<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;

/**
 * PHASE 4 — Analytics dashboard
 * ---------------------------------------------------------------
 * Suggested routes (add to routes/web.php or routes/admin.php inside
 * your existing admin auth middleware group):
 *
 *   Route::get('/admin/chat-analytics', [ChatAnalyticsController::class, 'index']);
 *   Route::get('/admin/chat-analytics/data', [ChatAnalyticsController::class, 'data']);
 */
class ChatAnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics)
    {
    }

    public function index()
    {
        return view('pages.chat-analytics');
    }

    public function data(Request $request)
    {
        $days = (int) $request->integer('days', 30);

        return response()->json([
            'success' => true,
            'summary' => $this->analytics->summary($days),
            'top_asked_questions' => $this->analytics->topAskedQuestions($days),
            'top_missing_questions' => $this->analytics->topMissingQuestions($days),
            'top_failed_searches' => $this->analytics->topFailedSearches($days),
            'top_products' => $this->analytics->topProducts($days),
            'most_viewed_kb' => $this->analytics->mostViewedKb($days),
        ]);
    }
}
