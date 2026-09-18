<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.conversation.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string
    {
        return 'new-message';
    }

    public function broadcastWith(): array
    {
        $m = $this->message;

        return [
            'id' => $m->id,
            'conversation_id' => $m->conversation_id,
            'direction' => $m->direction,
            'sender_type' => $m->sender_type,
            'sender_admin_name' => $m->senderAdmin?->name,
            'type' => $m->type,
            'body' => $m->body,
            'media_url' => $m->media_url,
            'ai_confidence' => $m->ai_confidence,
            'language' => $m->language,
            'wa_status' => $m->wa_status,
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }
}