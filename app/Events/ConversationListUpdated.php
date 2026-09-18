<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on a single shared admin-facing channel so every admin who
 * has the Live Chat inbox open sees new messages, escalations, takeovers,
 * and assignment changes update in real time without a page refresh.
 */
class ConversationListUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(public ChatConversation $conversation)
    {
    }

    /** @return Channel[] */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin-chat-list')];
    }

    public function broadcastAs(): string
    {
        return 'conversation-updated';
    }

    public function broadcastWith(): array
    {
        $c = $this->conversation;

        return [
            'id' => $c->id,
            'contact_name' => $c->contact_name,
            'wa_phone_number' => $c->wa_phone_number,
            'display_status' => $c->display_status,
            'is_locked' => $c->is_locked,
            'locked_by_admin_name' => $c->lockedByAdmin?->name,
            'handling_admin_name' => $c->handlingAdmin?->name,
            'assigned_admin_name' => $c->assignedAdmin?->name,
            'last_message_preview' => $c->last_message_preview,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'unread_count' => $c->unread_count,
            'escalated_at' => $c->escalated_at?->toIso8601String(),
        ];
    }
}