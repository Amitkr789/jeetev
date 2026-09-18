<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Bank;
use App\Models\Bill;
use App\Models\BillingHeader;
use App\Models\InventoryItem;
use App\Models\Lead;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule; // NEW
class BillingManageController extends Controller
{
    private const TAX_TYPES = ['CGST', 'SGST', 'IGST', 'UGST'];
    private const BILL_TYPES = ['invoice' => 'Invoice', 'quotation' => 'Quotation', 'pi' => 'Proforma Invoice'];

    private function currentAdmin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /* ==================== PAGE ====================
       Leads, products, banks and billing headers are all hydrated up front
       (same pattern as $admins on the leads page) so every search-select on
       the form works instantly with no extra AJAX round trip. */

    public function billingPage()
{
    $admin = $this->currentAdmin();
    
    // Product apna koi price store nahi karta — sirf SKU/HSN/unit ka
    // catalog hai. Actual price hamesha inventory_items me hota hai
    // (jab bhi stock in/out hua, us waqt ka price). Yahan har product ke
    // liye sabse latest inventory row ka price nikaal rahe hain, ek hi
    // query me (N+1 se bachne ke liye).
    $latestPrices = DB::table('inventory_items as ii')
        ->select('ii.product_id', 'ii.price')
        ->whereRaw('ii.id = (
            select ii2.id from inventory_items ii2
            where ii2.product_id = ii.product_id
            order by ii2.entry_date desc, ii2.id desc
            limit 1
        )')
        ->pluck('ii.price', 'ii.product_id');

    $products = Product::orderBy('name')->get()->map(function (Product $p) use ($latestPrices) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'hsn' => $p->hsn,
            'unit' => $p->unit,
            'stock' => $p->total_quantity,
            'last_price' => (float) ($latestPrices[$p->id] ?? 0),
        ];
    })->values();

    // Super admin can bill for any lead. A regular admin can only bill
    // for leads they created or leads assigned to them.
    // ASSUMPTION: the leads table has a `created_by` column (owner) and
    // an `assigned_to` column (Admin id, the admin the lead is assigned
    // to). If your Lead model uses different column names (e.g.
    // `assigned_admin_id`), update the two column names below and in
    // BillingManageController::checkLeadAccess().
    $leadsQuery = Lead::query();
    if (!$admin->isSuperAdmin()) {
        $leadsQuery->where(function ($q) use ($admin) {
            $q->where('created_by', $admin->id)
                ->orWhere('assigned_to', $admin->id);
        });
    }

    $leads = Lead::visibleTo($admin)->orderByDesc('id')->get([
    'id', 'customer_name', 'company_name', 'gst_number', 'phone', 'whatsapp', 'email', 'billing_address', 'shipping_address',
]);

    return view('pages.billings', [
        'leads' => $leads,
        'products' => $products,
        'banks' => Bank::orderBy('bank_name')->get(),
        'billingHeaders' => BillingHeader::orderByDesc('is_default')->orderBy('company_name')->get(),
        'taxTypes' => self::TAX_TYPES,
        'billTypes' => self::BILL_TYPES,
        'lastNumbers' => [
            'invoice' => $this->lastBillNumber('invoice'),
            'quotation' => $this->lastBillNumber('quotation'),
            'pi' => $this->lastBillNumber('pi'),
        ],
        'nextNumbers' => [
            'invoice' => $this->previewNextBillNumber('invoice'),
            'quotation' => $this->previewNextBillNumber('quotation'),
            'pi' => $this->previewNextBillNumber('pi'),
        ],
    ]);
}

    /* ==================== LISTING (AJAX) ==================== */

    public function data()
{
    $admin = $this->currentAdmin();

    $query = Bill::with([
        'items',
        'lead:id,customer_name',
        'bank:id,bank_name',
        'billingHeader:id,company_name',
        'creator:id,name'
    ]);

    // Sirf Super Admin sab dekh sakta hai
    if (!$admin->isSuperAdmin()) {
        $query->where('created_by', $admin->id);
    }

    $bills = $query->orderByDesc('id')->get();

    return response()->json([
        'success' => true,
        'bills'   => $bills,
    ]);
}

    public function show($id)
{
    $admin = $this->currentAdmin();

    $query = Bill::with([
        'items.product:id,name,sku,hsn,unit',
        'lead',
        'bank',
        'billingHeader',
        'creator'
    ]);

    if (!$admin->isSuperAdmin()) {
        $query->where('created_by', $admin->id);
    }

    $bill = $query->findOrFail($id);

    return response()->json([
        'success' => true,
        'bill'    => $bill,
    ]);
}

    /* ==================== CREATE ==================== */

   public function store(Request $request)
{
    $validator = $this->billValidator($request);
    if ($validator->fails()) {
        return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
    }

    if ($err = $this->checkLeadAccess($request)) {
        return response()->json(['success' => false, 'errors' => $err], 422);
    }

    if ($request->bill_type === 'invoice') {
        if ($err = $this->checkStockAvailability($request->items)) {
            return response()->json(['success' => false, 'errors' => $err], 422);
        }
    }

    try {
        $bill = DB::transaction(function () use ($request) {
            $calc = $this->calculateTotals($request->items, $request->courier_price, $request->courier_tax_percent);

            $bill = Bill::create(array_merge($this->billAttributes($request), [
                'bill_number' => $request->bill_number, // CHANGED: manual, no longer auto
                'bill_type' => $request->bill_type,
                'courier_tax_amount' => $calc['courier_tax_amount'],
                'subtotal' => $calc['subtotal'],
                'total_tax' => $calc['total_tax'],
                'grand_total' => $calc['grand_total'],
                'created_by' => $this->currentAdmin()->id,
            ]));

            foreach ($calc['items'] as $item) {
                $bill->items()->create($item);
            }

            if ($request->bill_type === 'invoice') {
                $bill->load('items');
                $this->deductStock($bill);
            }

            return $bill;
        });
    } catch (\Illuminate\Database\QueryException $e) {
        // NEW: agar validator ke baad, insert se pehle koi doosra request
        // wahi number le gaya (race), DB unique constraint yahan pakdega —
        // isliye bills.bill_number pe unique index zaroor hona chahiye.
        if ($e->getCode() === '23000') {
            return response()->json([
                'success' => false,
                'errors' => ['bill_number' => ['This bill number is already in use. Please choose another.']],
            ], 422);
        }
        throw $e;
    }

    $bill->load(['items', 'lead', 'bank', 'billingHeader', 'creator']);

    return response()->json(['success' => true, 'message' => 'Bill generated.', 'bill' => $bill]);
}

    /* ==================== UPDATE ==================== */
