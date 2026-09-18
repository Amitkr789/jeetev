<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PHASE 4 — Analytics
 * ---------------------------------------------------------------
 * Har inbound customer message ka ek row: question, answer, score,
 * konsa KB article use hua, fallback hua ya nahi, language, intent,
 * aur processing time. Admin dashboard isi table se:
 *   - Top Asked Questions
 *   - Top Missing Questions   (escalated = true)
 *   - Top Failed Searches     (kb_article_ids empty)
 *   - Top Products
 *   - Most Viewed KB
 * banata hai (see AnalyticsService).
 */
class ChatQueryLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'question',
        'normalized_question',
        'answer',
        'final_score',
        'confidence',
        'confidence_tier',
        'kb_article_ids',
        'product_ids',
        'used_ai',
        'escalated',
        'language',
        'intent',
        'response_time_ms',
    ];

    protected $casts = [
        'kb_article_ids' => 'array',
        'product_ids' => 'array',
        'used_ai' => 'boolean',
        'escalated' => 'boolean',
        'final_score' => 'float',
        'confidence' => 'integer',
        'response_time_ms' => 'integer',
    ];
}
