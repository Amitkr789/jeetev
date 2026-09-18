<?php

namespace App\Services;

/**
 * PHASE 1 — Backend Confidence
 * ---------------------------------------------------------------
 * Purani problem: GPT khud confidence=100 bolta tha aur sath me
 * sufficient_context=false bhi — dono contradictory. Yeh logically
 * galat hai kyunki GPT ko pata hi nahi hota retrieval kitna strong
 * tha.
 *
 * Naya rule: confidence SIRF backend nikalega, hybrid search ke
 * final_score se. GPT ka apna "confidence" field ab exist hi nahi
 * karta (Phase 2 prompt me hata diya gaya hai) — is se yeh poori
 * class of bug hamesha ke liye khatam ho jaati hai.
 *
 * Teen tiers, bilkul diagram jaisa:
 *   > 0.80        -> HIGH    -> GPT sirf wording karega, decision nahi
 *   0.55 - 0.80    -> MEDIUM  -> GPT ko limited discretion (answerable?)
 *   < 0.55        -> LOW     -> GPT ko call hi nahi karenge, seedha escalate
 */
class ConfidenceService
{
    public const TIER_HIGH = 'high';
    public const TIER_MEDIUM = 'medium';
    public const TIER_LOW = 'low';

    public function __construct(
        private float $highThreshold = 0.80,
        private float $mediumThreshold = 0.55,
    ) {
    }

    public function tierFor(float $finalScore): string
    {
        if ($finalScore > $this->highThreshold) {
            return self::TIER_HIGH;
        }

        if ($finalScore >= $this->mediumThreshold) {
            return self::TIER_MEDIUM;
        }

        return self::TIER_LOW;
    }

    /**
     * 0..1 score ko 0..100 customer/admin-facing confidence % me convert
     * karta hai. Yeh number saara UI/analytics/logging me dikhaya jaata hai
     * — kabhi GPT se nahi aata.
     */
    public function percentFor(float $finalScore): int
    {
        return (int) round(min(1.0, max(0.0, $finalScore)) * 100);
    }

    public function shouldCallAi(string $tier): bool
    {
        // LOW tier me GPT ko call karne ka koi fayda nahi — context hi
        // itna weak hai ki koi bhi answer hallucination hoga. Seedha
        // escalate karna hi sahi hai (Phase 2, point 9: Hallucination
        // Protection). Isse OpenAI cost bhi bachti hai.
        return $tier !== self::TIER_LOW;
    }

    public function shouldTrustAiWithoutSufficiencyCheck(string $tier): bool
    {
        // HIGH tier me hum GPT se "sufficient hai ya nahi" poochte hi nahi
        // — backend ne already decide kar liya hai retrieval strong hai.
        // GPT ka kaam sirf context ko customer-friendly wording dena hai.
        return $tier === self::TIER_HIGH;
    }
}
