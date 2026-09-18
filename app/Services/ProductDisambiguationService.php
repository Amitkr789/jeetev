<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * PHASE 3 — Product Disambiguation
 * ---------------------------------------------------------------
 * Diagram se: "price" query aur 5 products match hue -> GPT nahi
 * bolega, poochega "Kis product ki price chahiye?"
 *
 * Yeh service KbEmbeddingService::topMatches() ke result se distinct
 * product_ids nikaal ke decide karta hai ki disambiguation chahiye
 * ya nahi, aur agar chahiye to ek WhatsApp-friendly numbered question
 * banata hai.
 */
class ProductDisambiguationService
{
    public function __construct(private KbEmbeddingService $kb)
    {
    }

    public function needsDisambiguation(Collection $matches): bool
    {
        return count($this->kb->distinctProductIdsFrom($matches)) > 1;
    }

    /**
     * @return array{question: string, candidate_ids: array<int,int>}
     */
    public function buildQuestion(Collection $matches, string $language = 'en'): array
    {
        $productIds = $this->kb->distinctProductIdsFrom($matches);
        $products = Product::query()->whereIn('id', $productIds)->get(['id', 'name']);

        $lines = $products->values()->map(
            fn (Product $p, int $i) => ($i + 1) . ') ' . $p->name
        )->implode("\n");

        $prompts = [
            'en' => "We have a few matching options — which one did you mean?\n{$lines}",
            'hi' => "Hamare paas kuch matching options hain — aapko kaunsa chahiye?\n{$lines}",
            'bn' => "আমাদের কাছে বেশ কয়েকটি অপশন আছে — আপনি কোনটি বলছেন?\n{$lines}",
        ];

        return [
            'question' => $prompts[$language] ?? $prompts['en'],
            'candidate_ids' => $products->pluck('id')->all(),
        ];
    }

    /**
     * Customer ke reply ("2", "60V wali", product name ka hissa) ko
     * pending candidate list ke against resolve karta hai.
     */
    public function resolveSelection(string $customerReply, array $candidateIds): ?Product
    {
        $products = Product::query()->whereIn('id', $candidateIds)->get(['id', 'name']);
        $reply = mb_strtolower(trim($customerReply));

        // 1) Numbered selection: "1", "2)", "option 2"
        if (preg_match('/(\d+)/', $reply, $m)) {
            $index = ((int) $m[1]) - 1;
            $ordered = $products->values();

            if ($ordered->has($index)) {
                return $ordered->get($index);
            }
        }

        // 2) Name-token overlap: "60v" matches "60V Lithium Battery"
        $replyTokens = collect(preg_split('/\s+/u', $reply))->filter();

        $best = $products
            ->map(function (Product $p) use ($replyTokens) {
                $nameTokens = collect(preg_split('/\s+/u', mb_strtolower($p->name)))->filter();

                return ['product' => $p, 'overlap' => $nameTokens->intersect($replyTokens)->count()];
            })
            ->sortByDesc('overlap')
            ->first();

        return ($best && $best['overlap'] > 0) ? $best['product'] : null;
    }
}
