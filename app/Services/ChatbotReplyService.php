<?php

namespace App\Services;

use App\Events\ConversationListUpdated;
use App\Events\NewChatMessage;
use App\Models\Admin;
use App\Models\ChatbotSetting;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Notifications\ChatConversationNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * ==================================================================
 *  RAG Customer Support Orchestrator — Phases 1-4
 * ==================================================================
 *
 * PHASE 1  Query Normalizer + Hybrid Search + Backend Confidence
 * PHASE 2  Prompt Simplification — GPT sirf answer-wording karta hai
 * PHASE 3  Conversation Memory + Product Disambiguation
 * PHASE 4  Analytics + Auto Media + (perf notes inline)
 *
 * High-level flow (matches the architecture diagram):
 *
 *   inbound message
 *        │
 *        ▼
 *   AI on? ──no──► stop (human handles it)
 *        │yes
 *        ▼
 *   pending disambiguation? ──yes──► resolve or re-ask
 *        │no
 *        ▼
 *   legacy quick-state (asking_product/...)? ──yes──► state machine reply
 *        │no
 *        ▼
 *   canned intent (greeting/human/etc.)? ──yes──► canned reply
 *        │no
 *        ▼
 *   normalize query ─► expand w/ conversation memory if it's a follow-up
 *        │
 *        ▼
 *   hybrid search (embedding+keyword+tag)
 *        │
 *        ├─ no matches ───────────────► escalate (no AI call)
 *        ├─ multiple distinct products ► ask disambiguation (no AI call)
 *        ├─ score < 0.55 (LOW)  ───────► escalate (no AI call)
 *        ├─ score 0.55-0.80 (MEDIUM) ──► GPT answers, may flag "not answerable"
 *        └─ score > 0.80 (HIGH) ───────► GPT just phrases the answer
 *        │
 *        ▼
 *   send reply + auto-attach linked media + log analytics
 */
class ChatbotReplyService
{
    private const FALLBACK = [
        'en' => 'Our executive will contact you.',
        'hi' => 'हमारा प्रतिनिधि आपसे संपर्क करेगा।',
        'bn' => 'আমাদের প্রতিনিধি আপনার সাথে যোগাযোগ করবেন।',
    ];

    private const FOLLOWUP_PROMPT = [
        'en' => 'Anything else I can help you with?',
        'hi' => 'Aur kisi cheez me madad chahiye?',
        'bn' => 'আর কিছুতে সাহায্য করতে পারি?',
    ];

    private const HISTORY_WINDOW = 12;
    private const RETRIEVAL_TOP_K = 5;
    private const MIN_RELEVANCE_SCORE = 0.45;

    public function __construct(
        private OpenAiService $ai,
        private KbEmbeddingService $kb,
        private WhatsAppService $whatsapp,
        private ConversationService $conversation,
        private IntentService $intent,
        private QueryNormalizerService $normalizer,
        private ConfidenceService $confidence,
        private ProductDisambiguationService $disambiguation,
        private AnalyticsService $analytics,
    ) {
    }

