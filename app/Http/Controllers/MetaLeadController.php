<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMetaLead;
use App\Models\Admin;
use App\Models\Lead;
use App\Models\MetaLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MetaLeadController extends Controller
{
    private function currentAdmin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /* ====================================================================
       WEBHOOK — called BY Meta, not by your admin panel. No admin auth,
       no CSRF (register these in routes/api.php, not routes/web.php).
       ==================================================================== */

    // GET — one-time handshake Meta does when you save the webhook URL
    // in the App Dashboard > Webhooks > Page > leadgen.
    public function webhookVerify(Request $request)
    {
        if (
            $request->get('hub_mode') === 'subscribe'
            && $request->get('hub_verify_token') === config('services.meta.verify_token')
        ) {
            return response($request->get('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    // POST — fired every time someone submits your Lead Ad form.
    // The payload only has IDs, so the real fetch + save is queued.
    public function webhookReceive(Request $request)
    {

        if (! $this->verifySignature($request)) {
            
            Log::warning('Meta webhook: signature mismatch', ['ip' => $request->ip()]);
            return response('Forbidden', 403);
        }

        foreach ($request->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? [];
                if (empty($value['leadgen_id'])) {
                    continue;
                }

                ProcessMetaLead::dispatchSync(
                    leadgenId: $value['leadgen_id'],
                    pageId: $value['page_id'] ?? null,
                    formId: $value['form_id'] ?? null,
                    adId: $value['ad_id'] ?? null,
                    adgroupId: $value['adgroup_id'] ?? null,
                    createdTime: isset($value['created_time']) ? date('c', (int) $value['created_time']) : null,
                );
            }
        }

        // Meta just wants a 200 — it doesn't read the body.
        return response()->json(['status' => 'ok']);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('services.meta.app_secret');
        if (! $secret) {
            // No secret configured yet — allow through so you can get the
            // integration working first, but set META_APP_SECRET before
            // going live so random POSTs to this URL can't inject fake leads.
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        Log::info('Meta Debug', [
    'header' => $request->header('X-Hub-Signature-256'),
    'secret' => !empty(config('services.meta.app_secret')),
]);
        return hash_equals($expected, $signature);
    }

    /* ====================================================================
       ADMIN PAGE — same shape as LeadManageController, but for the
       "inbox" of raw Meta submissions before they become real leads.
       ==================================================================== */

    public function index()
    {
        return view('pages.meta-leads', [
            'admins' => Admin::orderBy('name')->get(['id', 'name']),
            'leadTypes' => Lead::LEAD_TYPES,
        ]);
    }

    public function data()
    {
        $metaLeads = MetaLead::with('convertedLead:id,customer_name')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'meta_leads' => $metaLeads]);
    }

    public function show($id)
    {
        $metaLead = MetaLead::with('convertedLead:id,customer_name')->findOrFail($id);

        return response()->json(['success' => true, 'meta_lead' => $metaLead]);
    }

    /* ====================================================================
       CONVERT — this is the step the user asked for: "jab uss lead ko
       save kare to phir mere real wala lead ke table me chala jaye".
       Creates a genuine row in `leads`, using the same shape/validation
       as LeadManageController@store, then marks the meta_leads row done.
       ==================================================================== */

    public function convert(Request $request, $id)
    {
        $metaLead = MetaLead::findOrFail($id);

        if ($metaLead->status === 'converted') {
            return response()->json(['success' => false, 'message' => 'This lead was already converted.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'product_name' => 'required|string|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'phone' => 'required|string|max:20',
            'social_platform' => 'nullable|string|max:50',
            'lead_type' => 'required|in:' . implode(',', Lead::LEAD_TYPES),
            'assigned_admins' => 'nullable|array',
            'assigned_admins.*' => 'exists:admins,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lead = Lead::create([
            'customer_name' => $request->customer_name,
            'product_name' => $request->product_name,
            'whatsapp' => $request->whatsapp,
            'phone' => $request->phone,
            'source' => 'Social Media',
            'social_platform' => $request->input('social_platform', 'Facebook'),
            'lead_type' => $request->lead_type,
            'created_by' => $this->currentAdmin()->id,
        ]);

        $lead->assignedAdmins()->sync($request->input('assigned_admins', []));

        $metaLead->status = 'converted';
        $metaLead->converted_lead_id = $lead->id;
        $metaLead->save();

        $lead->load(['creator:id,name', 'assignedAdmins:id,name']);

        return response()->json(['success' => true, 'message' => 'Converted to a lead.', 'lead' => $lead]);
    }

    public function discard($id)
    {
        $metaLead = MetaLead::findOrFail($id);
        $metaLead->status = 'discarded';
        $metaLead->save();

        return response()->json(['success' => true, 'message' => 'Marked as discarded.']);
    }

    /* ====================================================================
       DELETE
       ==================================================================== */

    public function destroy($id)
    {
        MetaLead::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Meta lead deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        $count = MetaLead::whereIn('id', $ids)->count();
        MetaLead::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => "{$count} deleted."]);
    }
}