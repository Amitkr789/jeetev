<?php

namespace App\Jobs;

use App\Events\ConversationListUpdated;
use App\Events\NewChatMessage;
use App\Models\ChatConversation;
use App\Services\ChatbotReplyService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param array<string, mixed> $waMessage raw "messages[n]" object from the webhook payload
     */
    public function __construct(public array $waMessage, public ?string $contactName = null)
    {
    }

    public function handle(ChatbotReplyService $replyService, WhatsAppService $whatsapp): void
    {
        $from = $this->waMessage['from'] ?? null;
        if (! $from) {
            return;
        }

        $conversation = ChatConversation::firstOrCreate(
            ['wa_phone_number' => $from],
            ['contact_name' => $this->contactName, 'ai_active' => true]
        );

        if ($this->contactName && $conversation->contact_name !== $this->contactName) {
            $conversation->contact_name = $this->contactName;
        }

        [$type, $body, $mediaId] = $this->extractContent($this->waMessage);
        $mediaUrl = $mediaId ? $whatsapp->resolveMediaUrl($mediaId) : null;
        $detectedLanguage = $body ? $this->quickScriptDetect($body) : null;

        $inbound = $conversation->messages()->create([
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'type' => $type,
            'body' => $body,
            'media_url' => $mediaUrl,
            'wa_message_id' => $this->waMessage['id'] ?? null,
            'wa_status' => 'received',
            'language' => $detectedLanguage,
        ]);

        $conversation->forceFill([
            'language' => $detectedLanguage ?: $conversation->language,
            'last_message_at' => now(),
            'last_customer_message_at' => now(),
            'last_message_preview' => \Illuminate\Support\Str::limit($body ?: ucfirst($type), 80),
            'unread_count' => $conversation->unread_count + 1,
        ])->save();

        broadcast(new NewChatMessage($inbound))->toOthers();
        event(new ConversationListUpdated($conversation->fresh()));

        // A locked/human-handled conversation still logs the message and
        // notifies live — it just skips straight past the AI.
        if ($type === 'text' && $body) {
            $replyService->handleInboundMessage($conversation, $inbound);
        }
    }

    /** @return array{0: string, 1: ?string, 2: ?string} [type, body, mediaId] */
    private function extractContent(array $msg): array
    {
        $type = $msg['type'] ?? 'text';

        return match ($type) {
            'text' => ['text', $msg['text']['body'] ?? null, null],
            'image' => ['image', $msg['image']['caption'] ?? null, $msg['image']['id'] ?? null],
            'document' => ['document', $msg['document']['caption'] ?? null, $msg['document']['id'] ?? null],
            'audio' => ['audio', null, $msg['audio']['id'] ?? null],
            'video' => ['video', $msg['video']['caption'] ?? null, $msg['video']['id'] ?? null],
            'location' => ['location', json_encode($msg['location'] ?? []), null],
            default => [$type, null, null],
        };
    }

    /**
     * Fast, free, deterministic pre-check based on Unicode script ranges.
     * Catches pure Devanagari (Hindi) and Bengali script instantly without
     * an API call. Romanised Hindi/Bengali (Hinglish etc.) falls through
     * to null here — the AI's own language detection in ChatbotReplyService
     * handles that case using full sentence context.
     */
    private function quickScriptDetect(string $text): ?string
    {
        if (preg_match('/\p{Devanagari}/u', $text)) {
            return 'hi';
        }
        if (preg_match('/\p{Bengali}/u', $text)) {
            return 'bn';
        }
        if (preg_match('/[a-zA-Z]/', $text)) {
            return 'en';
        }

        return null;
    }
}
