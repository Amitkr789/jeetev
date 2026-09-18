<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotSetting extends Model
{
    protected $fillable = [
        'ai_globally_enabled',
        'openai_api_key',
        'openai_model',
        'openai_embedding_model',
        'confidence_threshold',
        'system_prompt',
        'fallback_message_en',
        'fallback_message_hi',
        'fallback_message_bn',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_access_token',
        'whatsapp_webhook_verify_token',
        'whatsapp_api_version',
    ];

    protected $casts = [
        'ai_globally_enabled' => 'boolean',
        'confidence_threshold' => 'integer',
        // Secrets are transparently encrypted at rest and decrypted on read.
        'openai_api_key' => 'encrypted',
        'whatsapp_access_token' => 'encrypted',
    ];

    protected $hidden = [
        'openai_api_key',
        'whatsapp_access_token',
        'whatsapp_webhook_verify_token',
    ];

    /**
     * There is only ever one row. Everything in the chatbot (services,
     * controllers, jobs) reads config through here instead of querying
     * the table directly.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'whatsapp_webhook_verify_token' => bin2hex(random_bytes(16)),
        ]);
    }
}