<?php

namespace App\Http\Controllers;

use App\Models\ChatbotSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatbotSettingController extends Controller
{
    public function index()
    {
        $settings = ChatbotSetting::current();

        return view('pages.chatbot-settings', [
            'settings' => $settings,
            // Ready-to-paste URL for the Meta App Dashboard's webhook config.
            'webhookUrl' => url('/whatsapp/webhook'),
        ]);
    }

    public function show()
    {
        return response()->json(['success' => true, 'settings' => $this->transform(ChatbotSetting::current())]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ai_globally_enabled' => 'required|boolean',
            'openai_api_key' => 'nullable|string',
            'openai_model' => 'required|string|max:100',
            'openai_embedding_model' => 'required|string|max:100',
            'confidence_threshold' => 'required|integer|min:0|max:100',
            'system_prompt' => 'nullable|string|max:4000',
            'fallback_message_en' => 'required|string|max:500',
            'fallback_message_hi' => 'required|string|max:500',
            'fallback_message_bn' => 'required|string|max:500',
            'whatsapp_phone_number_id' => 'nullable|string|max:100',
            'whatsapp_business_account_id' => 'nullable|string|max:100',
            'whatsapp_access_token' => 'nullable|string',
            'whatsapp_api_version' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $settings = ChatbotSetting::current();

        $payload = $request->only([
            'ai_globally_enabled', 'openai_model', 'openai_embedding_model', 'confidence_threshold',
            'system_prompt', 'fallback_message_en', 'fallback_message_hi', 'fallback_message_bn',
            'whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_api_version',
        ]);

        // Blank secret fields mean "leave unchanged" — the current value
        // is never sent back to the browser (see ChatbotSetting::$hidden),
        // so an empty submit must not overwrite it with an empty string.
        if ($request->filled('openai_api_key')) {
            $payload['openai_api_key'] = $request->openai_api_key;
        }
        if ($request->filled('whatsapp_access_token')) {
            $payload['whatsapp_access_token'] = $request->whatsapp_access_token;
        }

        $settings->update($payload);

        return response()->json(['success' => true, 'message' => 'Chatbot settings saved.', 'settings' => $this->transform($settings)]);
    }

    public function regenerateWebhookToken()
    {
        $settings = ChatbotSetting::current();
        $settings->update(['whatsapp_webhook_verify_token' => bin2hex(random_bytes(16))]);

        return response()->json(['success' => true, 'message' => 'Webhook verify token regenerated. Update it in the Meta App Dashboard too.']);
    }

    private function transform(ChatbotSetting $s): array
    {
        return [
            'ai_globally_enabled' => $s->ai_globally_enabled,
            'openai_model' => $s->openai_model,
            'openai_embedding_model' => $s->openai_embedding_model,
            'confidence_threshold' => $s->confidence_threshold,
            'system_prompt' => $s->system_prompt,
            'fallback_message_en' => $s->fallback_message_en,
            'fallback_message_hi' => $s->fallback_message_hi,
            'fallback_message_bn' => $s->fallback_message_bn,
            'whatsapp_phone_number_id' => $s->whatsapp_phone_number_id,
            'whatsapp_business_account_id' => $s->whatsapp_business_account_id,
            'whatsapp_api_version' => $s->whatsapp_api_version,
            'has_openai_key' => ! empty($s->getRawOriginal('openai_api_key')),
            'has_whatsapp_token' => ! empty($s->getRawOriginal('whatsapp_access_token')),
            'webhook_url' => url('/whatsapp/webhook'),
        ];
    }
}