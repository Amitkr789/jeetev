<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * PHASE 1 — Query Normalizer
 * ---------------------------------------------------------------
 * Customer kabhi sahi spelling nahi likhta ("catelog", "battry",
 * "waranty"). Is service ka kaam GPT tak pahunchne se PEHLE query
 * ko clean, correct aur language-tagged bana dena hai, taaki:
 *
 *  - Hybrid search (KbEmbeddingService) ko sahi keyword mile
 *  - GPT ko galat-spelled text na dikhe
 *  - Language detection consistent rahe (GPT ke response par depend
 *    nahi karna — kyunki wahi "confidence=100, sufficient_context=false"
 *    jaisi galtiyan create karta hai)
 *
 * Design: static dictionary (fast, deterministic, free) + fuzzy
 * fallback (levenshtein against a cached vocabulary) for typos jo
 * dictionary me nahi hain. Dictionary hamesha fuzzy se pehle try hoti
 * hai kyunki wo O(1) aur 100% predictable hai.
 *
 * UPDATE (stopword filtering): Hinglish filler words ("hai", "kya",
 * "chahiye", "ka", ...) pehle tokenize() se seedhe pass ho jaate the.
 * Ye words kisi bhi KB title/tag/keyword se kabhi match nahi karte —
 * lekin KbEmbeddingService::tokenOverlapRatio() ka ratio query token
 * COUNT ke against normalize hota hai. Matlab "catalog hai" (2 tokens,
 * 1 real match) ka score "catalog" (1 token, 1 match) se aadha aata
 * tha — sirf ek harmless filler word ki wajah se. Ab tokenize() hi
 * inhe strip kar deta hai, taaki sirf real content words denominator
 * banayein.
 */
class QueryNormalizerService
{
    /**
     * Known typo => correct spelling map. Yeh list production me
     * grow karti rahegi — jab bhi analytics me "top failed searches"
     * me koi naya typo pattern dikhe, yahan add kar do.
     */
    private const TYPO_MAP = [
        // catalog family
        'catelog' => 'catalog',
        'catalog' => 'catalog',
        'catalogue' => 'catalog',
        'catelogue' => 'catalog',
        'catelouge' => 'catalog',
        'katalog' => 'catalog',
        'brocher' => 'brochure',
        'brochur' => 'brochure',
        'broucher' => 'brochure',

        // battery family
        'battry' => 'battery',
        'baterry' => 'battery',
        'bettery' => 'battery',
        'batery' => 'battery',
        'lithum' => 'lithium',
        'lithiam' => 'lithium',
        'lithiyam' => 'lithium',

        // warranty family
        'waranty' => 'warranty',
        'warrenty' => 'warranty',
        'warrnty' => 'warranty',
        'gaurantee' => 'guarantee',
        'garanty' => 'guarantee',

        // misc frequent
        'delivary' => 'delivery',
        'deliverry' => 'delivery',
        'pymnt' => 'payment',
        'paymnt' => 'payment',
        'instalment' => 'installment',
        'emi' => 'emi',
        'stok' => 'stock',
        'stcok' => 'stock',
        'showrom' => 'showroom',
        'dealership' => 'dealership',
        'chargr' => 'charger',
        'chrger' => 'charger',
    ];

    /**
     * Hinglish/Hindi (Roman script) filler words + a handful of very
     * common short English function words. These never carry KB-matching
     * signal on their own — they only exist to hold a Hindi sentence
     * together — so they're dropped before keyword/tag scoring and
     * before typo-correction wastes cycles on them.
     *
     * Grow this list the same way as TYPO_MAP: when analytics shows a
     * query scoring low purely because of filler-word dilution, check
     * if a new filler word needs adding here.
     */
    private const STOPWORDS = [
        // Hinglish function / helper words
        'hai', 'hain', 'ho', 'hoga', 'hogi', 'the', 'tha', 'thi', 'tha',
        'ka', 'ki', 'ke', 'ko', 'se', 'me', 'mein', 'par', 'pe',
        'kya', 'kaise', 'kab', 'kahan', 'kaha', 'kaunsa', 'konsa',
        'kitna', 'kitne', 'kitni',
        'chahiye', 'chaiye', 'chahye',
        'bhi', 'hi', 'to', 'toh',
        'aap', 'aapka', 'aapke', 'aapki', 'apna', 'apne', 'apni',
        'mujhe', 'mujhko', 'hum', 'humein', 'hamare', 'hamara',
        'sir', 'bhai', 'bhaiya', 'ji', 'plz', 'please', 'pls', 'do',

        // Common short English function words
        'is', 'are', 'a', 'an', 'of', 'for', 'and', 'or', 'you', 'i',
    ];

