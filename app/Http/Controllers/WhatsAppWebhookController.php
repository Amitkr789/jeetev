<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\ChatbotSetting;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta calls this once, with GET, when you save the webhook URL in
     * the App Dashboard. Must echo back hub_challenge if the verify
     * token matches, or Meta refuses to save the webhook.
     */
    // public function verify(Request $request)
    // {
    //     $settings = ChatbotSetting::current();

    //     if (
    //         $request->query('hub_mode') === 'subscribe'
    //         && hash_equals((string) $settings->whatsapp_webhook_verify_token, (string) $request->query('hub_verify_token'))
    //     ) {
    //         return response((string) $request->query('hub_challenge'), 200);
    //     }

    //     return response('Forbidden', 403);
    // }

        public function verify(Request $request)
{
    if (
        $request->query('hub_mode') === 'subscribe' &&
        $request->query('hub_verify_token') === env('META_VERIFY_TOKEN')
    ) {
        return response($request->query('hub_challenge'), 200);
    }

    return response('Forbidden', 403);
}

    /**
     * Meta calls this with POST for every inbound message and every
     * delivery-status update. Kept intentionally light — heavy lifting
     * (AI reply, KB retrieval) happens in a queued job so this responds
     * well within Meta's timeout.
     */
    public function receive(Request $request)
    {
        Log::info('WEBHOOK HIT', $request->all());
        $value = $request->input('entry.0.changes.0.value', []);

        foreach ($value['messages'] ?? [] as $message) {
            $contactName = $value['contacts'][0]['profile']['name'] ?? null;
            (new \App\Jobs\ProcessIncomingWhatsAppMessage($message, $contactName))
    ->handle(
        app(\App\Services\ChatbotReplyService::class),
        app(\App\Services\WhatsAppService::class)
    );
        }

        foreach ($value['statuses'] ?? [] as $status) {
            if (! empty($status['id'])) {
                ChatMessage::where('wa_message_id', $status['id'])->update([
                    'wa_status' => $status['status'] ?? null,
                ]);
            }
        }

        // Meta only cares about a 200 — body content is ignored.
        return response()->json(['success' => true]);
    }

}
