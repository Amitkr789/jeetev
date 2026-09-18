<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
  protected $fillable = [
    'conversation_id', 'direction', 'sender_type', 'sender_admin_id', 'type', 'body',
    'media_url', 'media_mime_type', 'media_filename', 'media_size',
    'wa_message_id', 'wa_status', 'ai_confidence', 'kb_article_ids', 'language',
];

    protected $casts = [
    'kb_article_ids' => 'array',
    'ai_confidence' => 'integer',
    'media_size' => 'integer',
];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function senderAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_admin_id');
    }
}