    private const CACHE_KEY = 'query_normalizer.vocabulary';
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @return array{
     *   raw: string,
     *   normalized: string,
     *   language: string,
     *   tokens: array<int, string>,
     *   corrections: array<string, string>
     * }
     */
    public function normalize(string $rawQuery): array
    {
        $language = $this->detectLanguage($rawQuery);

        // Devanagari/Bengali script ko as-is rakhte hain — typo-correction
        // sirf Latin-script (English/Hinglish) tokens par chalta hai, kyunki
        // hamari dictionary Latin spellings ki hai.
        $tokens = $this->tokenize($rawQuery);

        $corrections = [];
        $normalizedTokens = [];

        foreach ($tokens as $token) {
            $lower = mb_strtolower($token);

            if (isset(self::TYPO_MAP[$lower])) {
                $fixed = self::TYPO_MAP[$lower];
            } else {
                $fixed = $this->fuzzyCorrect($lower);
            }

            if ($fixed !== $lower) {
                $corrections[$token] = $fixed;
            }

            $normalizedTokens[] = $fixed;
        }

        $normalizedQuery = trim(implode(' ', $normalizedTokens));

        // Agar poori query hi stopwords se bani thi ("hai kya"), khaali
        // string embed karne se behtar hai raw query bhej dena — downstream
        // (KbEmbeddingService) waise bhi is fallback ko already handle
        // karta hai, ye bas extra safety hai.
        if ($normalizedQuery === '') {
            $normalizedQuery = trim($rawQuery);
        }

        return [
            'raw' => $rawQuery,
            'normalized' => $normalizedQuery,
            'language' => $language,
            'tokens' => $normalizedTokens,
            'corrections' => $corrections,
        ];
    }

    /**
     * Bahut halka language detector — Unicode script ranges par based.
     * GPT se language detect karwana avoid karte hain taaki backend
     * hamesha deterministic rahe (Phase 2 ka core principle: GPT sirf
     * wording, decisions nahi).
     */
    public function detectLanguage(string $text): string
    {
        if (preg_match('/[\x{0980}-\x{09FF}]/u', $text)) {
            return 'bn'; // Bengali script
        }

        if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
            return 'hi'; // Devanagari script
        }

        // Hinglish heuristic: common Hindi words typed in Roman script.
        $hinglishMarkers = ['kya', 'hai', 'chahiye', 'kitna', 'kitne', 'kaise', 'ka', 'ki', 'ke', 'bhai', 'price kya'];
        $lower = mb_strtolower($text);

        foreach ($hinglishMarkers as $marker) {
            if (str_contains($lower, $marker)) {
                return 'hi';
            }
        }

        return 'en';
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        return collect(preg_split('/\s+/u', trim((string) $clean)))
            ->filter(fn ($t) => $t !== '')
            // Stopwords yahin strip karo — typo-correction aur scoring
            // dono ko sirf real content words dikhne chahiye.
            ->reject(fn ($t) => in_array(mb_strtolower($t), self::STOPWORDS, true))
            ->values()
            ->all();
    }

    /**
     * Dictionary me na mile to known vocabulary (product names + KB
     * keywords) ke against levenshtein distance check karte hain.
     * Sirf tab correct karte hain jab match bahut confident ho
     * (distance <= 2 aur word length >= 4), taaki galat-galat
     * "correction" na ho jaaye.
     */
    private function fuzzyCorrect(string $token): string
    {
        if (mb_strlen($token) < 4) {
            return $token;
        }

        $vocabulary = $this->vocabulary();

        if (isset($vocabulary[$token])) {
            return $token;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($vocabulary as $word => $_) {
            // Quick length-based skip — levenshtein har word ke against
            // chalana O(n) hai, is se 10k+ KB pe bhi normalizer fast rehta hai.
            if (abs(mb_strlen($word) - mb_strlen($token)) > 2) {
                continue;
            }

            $distance = levenshtein($token, $word);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $word;
            }
        }

        $maxAllowedDistance = mb_strlen($token) <= 5 ? 1 : 2;

        return ($best !== null && $bestDistance <= $maxAllowedDistance) ? $best : $token;
    }

    /**
     * Product names + KB titles/keywords se ek "known good words"
     * vocabulary banata hai — cached kyunki har message par DB hit
     * karna mehenga hai. KB ya product badalne par cache khud hi
     * TTL (1 hour) ke baad refresh ho jaata hai; agar turant chahiye
     * to Cache::forget('query_normalizer.vocabulary') call karo (jaise
     * KbArticle/Product save hook se).
     *
     * @return array<string, bool>
     */
    private function vocabulary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $words = [];

            foreach (Product::query()->pluck('name') as $name) {
                foreach (preg_split('/\s+/u', mb_strtolower((string) $name)) as $w) {
                    if (mb_strlen($w) >= 4) {
                        $words[$w] = true;
                    }
                }
            }

            if (class_exists(KbArticle::class)) {
                foreach (KbArticle::query()->aiReady()->pluck('title') as $title) {
                    foreach (preg_split('/\s+/u', mb_strtolower((string) $title)) as $w) {
                        if (mb_strlen($w) >= 4) {
                            $words[$w] = true;
                        }
                    }
                }
            }

            // Domain vocabulary jo dictionary me already correct spelling
            // ke roop me hai, taaki fuzzy match unhe bhi recognize kare.
            foreach (array_unique(array_values(self::TYPO_MAP)) as $w) {
                $words[$w] = true;
            }

            return $words;
        });
    }
}
