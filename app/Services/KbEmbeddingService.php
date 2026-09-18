<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * PHASE 1 — Hybrid Search
 * ---------------------------------------------------------------
 * Pehle sirf embedding similarity use hoti thi. Problem: "catelog"
 * jaisa typo (normalize hone ke baad "catalog" ban jaata hai) bhi
 * kabhi kabhi embedding space me thoda door pad jaata hai aur score
 * threshold se neeche reh jaata hai.
 *
 * Fix: teen signals ko combine karo —
 *
 *   final_score = 0.70 * embedding_similarity
 *               + 0.20 * keyword_match_ratio
 *               + 0.10 * tag_match_ratio
 *
 * Keyword/tag match normalized query (QueryNormalizerService se aaya
 * hua, typo-corrected) ke against hota hai — is liye "catelog" bhi
 * "catalog" tag ko confidently hit karta hai.
 *
 * NOTE (data source, updated): `keywords` JSON column abhi tak admin UI
 * se kabhi populate nahi hota — agar future me koi form field isko bharne
 * lage to woh signal automatically use ho jayega (isNotEmpty check ke
 * through), warna title+question se hi keyword fallback milta hai.
 *
 * `search_tags` JSON column bhi UI se populate nahi hota — asli tag
 * signal admin ke tag-chip input se aata hai, jo `kb_article_tag` pivot
 * table (KbArticle::tags() relation) me store hota hai. Isliye tag score
 * seedhe usi relation se nikala jaata hai, kisi column-existence check
 * ke bina.
 */
class KbEmbeddingService
{
    private const WEIGHT_EMBEDDING = 0.70;
    private const WEIGHT_KEYWORD = 0.20;
    private const WEIGHT_TAG = 0.10;

    public function __construct(
        private OpenAiService $ai,
        private QueryNormalizerService $normalizer,
    ) {
    }

    /**
     * Recomputes and caches the embedding for a KB article if, and only
     * if, its AI-facing content actually changed since last time.
     */
    public function syncArticle(KbArticle $article): void
    {
        $eligible = $article->status === 'published' && $article->ai_ready;

        if (! $eligible) {
            if ($article->embedding) {
                $article->forceFill([
                    'embedding' => null,
                    'embedding_hash' => null,
                    'embedding_updated_at' => null,
                ])->saveQuietly();
            }

            return;
        }

        $text = $article->toAiContext();
        $hash = md5($text);

        if ($article->embedding_hash === $hash) {
            return;
        }

        $vector = $this->ai->embed($text);

        $article->forceFill([
            'embedding' => json_encode($vector),
            'embedding_hash' => $hash,
            'embedding_updated_at' => now(),
        ])->saveQuietly();
    }