    public function handleInboundMessage(ChatConversation $conversation, ChatMessage $inbound): void
    {
        $startedAt = microtime(true);
        $settings = ChatbotSetting::current();

        // AI globally off / off for this specific chat -> a human is
        // already handling it, we do nothing (checked FIRST so nothing
        // below — canned intents, state machine, RAG — can accidentally
        // fire while a human has taken over).
        if (! $settings->ai_globally_enabled || ! $conversation->ai_active) {
            event(new ConversationListUpdated($conversation->fresh()));
            return;
        }

        $rawBody = (string) ($inbound->body ?? '');
        $normalized = $this->normalizer->normalize($rawBody);
        $language = $normalized['language'];

        try {
            // 1) Was the customer replying to a disambiguation question
            //    we asked last turn? ("1", "60V wali", etc.)
            $pending = $this->conversation->pendingDisambiguation($conversation);

            if (! empty($pending)) {
                $this->handleDisambiguationReply($conversation, $inbound, $pending, $language, $startedAt);
                return;
            }

            // 2) Legacy quick-state slot-filling flow (kept for simple,
            //    scripted mini-flows like "battery -> lithium or lead-acid?").
            $state = $this->conversation->state($conversation);

            if ($state && $this->handleConversationState($conversation, $inbound, $state)) {
                return;
            }

            // 3) Canned intents (greeting/human/etc.) configured in ChatIntent.
            if ($this->handleCannedIntent($conversation, $rawBody)) {
                return;
            }

            // 4) Full RAG pipeline.
            $this->handleRagPipeline($conversation, $inbound, $normalized, $startedAt);
        } catch (Throwable $e) {
            Log::error('Chatbot pipeline crashed — escalating as a safety net', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $replyText = $settings->{"fallback_message_{$language}"} ?: self::FALLBACK[$language] ?? self::FALLBACK['en'];
            $this->finalizeReply($conversation, $replyText, $language, 0, [], escalate: true);
            $this->logQuery($conversation, $rawBody, $normalized, null, 0, 'low', [], [], false, true, $startedAt);
        }
    }

    /* ==================================================================
       PHASE 3 — Product disambiguation resolution
       ================================================================== */

    private function handleDisambiguationReply(
        ChatConversation $conversation,
        ChatMessage $inbound,
        array $pending,
        string $language,
        float $startedAt,
    ): void {
        $resolved = $this->disambiguation->resolveSelection((string) $inbound->body, $pending);

        if (! $resolved) {
            // Customer's reply didn't match any candidate — re-ask instead
            // of guessing. (We deliberately don't loop this forever in a
            // production build: track attempt count in state_data and
            // escalate after e.g. 2 failed tries.)
            $question = $this->disambiguation->buildQuestion(
                $this->productIdsToFakeMatches($pending),
                $language
            );

            $this->finalizeReply($conversation, $question['question'], $language, 0, [], escalate: false);
            return;
        }

        $this->conversation->clearPendingDisambiguation($conversation);
        $this->conversation->rememberFocusProduct($conversation, $resolved->id);

        // Now answer the ORIGINAL question the customer asked, but scoped
        // to the product they just picked.
        $originalQuery = $conversation->state_data['pending_question'] ?? $resolved->name;
        $scopedQuery = trim($resolved->name . ' ' . $originalQuery);

        $normalized = $this->normalizer->normalize($scopedQuery);
        $this->handleRagPipeline($conversation, $inbound, $normalized, $startedAt, forceProductId: $resolved->id);
    }

    /** Rebuilds a matches-shaped collection from raw product ids so we can reuse buildQuestion(). */
    private function productIdsToFakeMatches(array $productIds): \Illuminate\Support\Collection
    {
        return collect($productIds)->map(fn ($id) => [
            'article' => (object) ['product_id' => $id],
            'score' => 1.0,
        ]);
    }

    /* ==================================================================
       Legacy quick-state slot filling (kept from the original build)
       ================================================================== */

    private function handleConversationState(ChatConversation $conversation, ChatMessage $inbound, string $state): bool
    {
        $msg = mb_strtolower((string) $inbound->body);

        switch ($state) {
            case 'asking_product':
                if (str_contains($msg, 'battery')) {
                    $this->conversation->setState($conversation, 'asking_battery_type');
                    $this->finalizeReply($conversation, 'Lithium Battery chahiye ya Lead Acid Battery?', 'en', 100, [], false);
                    return true;
                }

                if (str_contains($msg, 'scooter')) {
                    $this->conversation->clear($conversation);
                    $this->finalizeReply($conversation, 'Hamare paas multiple Electric Scooters available hain. Aapka budget kitna hai?', 'en', 100, [], false);
                    return true;
                }

                break;

            case 'asking_battery_type':
                if (str_contains($msg, 'lithium')) {
                    $this->conversation->clear($conversation);
                    $this->finalizeReply($conversation, 'Great 😊 48V, 60V aur 72V Lithium Batteries available hain. Aapko kis vehicle ke liye chahiye?', 'en', 100, [], false);
                    return true;
                }

                break;
        }

        // State set tha lekin is turn me match nahi hua — state ko chhod
        // do aur RAG pipeline ko normal turn ki tarah handle karne do.
        return false;
    }

    /* ==================================================================
       Canned intents (ChatIntent-backed keyword replies)
       ================================================================== */

    private function handleCannedIntent(ChatConversation $conversation, string $rawBody): bool
    {
        $intent = $this->intent->detect($rawBody);

        if (! $intent) {
            return false;
        }

        $intentRow = \App\Models\ChatIntent::where('intent', $intent)->first();

        if (! $intentRow || empty($intentRow->responses)) {
            return false;
        }

        $responses = $intentRow->responses;
        $reply = $responses[array_rand($responses)];

        if ($intent === 'greeting') {
            $this->conversation->setState($conversation, 'asking_product');
        }

        $this->finalizeReply($conversation, $reply, 'en', 100, [], false);

        return true;
    }

    /* ==================================================================
       PHASE 1/2/3 — Core RAG pipeline
       ================================================================== */

    private function handleRagPipeline(
        ChatConversation $conversation,
        ChatMessage $inbound,
        array $normalized,
        float $startedAt,
        ?int $forceProductId = null,
    ): void {
        $settings = ChatbotSetting::current();
        $language = $normalized['language'];
        $searchQuery = $normalized['normalized'];

        // Follow-up expansion: "price?" -> "60V Lithium Battery price"
        // using whatever product the conversation last focused on.
        if (! $forceProductId && $this->conversation->looksLikeFollowUp($searchQuery)) {
            $searchQuery = $this->conversation->expandWithFocus($conversation, $searchQuery);
        }

        $matches = $this->kb->topMatches($searchQuery, self::RETRIEVAL_TOP_K, self::MIN_RELEVANCE_SCORE);

        Log::info('KB HYBRID MATCH', [
            'question' => $inbound->body,
            'search_query' => $searchQuery,
            'count' => $matches->count(),
            'titles' => $matches->pluck('article.title')->toArray(),
            'scores' => $matches->pluck('score')->toArray(),
        ]);

        // No relevant KB article at all -> escalate without ever calling GPT.
        if ($matches->isEmpty()) {
            $replyText = $settings->{"fallback_message_{$language}"} ?: self::FALLBACK[$language] ?? self::FALLBACK['en'];
            $this->finalizeReply($conversation, $replyText, $language, 0, [], escalate: true);
            $this->logQuery($conversation, $inbound->body, $normalized, null, 0, 'low', [], [], false, true, $startedAt);
            return;
        }

        // PHASE 3 — Multiple distinct products matched a generic query
        // ("price", "warranty") -> ask instead of guessing.
        if (! $forceProductId && $this->disambiguation->needsDisambiguation($matches)) {
            $question = $this->disambiguation->buildQuestion($matches, $language);

            $this->conversation->setState($conversation, $this->conversation->state($conversation) ?? 'awaiting_disambiguation', [
                'pending_question' => $inbound->body,
            ]);
            $this->conversation->setPendingDisambiguation($conversation, $question['candidate_ids']);

            $this->finalizeReply($conversation, $question['question'], $language, 0, [], escalate: false);
            $this->logQuery($conversation, $inbound->body, $normalized, null, 0, 'medium', $matches->pluck('article.id')->all(), $question['candidate_ids'], false, false, $startedAt);
            return;
        }

        $topScore = (float) $matches->first()['score'];
        $tier = $this->confidence->tierFor($topScore);
        $confidencePercent = $this->confidence->percentFor($topScore);
        $usedArticleIds = $matches->pluck('article.id')->values()->all();

        // PHASE 1, point 9 — LOW tier: don't even call the LLM. Weak
        // retrieval + an LLM call is exactly how hallucinated answers
        // happen, and it costs OpenAI tokens for nothing.
        if (! $this->confidence->shouldCallAi($tier)) {
            $replyText = $settings->{"fallback_message_{$language}"} ?: self::FALLBACK[$language] ?? self::FALLBACK['en'];
            $this->finalizeReply($conversation, $replyText, $language, $confidencePercent, $usedArticleIds, escalate: true);
            $this->logQuery($conversation, $inbound->body, $normalized, null, $topScore, $tier, $usedArticleIds, [], false, true, $startedAt);
            return;
        }

        $context = $matches->map(fn (array $m) => $m['article']->toAiContext())->implode("\n\n---\n\n");

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->buildSystemPrompt($settings, $context, $tier, $language)]],
            $this->recentHistory($conversation)
        );

        try {
            $result = $this->ai->chatJson($messages);
        } catch (Throwable $e) {
            Log::error('Chatbot AI request failed', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);
            $result = [];
        }

        $modelAnswer = trim((string) ($result['answer'] ?? ''));
        $resultLanguage = in_array($result['language'] ?? null, ['en', 'hi', 'bn'], true) ? $result['language'] : $language;

        // Safety net kept from the original build: if the model parrots
        // back its own previous reply verbatim on a NEW question, that's
        // a strong sign of history-anchoring rather than a real answer.
        $lastAiReply = $conversation->messages()->where('sender_type', 'ai')->orderByDesc('id')->value('body');

        if ($modelAnswer !== '' && $lastAiReply !== null && trim($lastAiReply) === $modelAnswer) {
            Log::warning('Blocked suspected history-anchored duplicate reply', [
                'conversation_id' => $conversation->id,
                'question' => $inbound->body,
            ]);
            $modelAnswer = '';
        }

        // PHASE 2 — In MEDIUM tier only, GPT is allowed a single narrow
        // decision: "does this context actually answer THIS question?"
        // HIGH tier skips this entirely — backend already decided the
        // retrieval is strong enough, GPT's only job was wording.
        $answerable = $tier === ConfidenceService::TIER_HIGH
            ? true
            : (bool) ($result['answerable'] ?? false);

        $shouldEscalate = $modelAnswer === '' || ! $answerable;

        $replyText = $shouldEscalate
            ? ($settings->{"fallback_message_{$resultLanguage}"} ?: self::FALLBACK[$resultLanguage] ?? self::FALLBACK['en'])
            : $modelAnswer;

        // Remember focus product for future short follow-ups, if every
        // matched article maps to a single product.
        $productIds = $matches->pluck('article.product_id')->filter()->unique()->values();

        if ($productIds->count() === 1) {
            $this->conversation->rememberFocusProduct($conversation, $productIds->first());
        }

        $autoMedia = $shouldEscalate ? collect() : $this->collectAutoMedia($matches);

        $this->finalizeReply($conversation, $replyText, $resultLanguage, $confidencePercent, $usedArticleIds, $shouldEscalate, $autoMedia);
        $this->logQuery(
            $conversation,
            $inbound->body,
            $normalized,
            $modelAnswer ?: null,
            $topScore,
            $tier,
            $usedArticleIds,
            $productIds->all(),
            true,
            $shouldEscalate,
            $startedAt
        );
    }

    /* ==================================================================
       PHASE 2 — Simplified system prompt
       ------------------------------------------------------------------
       GPT's job shrinks to almost nothing: given CONFIRMED context
       (backend already decided it's relevant), write a natural reply.
       In MEDIUM tier only, it may also say "this doesn't actually answer
       the question" via `answerable`. It never invents facts, never
       decides retrieval quality, and never returns its own confidence
       number — all of that is backend-owned now (Phase 1).
       ================================================================== */

    private function buildSystemPrompt(ChatbotSetting $settings, string $context, string $tier, string $language): string
    {
        $brandInstructions = trim((string) $settings->system_prompt);

        $languageNames = ['en' => 'English', 'hi' => 'Hindi', 'bn' => 'Bengali'];
        $languageName = $languageNames[$language] ?? 'English';

        $schemaNote = $tier === ConfidenceService::TIER_HIGH
            ? '{"language":"en|hi|bn","answer":"..."}'
            : '{"language":"en|hi|bn","answer":"...","answerable":true|false}';

        $answerableRule = $tier === ConfidenceService::TIER_HIGH
            ? ''
            : "\nIf the CONTEXT below does not actually answer the customer's latest question, set \"answerable\":false and leave \"answer\" empty. Do not guess.\n";

        return <<<PROMPT
You are a WhatsApp customer-support assistant. Reply in {$languageName}, matching the customer's script.

{$brandInstructions}

RULES
1. Use ONLY the CONTEXT below as your source of facts. Never use outside knowledge, never invent numbers, specs, or policies.
2. Conversation history is only for understanding follow-ups (like "price?" meaning the product just discussed) — never treat a previous assistant reply as a fact source.
3. Keep the reply short, friendly, and conversational — do not summarize the whole article, just answer what was asked.
4. Do not mention "context", "knowledge base", or that you are an AI.
{$answerableRule}
Return ONLY valid JSON, no markdown fences, no preamble.
Format: {$schemaNote}

CONTEXT
{$context}
PROMPT;
    }

    /* ==================================================================
       PHASE 4 — Auto media (generalized: any media linked to a used
       article gets attached, not just items tagged "catalog")
       ================================================================== */

    private function collectAutoMedia(\Illuminate\Support\Collection $matches): \Illuminate\Support\Collection
    {
        return $matches
            ->flatMap(fn (array $m) => $m['article']->media ?? collect())
            ->filter(fn ($media) => (bool) ($media->auto_send ?? true))
            ->unique('id')
            ->values();
    }

    /* ==================================================================
       Shared reply plumbing (send + persist + broadcast + escalate)
       ================================================================== */

    private function finalizeReply(
        ChatConversation $conversation,
        string $replyText,
        string $language,
        int $confidence,
        array $kbArticleIds,
        bool $escalate,
        ?\Illuminate\Support\Collection $media = null,
    ): void {
        $waMessageId = null;

        try {
            $waMessageId = $this->whatsapp->sendText($conversation->wa_phone_number, $replyText);
        } catch (Throwable $e) {
            Log::error('WhatsApp send failed during AI reply', ['error' => $e->getMessage()]);
        }

        if ($media && $media->isNotEmpty() && ! $escalate) {
            foreach ($media as $item) {
                try {
                    $this->whatsapp->sendMedia(
                        $conversation->wa_phone_number,
                        $this->waMediaTypeFor($item->mime_type ?? 'application/pdf'),
                        storage_path('app/public/' . $item->file_path),
                        $item->mime_type ?? 'application/pdf',
                        $item->file_name ?? null
                    );
                } catch (Throwable $e) {
                    Log::error('Auto media send failed', ['error' => $e->getMessage()]);
                }
            }

            // A short closer after any media dump, so the chat doesn't
            // just trail off after a PDF/image with no text.
            try {
                $this->whatsapp->sendText(
                    $conversation->wa_phone_number,
                    self::FOLLOWUP_PROMPT[$language] ?? self::FOLLOWUP_PROMPT['en']
                );
            } catch (Throwable $e) {
                Log::error('Follow-up prompt send failed', ['error' => $e->getMessage()]);
            }
        }

        $outbound = $conversation->messages()->create([
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'type' => 'text',
            'body' => $replyText,
            'wa_message_id' => $waMessageId,
            'wa_status' => $waMessageId ? 'sent' : 'failed',
            'ai_confidence' => $confidence,
            'kb_article_ids' => $kbArticleIds,
            'language' => $language,
        ]);

        $conversation->forceFill([
            'language' => $language,
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($replyText, 80),
        ]);

        if ($escalate) {
            $conversation->forceFill(['ai_active' => false, 'escalated_at' => now()]);
        }

        $conversation->save();

        broadcast(new NewChatMessage($outbound))->toOthers();
        event(new ConversationListUpdated($conversation->fresh()));

        if ($escalate) {
            $this->notifyEscalation($conversation);
        }
    }

    private function waMediaTypeFor(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }

    private function recentHistory(ChatConversation $conversation): array
    {
        return $conversation->messages()
            ->where(function ($q) {
                $q->where('sender_type', 'customer')
                    ->orWhere(function ($q2) {
                        // Only feed back AI replies that weren't escalation
                        // fallbacks — those are generic and shouldn't set a
                        // precedent for how future unrelated questions get answered.
                        $q2->where('sender_type', 'ai')->where('ai_confidence', '>=', 60);
                    });
            })
            ->orderByDesc('id')
            ->limit(self::HISTORY_WINDOW)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->sender_type === 'customer' ? 'user' : 'assistant',
                'content' => (string) $m->body,
            ])
            ->values()
            ->all();
    }

    /* ==================================================================
       PHASE 4 — Analytics logging
       ================================================================== */

    private function logQuery(
        ChatConversation $conversation,
        string $question,
        array $normalized,
        ?string $answer,
        ?float $finalScore,
        string $tier,
        array $kbArticleIds,
        array $productIds,
        bool $usedAi,
        bool $escalated,
        float $startedAt,
    ): void {
        try {
            $this->analytics->log([
                'conversation_id' => $conversation->id,
                'question' => $question,
                'normalized_question' => $normalized['normalized'] ?? null,
                'answer' => $answer,
                'final_score' => $finalScore,
                'confidence' => $finalScore !== null ? $this->confidence->percentFor($finalScore) : 0,
                'confidence_tier' => $tier,
                'kb_article_ids' => $kbArticleIds,
                'product_ids' => $productIds,
                'used_ai' => $usedAi,
                'escalated' => $escalated,
                'language' => $normalized['language'] ?? null,
                'intent' => $this->intent->detect($question),
                'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $e) {
            // Analytics must never break the customer-facing reply path.
            Log::error('Analytics logging failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyEscalation(ChatConversation $conversation): void
    {
        try {
            $recipients = $conversation->assigned_admin_id
                ? Admin::query()->where('id', $conversation->assigned_admin_id)->get()
                : Admin::query()->where('role', 'super_admin')->get();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ChatConversationNotification($conversation, 'escalated'));
            }
        } catch (Throwable $e) {
            Log::error('Escalation notification failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
