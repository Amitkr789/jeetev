<?php

namespace App\Jobs;

use App\Models\MetaLead;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta's webhook payload only ever carries IDs (leadgen_id, form_id,
 * page_id, ad_id...) — never the actual form answers. Those have to be
 * fetched separately from the Graph API using the leadgen_id.
 *
 * This is queued (not done inline in the controller) because Meta expects
 * a fast 200 response from the webhook and will retry / eventually
 * disable the subscription if it's slow.
 *
 * If you don't want to set up a queue worker right now, this class can
 * still be called synchronously with ProcessMetaLead::dispatchSync(...)
 * — just know the webhook response will be a bit slower.
 */
class ProcessMetaLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 15;

    public function __construct(
        public string $leadgenId,
        public ?string $pageId = null,
        public ?string $formId = null,
        public ?string $adId = null,
        public ?string $adgroupId = null,
        public ?string $createdTime = null, // ISO string
    ) {}

    public function handle(): void
    {
        // Meta redelivers the same webhook fairly often — this keeps it idempotent.
        if (MetaLead::where('leadgen_id', $this->leadgenId)->exists()) {
            return;
        }

        $token = config('services.meta.page_access_token');

        $response = Http::get("https://graph.facebook.com/v19.0/{$this->leadgenId}", [
            'access_token' => $token,
        ]);

        if (! $response->ok()) {
            Log::warning('Meta lead fetch failed', [
                'leadgen_id' => $this->leadgenId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->fail('Graph API fetch failed: ' . $response->body());
            return;
        }

        $data = $response->json();

        // field_data looks like: [{"name":"full_name","values":["Jordan Patel"]}, ...]
        $fields = collect($data['field_data'] ?? [])
            ->mapWithKeys(fn ($f) => [$f['name'] => $f['values'][0] ?? null]);

        $customerName = $fields->get('full_name')
            ?: trim(($fields->get('first_name') ?? '') . ' ' . ($fields->get('last_name') ?? ''))
            ?: null;

        MetaLead::updateOrCreate(
            ['leadgen_id' => $this->leadgenId],
            [
                'page_id' => $this->pageId,
                'form_id' => $data['form_id'] ?? $this->formId,
                'form_name' => $data['form_name'] ?? null,
                'ad_id' => $this->adId,
                'ad_name' => $data['ad_name'] ?? null,
                'adset_id' => $this->adgroupId,
                'adset_name' => $data['adset_name'] ?? null,
                'campaign_id' => $data['campaign_id'] ?? null,
                'campaign_name' => $data['campaign_name'] ?? null,

                'customer_name' => $customerName ?: null,
                'product_name' => $fields->get('product') ?? $fields->get('interested_in') ?? ($data['form_name'] ?? 'Meta Ad Enquiry'),
                'whatsapp' => $fields->get('whatsapp_number') ?? $fields->get('phone_number'),
                'phone' => $fields->get('phone_number'),
                'email' => $fields->get('email'),

                'raw_payload' => $data,
                'status' => 'new',
                'received_at' => $this->createdTime ? Carbon::parse($this->createdTime) : now(),
            ]
        );
    }
}