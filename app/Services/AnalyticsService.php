<?php

namespace App\Services;

use App\Models\ChatQueryLog;
use Illuminate\Support\Collection;

/**
 * PHASE 4 — Analytics
 * ---------------------------------------------------------------
 * Har query ek row ban jaati hai chat_query_logs me (ChatbotReplyService
 * har turn ke end me log() call karta hai). Yeh service sirf write +
 * dashboard read helpers hai — koi business logic yahan nahi.
 */
class AnalyticsService
{
    public function log(array $attributes): ChatQueryLog
    {
        return ChatQueryLog::create($attributes);
    }

    public function topAskedQuestions(int $days = 30, int $limit = 20): Collection
    {
        return ChatQueryLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('LOWER(TRIM(question)) as question, COUNT(*) as total')
            ->groupBy('question')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /** Escalated conversations = questions the KB currently can't answer well. */
    public function topMissingQuestions(int $days = 30, int $limit = 20): Collection
    {
        return ChatQueryLog::query()
            ->where('escalated', true)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('LOWER(TRIM(question)) as question, COUNT(*) as total')
            ->groupBy('question')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /** Queries where hybrid search returned nothing above minScore at all. */
    public function topFailedSearches(int $days = 30, int $limit = 20): Collection
    {
        return ChatQueryLog::query()
            ->whereNull('kb_article_ids')
            ->orWhereJsonLength('kb_article_ids', 0)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('LOWER(TRIM(question)) as question, COUNT(*) as total')
            ->groupBy('question')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    public function topProducts(int $days = 30, int $limit = 20): Collection
    {
        return ChatQueryLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('product_ids')
            ->get(['product_ids'])
            ->flatMap(fn ($row) => $row->product_ids ?? [])
            ->countBy()
            ->sortDesc()
            ->take($limit);
    }

    public function mostViewedKb(int $days = 30, int $limit = 20): Collection
    {
        return ChatQueryLog::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('kb_article_ids')
            ->get(['kb_article_ids'])
            ->flatMap(fn ($row) => $row->kb_article_ids ?? [])
            ->countBy()
            ->sortDesc()
            ->take($limit);
    }

    public function summary(int $days = 30): array
    {
        $base = ChatQueryLog::where('created_at', '>=', now()->subDays($days));

        $total = (clone $base)->count();
        $escalated = (clone $base)->where('escalated', true)->count();
        $aiAnswered = (clone $base)->where('used_ai', true)->where('escalated', false)->count();
        $avgResponseMs = (clone $base)->avg('response_time_ms');

        return [
            'total_queries' => $total,
            'ai_answered' => $aiAnswered,
            'escalated' => $escalated,
            'escalation_rate' => $total > 0 ? round(($escalated / $total) * 100, 1) : 0.0,
            'avg_response_time_ms' => $avgResponseMs ? round($avgResponseMs) : null,
        ];
    }
}