public function update(Request $request, $id)
{
    $bill = Bill::with('items')->findOrFail($id);

    if (!$this->currentAdmin()->isSuperAdmin() && $bill->created_by != $this->currentAdmin()->id) {
        abort(403);
    }

    $validator = $this->billValidator($request, $bill->id); // CHANGED: ignore self
    if ($validator->fails()) {
        return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
    }

    if ($err = $this->checkLeadAccess($request)) {
        return response()->json(['success' => false, 'errors' => $err], 422);
    }

    if ($request->bill_type === 'invoice') {
        if ($err = $this->checkStockAvailability($request->items)) {
            return response()->json(['success' => false, 'errors' => $err], 422);
        }
    }

    try {
        DB::transaction(function () use ($request, $bill) {
            if ($bill->stock_deducted) {
                $this->restoreStock($bill);
            }
            $bill->items()->delete();

            $calc = $this->calculateTotals($request->items, $request->courier_price, $request->courier_tax_percent);

            $bill->update(array_merge($this->billAttributes($request), [
                'bill_number' => $request->bill_number, // NEW: ab edit pe bhi manual number save hoga
                'bill_type' => $request->bill_type,
                'courier_tax_amount' => $calc['courier_tax_amount'],
                'subtotal' => $calc['subtotal'],
                'total_tax' => $calc['total_tax'],
                'grand_total' => $calc['grand_total'],
                'stock_deducted' => false,
            ]));

            foreach ($calc['items'] as $item) {
                $bill->items()->create($item);
            }

            if ($request->bill_type === 'invoice') {
                $bill->refresh();
                $bill->load('items');
                $this->deductStock($bill);
            }
        });
    } catch (\Illuminate\Database\QueryException $e) {
        if ($e->getCode() === '23000') {
            return response()->json([
                'success' => false,
                'errors' => ['bill_number' => ['This bill number is already in use. Please choose another.']],
            ], 422);
        }
        throw $e;
    }

    $bill->load(['items', 'lead', 'bank', 'billingHeader', 'creator']);

    return response()->json(['success' => true, 'message' => 'Bill updated.', 'bill' => $bill]);
}

    /* ==================== DELETE ==================== */

    public function destroy($id)
    {
        $bill = Bill::with('items')->findOrFail($id);

        if (
    !$this->currentAdmin()->isSuperAdmin() &&
    $bill->created_by != $this->currentAdmin()->id
) {
    abort(403);
}

        if ($bill->stock_deducted) {
            $this->restoreStock($bill);
        }
        $bill->delete();

        return response()->json(['success' => true, 'message' => 'Bill deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        $admin = $this->currentAdmin();

        $query = Bill::with('items')->whereIn('id', $ids);

        if (!$admin->isSuperAdmin()) {
            $query->where('created_by', $admin->id);
        }

        $bills = $query->get();
        foreach ($bills as $bill) {
            if ($bill->stock_deducted) {
                $this->restoreStock($bill);
            }
        }

        $count = $bills->count();
        Bill::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => "{$count} bill(s) deleted."]);
    }

    /* ==================== PDF ==================== */

    // Requires: composer require barryvdh/laravel-dompdf
    public function downloadPdf($id)
    {
        $bill = Bill::with(['items.product', 'lead', 'bank', 'billingHeader', 'creator'])->findOrFail($id);

        $pdf = app('dompdf.wrapper')->loadView('pages.bill', compact('bill'))->setPaper('a4');

        return $pdf->download($bill->bill_number . '.pdf');
    }

    /* ==================== VALIDATION / CALCULATION HELPERS ==================== */

    private function billValidator(Request $request, $ignoreBillId = null)
    {
        return Validator::make($request->all(), [
             'bill_number' => [
            'required',
            'string',
            'max:50',
            Rule::unique('bills', 'bill_number')->ignore($ignoreBillId),
        ],
            'bill_type' => 'required|in:' . implode(',', Bill::TYPES),
        'lead_id' => 'nullable|exists:leads,id',
            'customer_name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'gst_number' => 'nullable|string|max:20',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'billing_address' => 'nullable|string|max:1000',
            'shipping_address' => 'nullable|string|max:1000',
            'ship_same_as_billing' => 'boolean',
            'billing_date' => 'required|date',
            'valid_till' => 'nullable|date',
            'billing_header_id' => 'nullable|exists:billing_headers,id',
            'bank_id' => 'nullable|exists:banks,id',
            'courier_name' => 'nullable|string|max:255',
            'courier_price' => 'nullable|numeric|min:0',
            'courier_tax_type' => 'nullable|in:' . implode(',', self::TAX_TYPES),
            'courier_tax_percent' => 'nullable|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.hsn_sku' => 'nullable|string|max:100',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.taxes' => 'nullable|array',
            'items.*.taxes.*.type' => 'required_with:items.*.taxes|in:' . implode(',', self::TAX_TYPES),
            'items.*.taxes.*.percent' => 'required_with:items.*.taxes|numeric|min:0|max:100',
        ]);
    }

    /**
     * Super admin can raise a bill against any lead (or none — "new
     * customer"). A regular admin can only raise a bill with no lead_id
     * (new customer), or with a lead_id for a lead they created or that is
     * assigned to them. Returns a validator-shaped error array, or null
     * when the request is allowed to proceed.
     *
     * ASSUMPTION: see the comment on the same columns in billingPage().
     */
    private function checkLeadAccess(Request $request): ?array
{
    if (!$request->lead_id) {
        return null;
    }

    $admin = $this->currentAdmin();
    if ($admin->isSuperAdmin()) {
        return null;
    }

    $lead = Lead::visibleTo($admin)->find($request->lead_id);
    if (!$lead) {
        return ['lead_id' => ['You can only bill for your own leads or leads assigned to you.']];
    }

    return null;
}

    private function billAttributes(Request $request): array
    {
        return [
            'lead_id' => $request->lead_id,
            'customer_name' => $request->customer_name,
            'company_name' => $request->company_name,
            'gst_number' => $request->gst_number,
            'phone' => $request->phone,
            'email' => $request->email,
            'billing_address' => $request->billing_address,
            'shipping_address' => $request->boolean('ship_same_as_billing') ? $request->billing_address : $request->shipping_address,
            'ship_same_as_billing' => $request->boolean('ship_same_as_billing'),
            'billing_date' => $request->billing_date,
            'valid_till' => $request->valid_till,
            'billing_header_id' => $request->billing_header_id,
            'bank_id' => $request->bank_id,
            'courier_name' => $request->courier_name,
            'courier_price' => $request->courier_price ?? 0,
            'courier_tax_type' => $request->courier_tax_type,
            'courier_tax_percent' => $request->courier_tax_percent ?? 0,
        ];
    }

    /**
     * Recomputes every line total server-side from price/quantity/taxes —
     * never trusts totals the browser might have sent.
     */
    private function calculateTotals(array $items, $courierPrice, $courierTaxPercent): array
    {
        $subtotal = 0;
        $totalTax = 0;
        $computedItems = [];

        foreach ($items as $item) {
            $taxable = round(($item['price'] ?? 0) * ($item['quantity'] ?? 0), 2);
            $taxAmount = 0;
            $taxes = [];

            foreach (($item['taxes'] ?? []) as $tax) {
                $amt = round($taxable * (($tax['percent'] ?? 0) / 100), 2);
                $taxes[] = ['type' => $tax['type'], 'percent' => (float) $tax['percent'], 'amount' => $amt];
                $taxAmount += $amt;
            }

            $total = round($taxable + $taxAmount, 2);
            $subtotal += $taxable;
            $totalTax += $taxAmount;

            $computedItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'hsn_sku' => $item['hsn_sku'] ?? null,
                'unit' => $item['unit'] ?? null,
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'taxable_amount' => $taxable,
                'taxes' => $taxes,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ];
        }

        $courierPrice = (float) ($courierPrice ?? 0);
        $courierTaxPercent = (float) ($courierTaxPercent ?? 0);
        $courierTaxAmount = round($courierPrice * ($courierTaxPercent / 100), 2);

        $subtotal += $courierPrice;
        $totalTax += $courierTaxAmount;

        return [
            'items' => $computedItems,
            'subtotal' => round($subtotal, 2),
            'total_tax' => round($totalTax, 2),
            'courier_tax_amount' => $courierTaxAmount,
            'grand_total' => round($subtotal + $totalTax, 2),
        ];
    }

    /**
     * For invoice-type bills, makes sure every line with a real product
     * doesn't ask for more than what's currently in stock. Returns a
     * Laravel-validator-shaped error array, or null if everything is fine.
     */
    private function checkStockAvailability(array $items): ?array
    {
        foreach ($items as $idx => $item) {
            if (empty($item['product_id'])) {
                continue;
            }
            $product = Product::find($item['product_id']);
            if ($product && $product->total_quantity < (float) ($item['quantity'] ?? 0)) {
                return ["items.{$idx}.quantity" => ["Only {$product->total_quantity} {$product->unit} of \"{$product->name}\" left in stock."]];
            }
        }

        return null;
    }

    /* ==================== BILL NUMBERING ==================== */

    private function prefixFor(string $type): string
    {
        return match ($type) {
            'invoice' => 'INV',
            'quotation' => 'QUO',
            'pi' => 'PI',
            default => 'BILL',
        };
    }

    private function lastBillNumber(string $type): ?string
    {
        return Bill::where('bill_type', $type)->orderByDesc('id')->value('bill_number');
    }

    // Locked read used at the moment of actually creating a bill, to avoid a
    // race between two people generating a bill of the same type at once.
    private function nextBillNumber(string $type): string
    {
        $last = Bill::where('bill_type', $type)->lockForUpdate()->orderByDesc('id')->value('bill_number');
        $seq = 0;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1];
        }

        return $this->prefixFor($type) . '-' . str_pad($seq + 1, 4, '0', STR_PAD_LEFT);
    }

    // Unlocked version, just for showing a preview number on the empty form.
    private function previewNextBillNumber(string $type): string
    {
        $last = $this->lastBillNumber($type);
        $seq = 0;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1];
        }

        return $this->prefixFor($type) . '-' . str_pad($seq + 1, 4, '0', STR_PAD_LEFT);
    }

    /* ==================== STOCK MOVEMENT ====================
       IMPORTANT: quantity is always stored POSITIVE here. Direction is
       controlled entirely by `type` ('out' for a sale), never by sign —
       see the add_type_to_inventory_items_table migration for why: a
       negative-quantity convention silently turns back into a stock
       ADDITION the moment anything downstream normalizes quantity to a
       positive number (unsigned column, model mutator, etc). `type` can't
       be silently flattened the same way. */

    private function deductStock(Bill $bill): void
    {
        foreach ($bill->items as $item) {
            if (! $item->product_id) {
                continue;
            }
            InventoryItem::create([
                'product_id' => $item->product_id,
                'dealer_id' => null,
                'bill_item_id' => $item->id,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'price' => $item->price,
                'type' => 'out',
                'entry_date' => now()->toDateString(),
                'description' => "Sold via {$bill->bill_number}",
                'created_by' => $this->currentAdmin()->id,
            ]);
        }

        $bill->update(['stock_deducted' => true]);
    }

    private function restoreStock(Bill $bill): void
    {
        InventoryItem::whereIn('bill_item_id', $bill->items->pluck('id'))->delete();
        $bill->update(['stock_deducted' => false]);
    }

    /* ==================== BANKS ==================== */

    public function storeBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:30|confirmed',
            'ifsc_code' => 'required|string|max:20',
            'branch' => 'nullable|string|max:255',
            'qr_code' => 'nullable|image|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'branch']);
        if ($request->hasFile('qr_code')) {
            $data['qr_code'] = $request->file('qr_code')->store('banks', 'public');
        }
        $data['created_by'] = $this->currentAdmin()->id;

        $bank = Bank::create($data);

        return response()->json(['success' => true, 'message' => 'Bank added.', 'bank' => $bank]);
    }

    public function updateBank(Request $request, $id)
    {
        $bank = Bank::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:30|confirmed',
            'ifsc_code' => 'required|string|max:20',
            'branch' => 'nullable|string|max:255',
            'qr_code' => 'nullable|image|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'branch']);
        if ($request->hasFile('qr_code')) {
            $data['qr_code'] = $request->file('qr_code')->store('banks', 'public');
        }

        $bank->update($data);

        return response()->json(['success' => true, 'message' => 'Bank updated.', 'bank' => $bank]);
    }

    public function destroyBank($id)
    {
        Bank::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Bank deleted.']);
    }

    /* ==================== BILLING HEADERS (company profile) ==================== */

    public function storeHeader(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'gstin' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|max:2048',
            'is_default' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['company_name', 'gstin', 'phone', 'address']);
        $data['is_default'] = $request->boolean('is_default');
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('billing-headers', 'public');
        }
        $data['created_by'] = $this->currentAdmin()->id;

        if ($data['is_default']) {
            BillingHeader::query()->update(['is_default' => false]);
        }

        $header = BillingHeader::create($data);

        return response()->json(['success' => true, 'message' => 'Billing header added.', 'header' => $header]);
    }

    public function updateHeader(Request $request, $id)
    {
        $header = BillingHeader::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'gstin' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|max:2048',
            'is_default' => 'boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['company_name', 'gstin', 'phone', 'address']);
        $data['is_default'] = $request->boolean('is_default');
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('billing-headers', 'public');
        }

        if ($data['is_default']) {
            BillingHeader::query()->where('id', '!=', $header->id)->update(['is_default' => false]);
        }

        $header->update($data);

        return response()->json(['success' => true, 'message' => 'Billing header updated.', 'header' => $header]);
    }

    public function destroyHeader($id)
    {
        BillingHeader::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Billing header deleted.']);
    }
}