    /**
     * Returns the top-K KB articles most relevant to $query, each paired
     * with a hybrid score (0..1) and a breakdown of the three signals
     * that produced it (useful for analytics + debugging).
     *
     * @return Collection<int, array{article: KbArticle, score: float, breakdown: array}>
     */
    public function topMatches(string $query, int $topK = 5, float $minScore = 0.45): Collection
    {
        $normalized = $this->normalizer->normalize($query);
        $normalizedQuery = $normalized['normalized'] !== '' ? $normalized['normalized'] : $query;
        $queryTokens = collect($normalized['tokens']);

        // Embedding call still uses the normalized text — typo-corrected
        // text embeds closer to the article it actually means.
        $queryVector = $this->ai->embed($normalizedQuery);

        $semantic = KbArticle::query()
            ->aiReady()
            ->whereNotNull('embedding')
            ->with('tags:id,name') // tag signal ka asli source — avoid N+1
            ->get()
            ->map(function (KbArticle $article) use ($queryVector, $queryTokens) {
                $embeddingScore = $this->cosineSimilarity(
                    $queryVector,
                    json_decode($article->embedding, true) ?: []
                );

                // `keywords` column abhi hamesha khali hota hai. Agar
                // kabhi bhara mile to wahi priority se use hoga, warna
                // title+question se hi keyword tokens nikal lo — taaki
                // empty column hone par bhi 20% weight zaya na jaaye.
                $keywordTokens = $this->articleKeywordTokens($article);
                $keywordScore = $keywordTokens->isNotEmpty()
                    ? $this->tokenOverlapRatio($queryTokens, $keywordTokens)
                    : $this->tokenOverlapRatio($queryTokens, $this->titleAndQuestionTokens($article));

                // Real tag signal — admin ke tag-chip input se yahi
                // populate hota hai (kb_article_tag pivot), `search_tags`
                // JSON column nahi — is liye seedhe relation se padho.
                $tagScore = $this->tokenOverlapRatio($queryTokens, $this->articleTagTokens($article));

                $finalScore = (self::WEIGHT_EMBEDDING * $embeddingScore)
                    + (self::WEIGHT_KEYWORD * $keywordScore)
                    + (self::WEIGHT_TAG * $tagScore);

                return [
                    'article' => $article,
                    'score' => round($finalScore, 4),
                    'breakdown' => [
                        'embedding' => round($embeddingScore, 4),
                        'keyword' => round($keywordScore, 4),
                        'tag' => round($tagScore, 4),
                    ],
                ];
            })
            ->filter(fn ($r) => $r['score'] >= $minScore)
            ->sortByDesc('score')
            ->values();

        if ($semantic->isNotEmpty()) {
            $topScore = $semantic->first()['score'];

            // Only keep articles close to the best match, so a single
            // strong hit doesn't get diluted by weak long-tail matches.
            $semantic = $semantic
                ->filter(fn ($row) => $row['score'] >= ($topScore - 0.08))
                ->values();
        }

        $forced = $this->matchByProductName($normalizedQuery)
            ->map(fn ($article) => [
                'article' => $article,
                'score' => 1.0,
                'breakdown' => ['embedding' => null, 'keyword' => null, 'tag' => null, 'forced_product_match' => true],
            ]);

        return $semantic
            ->merge($forced)
            ->unique(fn ($r) => $r['article']->id)
            ->sortByDesc('score')
            ->take($topK)
            ->values();
    }

    private function matchByProductName(string $query): Collection
    {
        $queryTokens = collect(preg_split('/\s+/', mb_strtolower($query)))->filter();

        return Product::all(['id', 'name'])
            ->filter(function ($p) use ($queryTokens) {
                $nameTokens = collect(preg_split('/\s+/', mb_strtolower($p->name)))
                    ->reject(fn ($t) => in_array($t, ['ion', 'the', 'of', 'a']));
                $overlap = $nameTokens->intersect($queryTokens)->count();

                return $nameTokens->count() > 0
                    && $overlap >= max(1, (int) ceil($nameTokens->count() / 2));
            })
            ->flatMap(fn ($p) => KbArticle::aiReady()->where('product_id', $p->id)->get())
            ->values();
    }

    /**
     * Naya helper: yeh hybrid search ka standalone entry point hai jo
     * ChatbotReplyService aur ProductDisambiguationService dono use
     * karte hain, taaki candidate products ek hi jagah se nikle.
     */
    public function distinctProductIdsFrom(Collection $matches): array
    {
        return $matches
            ->pluck('article.product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function tokenOverlapRatio(Collection $queryTokens, Collection $targetTokens): float
    {
        if ($queryTokens->isEmpty() || $targetTokens->isEmpty()) {
            return 0.0;
        }

        $overlap = $queryTokens->intersect($targetTokens)->count();

        // Ratio against the (smaller) query side — a short query that
        // fully matches a subset of a long keyword list should still
        // score close to 1.0, not get diluted by the target's length.
        return min(1.0, $overlap / $queryTokens->count());
    }

    private function articleKeywordTokens(KbArticle $article): Collection
    {
        $keywords = $article->keywords ?? [];
        $keywords = is_array($keywords) ? $keywords : (json_decode((string) $keywords, true) ?: []);

        return collect($keywords)->map(fn ($k) => mb_strtolower(trim((string) $k)))->filter();
    }

 private function articleTagTokens(KbArticle $article): Collection
{
    return $article->tags->pluck('name')
        ->flatMap(fn ($t) => preg_split('/\s+/u', mb_strtolower(trim((string) $t))))
        ->filter();
}

    private function titleTokens(KbArticle $article): Collection
    {
        return collect(preg_split('/\s+/u', mb_strtolower((string) $article->title)))->filter();
    }

    private function titleAndQuestionTokens(KbArticle $article): Collection
    {
        $text = trim($article->title . ' ' . $article->question);

        return collect(preg_split('/\s+/u', mb_strtolower($text)))->filter();
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $val) {
            $dot += $val * $b[$i];
            $normA += $val * $val;
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
