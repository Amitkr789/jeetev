<?php

namespace App\Notifications;

use App\Models\ChatConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChatConversationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param string $type escalated|assigned|transferred */
    public function __construct(public ChatConversation $conversation, public string $type)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'type' => $this->type,
            'contact_name' => $this->conversation->contact_name,
            'wa_phone_number' => $this->conversation->wa_phone_number,
            'message' => $this->messageFor($this->type),
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    private function messageFor(string $type): string
    {
        $who = $this->conversation->contact_name ?: $this->conversation->wa_phone_number;

        return match ($type) {
            'escalated' => "Chat with {$who} needs a human — AI wasn't confident enough to answer.",
            'assigned' => "You were assigned the chat with {$who}.",
            'transferred' => "Chat with {$who} was transferred to you.",
            default => "Update on chat with {$who}.",
        };
    }
}
