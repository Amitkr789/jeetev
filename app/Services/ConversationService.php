<?php

namespace App\Services;

use App\Models\ChatConversation;

/**
 * PHASE 3 — Conversation Memory
 * ---------------------------------------------------------------
 * Purana ConversationService sirf ek generic `state` + `state_data`
 * store karta tha (state-machine ke liye). Ab isme "focus memory"
 * bhi add ki hai: jab tak customer topic na badle, hum yaad rakhte
 * hain wo kis product/topic ki baat kar raha tha.
 *
 * Example flow:
 *   Customer: "Lithium battery"     -> focus_product_id = <lithium id>
 *   Bot:      "48V ya 60V?"
 *   Customer: "60V"                 -> focus_product_id updates ho jaata hai
 *   Customer: "price?"              -> query short hai, is liye
 *                                       ChatbotReplyService is memory ko
 *                                       "60V Lithium Battery price" me
 *                                       expand karta hai before search.
 *
 * NOTE (schema assumption): `chat_conversations` table par
 * `focus_product_id`, `focus_topic`, `pending_disambiguation` columns
 * chahiye. Migration:
 * 2026_07_11_000002_add_memory_fields_to_chat_conversations_table.php
 */
class ConversationService
{
    /** Follow-up queries jitne chhote hon usse kam, unhe "ambiguous
     *  without context" maana jaata hai aur memory se resolve kiya
     *  jaata hai (jaise "price?", "warranty?", "kitna hai"). */
    private const SHORT_QUERY_WORD_LIMIT = 3;

    public function setState(ChatConversation $conversation, string $state, array $data = []): void
    {
        $conversation->update([
            'state' => $state,
            'state_data' => $data,
        ]);
    }

    public function clear(ChatConversation $conversation): void
    {
        $conversation->update([
            'state' => null,
            'state_data' => [],
        ]);
    }

    public function state(ChatConversation $conversation): ?string
    {
        return $conversation->state;
    }

    /**
     * Customer ne jis product/topic ki baat ki, use "focus" me yaad
     * rakh lo. Har naya specific product-mention is memory ko overwrite
     * karta hai — customer ka sabse recent interest hamesha priority
     * leta hai.
     */
    public function rememberFocusProduct(ChatConversation $conversation, int $productId): void
    {
        $conversation->update(['focus_product_id' => $productId]);
    }

    public function focusProductId(ChatConversation $conversation): ?int
    {
        return $conversation->focus_product_id;
    }

    public function setPendingDisambiguation(ChatConversation $conversation, array $candidateProductIds): void
    {
        $conversation->update([
            'pending_disambiguation' => $candidateProductIds,
        ]);
    }

    public function pendingDisambiguation(ChatConversation $conversation): array
    {
        return $conversation->pending_disambiguation ?? [];
    }

    public function clearPendingDisambiguation(ChatConversation $conversation): void
    {
        $conversation->update(['pending_disambiguation' => []]);
    }

    /**
     * Query itna short/generic hai ki uska matlab focus product/topic
     * ke bina samajhna mushkil hai (e.g. "price?", "warranty kitni hai").
     */
    public function looksLikeFollowUp(string $normalizedQuery): bool
    {
        $wordCount = count(array_filter(explode(' ', trim($normalizedQuery))));

        return $wordCount > 0 && $wordCount <= self::SHORT_QUERY_WORD_LIMIT;
    }

    /**
     * Follow-up query ko remembered product name se expand karta hai,
     * taaki hybrid search ko poora context mile. Agar koi focus product
     * nahi hai, query untouched return hoti hai.
     */
    public function expandWithFocus(ChatConversation $conversation, string $normalizedQuery): string
    {
        $productId = $this->focusProductId($conversation);

        if (! $productId) {
            return $normalizedQuery;
        }

        $product = \App\Models\Product::find($productId);

        if (! $product) {
            return $normalizedQuery;
        }

        return trim($product->name . ' ' . $normalizedQuery);
    }
}
