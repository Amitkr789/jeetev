<?php

namespace App\Services;

use App\Models\ChatbotSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppService
{
    protected string $token;
    protected string $phoneNumberId;
    protected string $apiVersion;

    public function __construct(?ChatbotSetting $settings = null)
    {
        $settings = $settings ?? ChatbotSetting::current();

        $this->token = (string) $settings->whatsapp_access_token;
        $this->phoneNumberId = (string) $settings->whatsapp_phone_number_id;
        $this->apiVersion = $settings->whatsapp_api_version ?: 'v20.0';

        if (! $this->token || ! $this->phoneNumberId) {
            throw new RuntimeException('WhatsApp is not configured yet. Fill in Phone Number ID and Access Token under Chatbot Settings.');
        }
    }

    /**
     * Sends a plain text message. Returns the WhatsApp message id on
     * success (used later to track delivered/read status), or null on
     * failure (already logged).
     */
    public function sendText(string $toPhone, string $body): ?string
    {
        $response = Http::withToken($this->token)
            ->timeout(20)
            ->post($this->endpoint('messages'), [
                'messaging_product' => 'whatsapp',
                'to' => $toPhone,
                'type' => 'text',
                'text' => ['body' => $body, 'preview_url' => false],
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp sendText failed', ['to' => $toPhone, 'response' => $response->body()]);
            return null;
        }

        return $response->json('messages.0.id');
    }

    public function markAsRead(string $waMessageId): void
    {
        Http::withToken($this->token)->post($this->endpoint('messages'), [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
        ]);
    }

    /** Resolves a media id from an inbound webhook payload into a downloadable URL. */
    public function resolveMediaUrl(string $mediaId): ?string
    {
        $response = Http::withToken($this->token)->get("https://graph.facebook.com/{$this->apiVersion}/{$mediaId}");

        if ($response->failed()) {
            return null;
        }

        return $response->json('url');
    }

    /**
     * Uploads a local file to Meta's media endpoint, then sends a message
     * referencing that media id. Two-step, server-to-server upload.
     *
     * @param  string  $waType  image | audio | video | document
     */
    public function sendMedia(string $to, string $waType, string $localFilePath, string $mimeType, ?string $caption = null): ?string
    {
        $upload = Http::withToken($this->token)
            ->timeout(30)
            ->attach('file', file_get_contents($localFilePath), basename($localFilePath), ['Content-Type' => $mimeType])
            ->post($this->endpoint('media'), [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if ($upload->failed()) {
            Log::error('WhatsApp media upload failed', ['to' => $to, 'response' => $upload->body()]);
            return null;
        }

        $mediaId = $upload->json('id');

        $send = Http::withToken($this->token)
            ->timeout(20)
            ->post($this->endpoint('messages'), [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => $waType,
                $waType => array_filter([
                    'id' => $mediaId,
                    'caption' => $caption,
                    'filename' => $waType === 'document' ? basename($localFilePath) : null,
                ]),
            ]);

        if ($send->failed()) {
            Log::error('WhatsApp sendMedia failed', ['to' => $to, 'response' => $send->body()]);
            return null;
        }

        return $send->json('messages.0.id');
    }

    public function downloadMedia(string $mediaId): ?array
    {
        $meta = Http::withToken($this->token)->get("https://graph.facebook.com/{$this->apiVersion}/{$mediaId}");

        if ($meta->failed()) {
            Log::error('WhatsApp media meta lookup failed', ['media_id' => $mediaId, 'response' => $meta->body()]);
            return null;
        }

        $url = $meta->json('url');
        $mime = $meta->json('mime_type');

        $download = Http::withHeaders(['Authorization' => 'Bearer ' . $this->token])->get($url);

        if ($download->failed()) {
            Log::error('WhatsApp media download failed', ['media_id' => $mediaId]);
            return null;
        }

        return [
            'contents' => $download->body(),
            'mime' => $mime,
        ];
    }

    private function endpoint(string $path): string
    {
        return "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/{$path}";
    }
}
