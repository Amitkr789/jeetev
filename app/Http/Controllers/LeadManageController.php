<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Models\LeadReminder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LeadManageController extends Controller
{
    private function currentAdmin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /* ==================== PAGE ==================== */

    public function Leadmanage()
    {
        return view('pages.lead', [
            'admins'       => Admin::orderBy('name')->get(['id', 'name']),
            'leadTypes'    => Lead::LEAD_TYPES,
            'sources'      => ['Website', 'Referral', 'Cold Call', 'Social Media', 'Walk-in', 'Email Campaign', 'Trade Show', 'Other'],
            'platforms'    => ['Facebook', 'Instagram', 'LinkedIn', 'WhatsApp', 'Other'],
            // Sirf super_admin ko "view leads by user" wala search-select dikhega.
            'isSuperAdmin' => $this->currentAdmin()->role === 'super_admin',
        ]);
    }

    /* ==================== LISTING (AJAX) ====================
       Returns every lead the current admin is allowed to see, in one
       shot — filtering/search/pagination/category tabs (aur ab
       "view leads by user" bhi, super_admin ke liye) sab client-side
       lead.js me handle hote hai, kyunki super_admin ko yahin par
       saare leads already mil jaate hai visibleTo() se.

       Reminders aur Attachments dono per-admin scoped hai: super_admin
       sab dekhta hai, baaki sirf apna khud ka (jo unhone create/upload
       kiya ho), even if lead khud shared/assigned hai. Reminders yahan
       type ('reminder' ya 'note') dono ke saath aate hai — front-end
       (lead.blade.php) inhe do alag sections me split karta hai. */

    public function data()
    {
        $admin = $this->currentAdmin();

        $leads = Lead::query()
            ->with([
                'creator:id,name',
                'assignedAdmins:id,name',
                'reminders' => fn ($q) => $q->visibleTo($admin),
                'attachments' => fn ($q) => $q->visibleTo($admin)->with('uploader:id,name'),
            ])
            ->visibleTo($admin)
            ->orderByDesc('is_pinned')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'leads' => $leads,
            'current_admin' => ['id' => $admin->id, 'name' => $admin->name, 'role' => $admin->role],
        ]);
    }

    /* ==================== CREATE ==================== */

    public function store(Request $request)
    {
        $validator = $this->leadValidator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lead = DB::transaction(function () use ($request) {
            $lead = Lead::create([
                'customer_name' => $request->customer_name,
                'product_name' => $request->product_name,
                'whatsapp' => $request->whatsapp,
                'phone' => $request->phone,
                'source' => $request->source,
                'social_platform' => $request->source === 'Social Media' ? $request->social_platform : null,
                'lead_type' => $request->lead_type,
                'created_by' => $this->currentAdmin()->id,
            ]);

            $lead->assignedAdmins()->sync($request->input('assigned_admins', []));

            return $lead;
        });

        $lead->load(['creator:id,name', 'assignedAdmins:id,name']);

        return response()->json(['success' => true, 'message' => 'Lead added successfully.', 'lead' => $lead]);
    }

    /* ==================== UPDATE ==================== */

    public function update(Request $request, $id)
    {
        $lead = Lead::visibleTo($this->currentAdmin())->findOrFail($id);

        $validator = $this->leadValidator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lead->update([
            'customer_name' => $request->customer_name,
            'product_name' => $request->product_name,
            'whatsapp' => $request->whatsapp,
            'phone' => $request->phone,
            'source' => $request->source,
            'social_platform' => $request->source === 'Social Media' ? $request->social_platform : null,
            'lead_type' => $request->lead_type,
        ]);

        $lead->assignedAdmins()->sync($request->input('assigned_admins', []));
        $lead->load(['creator:id,name', 'assignedAdmins:id,name']);

        return response()->json(['success' => true, 'message' => 'Lead updated successfully.', 'lead' => $lead]);
    }

    private function leadValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'product_name' => 'required|string|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'phone' => 'required|string|max:20',
            'source' => 'required|string|max:50',
            'social_platform' => 'nullable|string|max:50',
            'lead_type' => 'required|in:' . implode(',', Lead::LEAD_TYPES),
            'assigned_admins' => 'nullable|array',
            'assigned_admins.*' => 'exists:admins,id',
        ]);
    }

    /* ==================== INLINE STATUS (lead type) UPDATE ====================
       Used by the "view lead" offcanvas so the lead's status/type can be
       changed right there — no need to open the full edit form. Only
       touches lead_type, so it stays valid even if other required fields
       (phone, product, etc.) were left blank on an older record. */

    public function updateType(Request $request, $id)
    {
        $lead = Lead::visibleTo($this->currentAdmin())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'lead_type' => 'required|in:' . implode(',', Lead::LEAD_TYPES),
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lead->lead_type = $request->lead_type;
        $lead->save();
        $lead->load(['creator:id,name', 'assignedAdmins:id,name']);

        return response()->json(['success' => true, 'message' => 'Lead status updated.', 'lead' => $lead]);
    }

    /* ==================== DELETE ==================== */

    public function destroy($id)
    {
        Lead::visibleTo($this->currentAdmin())->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Lead deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        $count = Lead::visibleTo($this->currentAdmin())->whereIn('id', $ids)->count();
        Lead::visibleTo($this->currentAdmin())->whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => "{$count} lead(s) deleted."]);
    }

    /* ==================== PIN ==================== */

    public function togglePin($id)
    {
        $lead = Lead::visibleTo($this->currentAdmin())->findOrFail($id);
        $lead->is_pinned = ! $lead->is_pinned;
        $lead->save();

        return response()->json(['success' => true, 'is_pinned' => $lead->is_pinned]);
    }

    /* ==================== DETAIL ==================== */

    public function show($id)
    {
        $admin = $this->currentAdmin();

        $lead = Lead::visibleTo($admin)
            ->with([
                'creator:id,name',
                'assignedAdmins:id,name',
                'reminders' => fn ($q) => $q->visibleTo($admin),
                'attachments' => fn ($q) => $q->visibleTo($admin)->with('uploader:id,name'),
            ])
            ->findOrFail($id);

        return response()->json(['success' => true, 'lead' => $lead]);
    }

    /* ==================== ATTACHMENTS ====================
       storeAttachment: koi bhi admin jo lead dekh sakta hai (visibleTo)
       usme attachment upload kar sakta hai.
       destroyAttachment: LeadAttachment::visibleTo() scope already
       non-super admins ko sirf apna khud ka upload hi dikhata/deta hai,
       isliye yahi findOrFail ownership check bhi ban jaata hai —
       super_admin kisi ka bhi delete kar sakta hai. */

    public function storeAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'attachment' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,zip,txt',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $admin = $this->currentAdmin();
        $lead = Lead::visibleTo($admin)->findOrFail($request->lead_id);

        $file = $request->file('attachment');
        $path = $file->store('lead-attachments', 'public');

        $attachment = LeadAttachment::create([
            'lead_id' => $lead->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $admin->id,
        ]);

        $attachment->load('uploader:id,name');

        return response()->json(['success' => true, 'message' => 'Attachment uploaded.', 'attachment' => $attachment]);
    }

    public function destroyAttachment($id)
    {
        $admin = $this->currentAdmin();

        $attachment = LeadAttachment::whereHas('lead', fn ($q) => $q->visibleTo($admin))
            ->visibleTo($admin)
            ->findOrFail($id);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
    }

    /* ==================== REMINDERS ====================
       A "reminder" entry can be one of two kinds, chosen on the front-end:
         - type=reminder : has an optional date/time + appointment type,
           shows up in the alarm/bell poll (checkReminders) and on the
           standalone Reminders page (remindersData).
         - type=note     : also has an optional date/time (just informational —
           it never alerts), but no appointment type. Only shown in the lead
           detail panel's "Notes" list.
       appointment_type is forced null server-side for a note regardless of
       what the client sends, since that concept only applies to reminders.
       A note never triggers the alarm/bell because checkReminders() below
       explicitly filters to type=reminder — not because it lacks a date.
       (It does still show up in the Reminders page's data, under its own
       "Notes" tab — see remindersData().) */

    public function storeReminder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'type' => 'nullable|in:reminder,note',
            'note' => 'required|string|max:1000',
            'reminder_date' => 'nullable|date',
            'reminder_time' => 'nullable|date_format:H:i',
            'appointment_type' => 'nullable|in:whatsapp,meeting',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lead = Lead::visibleTo($this->currentAdmin())->findOrFail($request->lead_id);

        $type = $request->input('type', 'reminder') === 'note' ? 'note' : 'reminder';

        $reminder = LeadReminder::create([
            'lead_id' => $lead->id,
            'type' => $type,
            'note' => $request->note,
            'reminder_date' => $request->reminder_date,
            'reminder_time' => $request->reminder_time,
            'appointment_type' => $type === 'note' ? null : $request->appointment_type,
            'created_by' => $this->currentAdmin()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => $type === 'note' ? 'Note added.' : 'Reminder added.',
            'reminder' => $reminder,
        ]);
    }

    public function updateReminderStatus(Request $request, $id)
    {
        $status = $request->input('status', 'completed');
        if (! in_array($status, ['scheduled', 'missed', 'silent', 'completed'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid status.'], 422);
        }

        $admin = $this->currentAdmin();
        $reminder = LeadReminder::whereHas('lead', fn ($q) => $q->visibleTo($admin))
            ->visibleTo($admin)
            ->findOrFail($id);

        $reminder->status = $status;
        $reminder->save();

        return response()->json(['success' => true, 'reminder' => $reminder]);
    }

    public function destroyReminder($id)
    {
        $admin = $this->currentAdmin();

        LeadReminder::whereHas('lead', fn ($q) => $q->visibleTo($admin))
            ->visibleTo($admin)
            ->findOrFail($id)
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Polled by the front-end (and hit again by a precisely-timed client
     * timer for each "upcoming" reminder — see global-reminders.js), so
     * this needs to stay cheap and reliable rather than assume the poll
     * interval alone drives punctuality.
     *
     * Only type=reminder entries are considered — type=note entries never
     * carry a date, so they'd already be excluded by whereNotNull below,
     * but the explicit type filter keeps the intent obvious.
     *
     * Returns:
     *   - due: reminders whose date/time has already arrived and are still
     *     'scheduled' — reported every call, not just the first, so the
     *     alarm/bell can stay accurate until the user handles them.
     *   - upcoming: still-'scheduled' reminders whose time hasn't arrived
     *     yet, each with an exact `due_at` ISO timestamp so the client can
     *     set a precise timer instead of waiting for the next poll tick.
     *   - server_time: lets the client correct for its own clock drift
     *     when scheduling those timers.
     *
     * Scoped per-admin: each admin only gets alerted for reminders THEY
     * created (super_admin gets everything).
     */
    public function checkReminders()
    {
        $admin = $this->currentAdmin();
        $now = Carbon::now();

        $all = LeadReminder::whereHas('lead', fn ($q) => $q->visibleTo($admin))
            ->visibleTo($admin)
            ->where('type', 'reminder')
            ->where('status', 'scheduled')
            ->whereNotNull('reminder_date')
            ->with('lead:id,customer_name')
            ->get()
            ->map(function ($reminder) {
                $reminder->due_at = Carbon::parse(
                    $reminder->reminder_date->format('Y-m-d') . ' ' . ($reminder->reminder_time ?? '00:00:00')
                )->toIso8601String();

                return $reminder;
            });

        $due = $all->filter(fn ($r) => Carbon::parse($r->due_at)->lte($now))->values();
        $upcoming = $all->filter(fn ($r) => Carbon::parse($r->due_at)->gt($now))->values();

        return response()->json([
            'success' => true,
            'due' => $due,
            'upcoming' => $upcoming,
            'server_time' => $now->toIso8601String(),
        ]);
    }

    /* ==================== REMINDERS PAGE ====================
       A dedicated page that lists every reminder AND note across every
       lead the admin can see (not just one lead's, like the offcanvas
       does). reminders.blade.php splits them into two tabs — "Reminders"
       and "Notes" — over this same data set; filtering/search/status/lead
       is handled client-side there, same pattern as the leads table. */

    public function remindersPage()
    {
        return view('pages.reminders', [
            'leads' => Lead::visibleTo($this->currentAdmin())
                ->orderBy('customer_name')
                ->get(['id', 'customer_name', 'product_name', 'phone', 'lead_type']),
        ]);
    }

    public function remindersData()
    {
        $admin = $this->currentAdmin();

        $reminders = LeadReminder::whereHas('lead', fn ($q) => $q->visibleTo($admin))
            ->visibleTo($admin)
            ->with(['lead:id,customer_name,product_name,phone,lead_type'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'reminders' => $reminders]);
    }
}
