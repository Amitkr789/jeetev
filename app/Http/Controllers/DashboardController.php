<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Bill;
use App\Models\ChatConversation;
use App\Models\InventoryItem;
use App\Models\Lead;
use App\Models\LeadReminder;
use App\Models\MetaLead;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private function currentAdmin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /* ==================== PAGE ====================
       Super admin gets every active admin in a search-select so the
       whole dashboard can be pivoted onto one person's numbers. A
       regular admin never sees that control — their own id is baked
       into every query in data() below no matter what the client sends. */

    public function index()
    {
        $admin = $this->currentAdmin();

        return view('home', [
            'isSuperAdmin' => $admin->isSuperAdmin(),
            'teamAdmins'   => $admin->isSuperAdmin()
                ? Admin::where('status', 'active')->orderBy('name')->get(['id', 'name', 'role'])
                : collect(),
        ]);
    }

    /* ==================== DATA (AJAX) ====================
       One endpoint powers every widget on the page.

       Query params:
         range     : today|yesterday|week|month|last_month|year|custom (default: month)
         date_from : Y-m-d   (only read when range=custom)
         date_to   : Y-m-d   (only read when range=custom)
         admin_id  : "all" or an admin id — only ever honoured for super_admin
    */

    public function data(Request $request)
    {
        $admin = $this->currentAdmin();
        $isSuper = $admin->isSuperAdmin();

        [$from, $to, $label] = $this->resolveRange($request);
        [$prevFrom, $prevTo] = $this->previousRange($from, $to);

        // Non-super admins can never look at anyone else's numbers —
        // whatever admin_id they pass in is silently ignored.
        if ($isSuper) {
            $requested = $request->input('admin_id', 'all');
            $scopeAdminId = ($requested === 'all' || $requested === null || $requested === '') ? null : (int) $requested;
        } else {
            $scopeAdminId = $admin->id;
        }

        return response()->json([
            'success' => true,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $label],
            'is_super_admin' => $isSuper,
            'scoped_admin_id' => $scopeAdminId,
            'scoped_admin_name' => $scopeAdminId ? Admin::find($scopeAdminId)?->name : null,

            'leads'      => $this->leadStats($from, $to, $prevFrom, $prevTo, $scopeAdminId),
            'earnings'   => $this->earningStats($from, $to, $prevFrom, $prevTo, $scopeAdminId),
            'ad_leads'   => $this->adLeadStats($from, $to, $scopeAdminId),
            'tasks'      => $this->taskStats($from, $to, $prevFrom, $prevTo, $scopeAdminId),
            'reminders'  => $this->reminderStats($scopeAdminId),
            'chats'      => $this->chatStats(),
            'inventory'  => $this->inventoryStats(),

            'leaderboard'   => ($isSuper && $scopeAdminId === null) ? $this->leaderboard($from, $to) : [],
            'top_customers' => $this->topCustomers($from, $to, $scopeAdminId),
            'recent_leads'  => $this->recentLeads($scopeAdminId),
            'recent_bills'  => $this->recentBills($scopeAdminId),
            'upcoming_reminders' => $this->upcomingReminders($scopeAdminId),
        ]);
    }

    /* ==================== RANGE HELPERS ==================== */

    private function resolveRange(Request $request): array
    {
        $range = $request->input('range', 'month');
        $now = Carbon::now();

        switch ($range) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today'];
            case 'yesterday':
                $y = $now->copy()->subDay();
                return [$y->copy()->startOfDay(), $y->copy()->endOfDay(), 'Yesterday'];
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'This Week'];
            case 'last_month':
                $m = $now->copy()->subMonthNoOverflow();
                return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth(), 'Last Month'];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'This Year'];
            case 'custom':
                $from = $request->filled('date_from')
                    ? Carbon::parse($request->date_from)->startOfDay()
                    : $now->copy()->startOfMonth();
                $to = $request->filled('date_to')
                    ? Carbon::parse($request->date_to)->endOfDay()
                    : $now->copy()->endOfDay();
                return [$from, $to, 'Custom Range'];
            case 'month':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This Month'];
        }
    }

    // Same-length window immediately before the selected range, used for
    // every "up/down X% vs previous period" trend figure on the cards.
    private function previousRange(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        return [$prevFrom, $prevTo];
    }

    private function percentChange($current, $previous): float
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    // Groups a collection by day, or by month once the range gets long
    // enough that a daily chart would be unreadable (>60 days).
    private function groupTrend($items, Carbon $from, Carbon $to, string $dateField, ?callable $valueFn = null)
    {
        $byMonth = $from->diffInDays($to) > 60;

        return $items
            ->groupBy(fn ($item) => $byMonth ? $item->{$dateField}->format('Y-m') : $item->{$dateField}->toDateString())
            ->map(fn ($group) => $valueFn ? $valueFn($group) : $group->count())
            ->sortKeys();
    }

    /* ==================== LEAD SCOPE ====================
       Mirrors Lead::scopeVisibleTo, but keyed off an arbitrary admin id
       rather than "whoever is logged in" — needed so a super_admin can
       pivot the dashboard onto someone else's numbers. */

    private function scopeLeadsTo($query, ?int $scopeAdminId): void
    {
        if ($scopeAdminId === null) {
            return;
        }
        $query->where(function ($q) use ($scopeAdminId) {
            $q->where('created_by', $scopeAdminId)
                ->orWhereHas('assignedAdmins', fn ($q2) => $q2->where('admins.id', $scopeAdminId));
        });
    }

    /* ==================== LEADS ==================== */

    private function leadStats(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo, ?int $scopeAdminId): array
    {
        $scoped = Lead::query();
        $this->scopeLeadsTo($scoped, $scopeAdminId);

        $allTime = (clone $scoped)->count();

        $inRange = (clone $scoped)->whereBetween('created_at', [$from, $to])
            ->get(['id', 'lead_type', 'source', 'social_platform', 'created_at']);

        $prevCount = (clone $scoped)->whereBetween('created_at', [$prevFrom, $prevTo])->count();

        $bySource = $inRange
            ->groupBy(fn ($l) => ($l->source === 'Social Media' && $l->social_platform) ? $l->social_platform : $l->source)
            ->map->count();

        $byType = collect(Lead::LEAD_TYPES)->mapWithKeys(
            fn ($t) => [$t => $inRange->where('lead_type', $t)->count()]
        );

        $converted = $byType['converted'] ?? 0;

        return [
            'total_all_time' => $allTime,
            'total_in_range' => $inRange->count(),
            'change_pct' => $this->percentChange($inRange->count(), $prevCount),
            'converted_in_range' => $converted,
            'conversion_rate' => $inRange->count() > 0 ? round(($converted / $inRange->count()) * 100, 1) : 0.0,
            'by_type' => $byType,
            'by_source' => $bySource,
            'trend' => $this->groupTrend($inRange, $from, $to, 'created_at'),
        ];
    }

    /* ==================== EARNINGS ====================
       Only real invoices count as "earning" — quotations and proforma
       invoices are shown as a separate pipeline figure and never added
       into revenue, since nothing has actually been billed for those. */

    private function earningStats(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo, ?int $scopeAdminId): array
    {
        $base = Bill::query();
        if ($scopeAdminId !== null) {
            $base->where('created_by', $scopeAdminId);
        }

        $bills = (clone $base)->whereBetween('billing_date', [$from, $to])
            ->get(['id', 'bill_type', 'grand_total', 'billing_date']);

        $invoices = $bills->where('bill_type', 'invoice');
        $quotations = $bills->where('bill_type', 'quotation');
        $proformas = $bills->where('bill_type', 'pi');

        $prevInvoiceTotal = (clone $base)->where('bill_type', 'invoice')
            ->whereBetween('billing_date', [$prevFrom, $prevTo])
            ->sum('grand_total');

        return [
            'total_earning' => round($invoices->sum('grand_total'), 2),
            'change_pct' => $this->percentChange($invoices->sum('grand_total'), $prevInvoiceTotal),
            'invoice_count' => $invoices->count(),
            'avg_invoice_value' => $invoices->count() > 0 ? round($invoices->avg('grand_total'), 2) : 0.0,
            'quotation_count' => $quotations->count(),
            'quotation_value' => round($quotations->sum('grand_total'), 2),
            'pi_count' => $proformas->count(),
            'pi_value' => round($proformas->sum('grand_total'), 2),
            'trend' => $this->groupTrend($invoices, $from, $to, 'billing_date', fn ($g) => round($g->sum('grand_total'), 2)),
        ];
    }

    /* ==================== AD (META) LEADS ====================
       A MetaLead row has no owner of its own — a regular admin's slice
       is "ad leads I personally converted into a real lead", matched
       through convertedLead.created_by. A super_admin viewing "All
       Team" gets the whole inbox regardless of who converted what. */

    private function adLeadStats(Carbon $from, Carbon $to, ?int $scopeAdminId): array
    {
        $query = MetaLead::query()->whereBetween('created_at', [$from, $to]);

        if ($scopeAdminId !== null) {
            $query->whereHas('convertedLead', fn ($q) => $q->where('created_by', $scopeAdminId));
        }

        $all = $query->get(['id', 'status', 'created_at']);
        $converted = $all->where('status', 'converted')->count();
        $discarded = $all->where('status', 'discarded')->count();

        return [
            'total' => $all->count(),
            'converted' => $converted,
            'discarded' => $discarded,
            'pending' => max($all->count() - $converted - $discarded, 0),
            'conversion_rate' => $all->count() > 0 ? round(($converted / $all->count()) * 100, 1) : 0.0,
        ];
    }

    /* ==================== TASKS ==================== */

    private function taskStats(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo, ?int $scopeAdminId): array
    {
        $base = Task::query();
        if ($scopeAdminId !== null) {
            $base->where('assigned_to', $scopeAdminId);
        }

        $tasks = (clone $base)->whereBetween('created_at', [$from, $to])->get();
        $completed = $tasks->where('status', 'completed')->count();

        $prevCompleted = (clone $base)->whereBetween('created_at', [$prevFrom, $prevTo])
            ->where('status', 'completed')->count();

        return [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $completed,
            'change_pct' => $this->percentChange($completed, $prevCompleted),
            'reverted' => $tasks->where('status', 'reverted')->count(),
            'on_hold' => $tasks->where('status', 'on_hold')->count(),
            'overdue' => $tasks->filter(fn ($t) => $t->isOverdue())->count(),
        ];
    }

    /* ==================== REMINDERS ====================
       Deliberately NOT date-ranged — "open" and "overdue" are a
       right-now snapshot, not something that should vanish because the
       dashboard filter is set to "Last Month". */

    private function reminderStats(?int $scopeAdminId): array
    {
        $query = LeadReminder::query()->where('status', 'scheduled');
        if ($scopeAdminId !== null) {
            $query->where('created_by', $scopeAdminId);
        }

        $rows = $query->get(['id', 'reminder_date', 'reminder_time']);
        $now = Carbon::now();
        $today = $now->toDateString();

        $overdue = 0;
        $dueToday = 0;
        foreach ($rows as $r) {
            if (! $r->reminder_date) {
                continue;
            }
            $due = Carbon::parse($r->reminder_date->format('Y-m-d') . ' ' . ($r->reminder_time ?? '00:00:00'));
            if ($due->lt($now)) {
                $overdue++;
            } elseif ($r->reminder_date->toDateString() === $today) {
                $dueToday++;
            }
        }

        return [
            'open' => $rows->count(),
            'overdue' => $overdue,
            'due_today' => $dueToday,
        ];
    }

    /* ==================== CHATS ====================
       WhatsApp inbox is a shared, un-owned surface until someone picks a
       conversation up — kept global for every role. Add a real scope
       here (e.g. handling_admin_id) if that should change later. */

    private function chatStats(): array
    {
        $all = ChatConversation::query()->get(['id', 'ai_active', 'handling_admin_id', 'assigned_admin_id', 'escalated_at']);

        return [
            'total' => $all->count(),
            'escalated' => $all->whereNotNull('escalated_at')->count(),
            'unassigned' => $all->whereNull('assigned_admin_id')->count(),
            'ai_handled' => $all->where('ai_active', true)->whereNull('handling_admin_id')->count(),
            'human_handled' => $all->whereNotNull('handling_admin_id')->count(),
        ];
    }

    /* ==================== INVENTORY (quick glance) ==================== */

    private function inventoryStats(): array
    {
        return [
            'products_tracked' => InventoryItem::query()->distinct('product_id')->count('product_id'),
        ];
    }

    /* ==================== LEADERBOARD ====================
       super_admin + "All Team" only — per-user breakdown so who's
       actually driving leads/revenue this period is visible without
       flipping the whole dashboard to each person one at a time. */

    private function leaderboard(Carbon $from, Carbon $to): array
    {
        return Admin::where('status', 'active')->get(['id', 'name', 'role'])
            ->map(function ($a) use ($from, $to) {
                $leadCount = Lead::whereBetween('created_at', [$from, $to])
                    ->where(function ($q) use ($a) {
                        $q->where('created_by', $a->id)
                            ->orWhereHas('assignedAdmins', fn ($q2) => $q2->where('admins.id', $a->id));
                    })->count();

                $earning = Bill::whereBetween('billing_date', [$from, $to])
                    ->where('bill_type', 'invoice')
                    ->where('created_by', $a->id)
                    ->sum('grand_total');

                $tasksCompleted = Task::whereBetween('created_at', [$from, $to])
                    ->where('assigned_to', $a->id)
                    ->where('status', 'completed')
                    ->count();

                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'role' => $a->role,
                    'leads' => $leadCount,
                    'earning' => round($earning, 2),
                    'tasks_completed' => $tasksCompleted,
                ];
            })
            ->sortByDesc('earning')
            ->values()
            ->all();
    }

    /* ==================== TOP CUSTOMERS ==================== */

    private function topCustomers(Carbon $from, Carbon $to, ?int $scopeAdminId): array
    {
        $query = Bill::whereBetween('billing_date', [$from, $to])->where('bill_type', 'invoice');
        if ($scopeAdminId !== null) {
            $query->where('created_by', $scopeAdminId);
        }

        return $query->selectRaw('customer_name, SUM(grand_total) as total, COUNT(*) as invoices')
            ->groupBy('customer_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->customer_name, 'total' => round((float) $r->total, 2), 'invoices' => (int) $r->invoices])
            ->all();
    }

    /* ==================== RECENT LISTS ==================== */

    private function recentLeads(?int $scopeAdminId)
    {
        $query = Lead::with('creator:id,name')->orderByDesc('is_pinned')->orderByDesc('id')->limit(6);
        $this->scopeLeadsTo($query, $scopeAdminId);

        return $query->get(['id', 'customer_name', 'product_name', 'phone', 'lead_type', 'source', 'created_by', 'created_at']);
    }

    private function recentBills(?int $scopeAdminId)
    {
        $query = Bill::with('creator:id,name')->orderByDesc('id')->limit(6);
        if ($scopeAdminId !== null) {
            $query->where('created_by', $scopeAdminId);
        }

        return $query->get(['id', 'bill_number', 'bill_type', 'customer_name', 'grand_total', 'billing_date', 'created_by']);
    }

    private function upcomingReminders(?int $scopeAdminId)
    {
        $query = LeadReminder::with('lead:id,customer_name')
            ->where('status', 'scheduled')
            ->orderBy('reminder_date')
            ->orderBy('reminder_time')
            ->limit(6);

        if ($scopeAdminId !== null) {
            $query->where('created_by', $scopeAdminId);
        }

        return $query->get();
    }
}
