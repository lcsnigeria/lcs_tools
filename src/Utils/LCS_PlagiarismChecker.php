<?php
namespace LCSNG\Tools\Utils;

/**
 * LCS_PlagiarismChecker
 *
 * A powerful, multi-algorithm plagiarism detection class for in-system
 * duplicate content checking. Combines five complementary similarity
 * algorithms into a single weighted composite score.
 *
 * Algorithms used:
 *   1. Cosine TF-IDF     — bag-of-words frequency vector match (weight: 35%)
 *   2. Jaccard Index     — unique token set overlap (weight: 25%)
 *   3. Word Frequency    — verbatim word-level overlap (weight: 20%)
 *   4. N-Gram Overlap    — phrase/bigram matching, catches reordering (weight: 12%)
 *   5. Levenshtein       — character-level structural similarity (weight: 8%)
 *
 * Requirements:
 *   composer require yooper/php-text-analysis
 *   composer require edgaras/strsim
 *
 * @author  LCSNG
 * @version 1.0.0
 */

use TextAnalysis\Comparisons\CosineSimilarityComparison;
use Edgaras\StrSim\Jaccard;

class LCS_PlagiarismChecker
{
    // -------------------------------------------------------------------------
    // Constants: Verdict thresholds
    // -------------------------------------------------------------------------

    const VERDICT_BLOCKED    = 'blocked';    // >= 90% — near-identical / copy-paste
    const VERDICT_FLAGGED    = 'flagged';    // >= 70% — heavily similar, needs review
    const VERDICT_WARNING    = 'warning';    // >= 50% — moderately similar, soft alert
    const VERDICT_CLEAN      = 'clean';      // <  50% — sufficiently original

    // -------------------------------------------------------------------------
    // Algorithm weight distribution (must sum to 1.0)
    // -------------------------------------------------------------------------

    private float $weightCosine      = 0.35;
    private float $weightJaccard     = 0.25;
    private float $weightWordFreq    = 0.20;
    private float $weightNgram       = 0.12;
    private float $weightLevenshtein = 0.08;

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /** N-gram window size (2 = bigrams, 3 = trigrams). */
    private int $ngramSize = 2;

    /** Levenshtein chunk size — keeps each chunk within PHP's 255-char limit. */
    private int $levenshteinChunkSize = 200;

    /** Minimum character length for a meaningful check. */
    private int $minLength = 20;

    /** Custom stopwords to merge with the library defaults. */
    private array $customStopWords = [];

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param array $options Optional configuration overrides:
     *   - weights        array   Override default algorithm weights (must sum to 1.0).
     *   - ngram_size     int     Bigram (2) or trigram (3). Default: 2.
     *   - chunk_size     int     Levenshtein chunk size. Default: 200.
     *   - min_length     int     Minimum text length to check. Default: 20.
     *   - stop_words     array   Additional stopwords to filter out.
     */
    public function __construct(array $options = [])
    {
        if (!empty($options['weights'])) {
            $this->setWeights($options['weights']);
        }
        if (isset($options['ngram_size']))  $this->ngramSize             = (int) $options['ngram_size'];
        if (isset($options['chunk_size']))  $this->levenshteinChunkSize  = (int) $options['chunk_size'];
        if (isset($options['min_length']))  $this->minLength             = (int) $options['min_length'];
        if (!empty($options['stop_words'])) $this->customStopWords       = (array) $options['stop_words'];
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Run a full plagiarism check between two pieces of content.
     *
     * @param  string $mainContent    Existing / reference content.
     * @param  string $checkContent   New content submitted by a user.
     * @return array {
     *   score:     float   — composite 0–100 (100 = identical)
     *   verdict:   string  — 'clean' | 'warning' | 'flagged' | 'blocked'
     *   breakdown: array   — per-algorithm scores
     *   is_plagiarised: bool
     * }
     */
    public function check(string $mainContent, string $checkContent): array
    {
        $main  = $this->normalizeText($mainContent);
        $check = $this->normalizeText($checkContent);

        // Guard: too short to analyse meaningfully
        if (mb_strlen($main) < $this->minLength || mb_strlen($check) < $this->minLength) {
            return $this->buildResult(0.0, []);
        }

        $breakdown = [
            'cosine_tfidf'   => $this->cosineSimilarity($main, $check),
            'jaccard_index'  => $this->jaccardSimilarity($main, $check),
            'word_frequency' => $this->contentPercentageDifference($main, $check),
            'ngram_overlap'  => $this->ngramSimilarity($main, $check),
            'levenshtein'    => $this->levenshteinSimilarity($main, $check),
        ];

        $composite = ($breakdown['cosine_tfidf']   * $this->weightCosine)
                   + ($breakdown['jaccard_index']  * $this->weightJaccard)
                   + ($breakdown['word_frequency'] * $this->weightWordFreq)
                   + ($breakdown['ngram_overlap']  * $this->weightNgram)
                   + ($breakdown['levenshtein']    * $this->weightLevenshtein);

        return $this->buildResult(round($composite, 2), $breakdown);
    }

    /**
     * Check a single piece of new content against an array of existing documents.
     * Returns the highest-scoring match plus all individual results.
     *
     * @param  string   $checkContent   Content to verify.
     * @param  string[] $corpus         Array of existing content strings.
     * @return array {
     *   highest_match:  array   — result against the most similar document
     *   corpus_index:   int     — index of the most similar document in $corpus
     *   all_results:    array[] — full result for every corpus document
     * }
     */
    public function checkAgainstCorpus(string $checkContent, array $corpus): array
    {
        $allResults  = [];
        $topScore    = -1;
        $topIndex    = 0;

        foreach ($corpus as $idx => $existing) {
            $result = $this->check($existing, $checkContent);
            $allResults[$idx] = $result;

            if ($result['score'] > $topScore) {
                $topScore = $result['score'];
                $topIndex = $idx;
            }
        }

        return [
            'highest_match' => $allResults[$topIndex] ?? $this->buildResult(0.0, []),
            'corpus_index'  => $topIndex,
            'all_results'   => $allResults,
        ];
    }

    /**
     * Convenience wrapper: returns true if the composite score meets or exceeds
     * the given threshold (default: 70%).
     */
    public function isPlagiarised(string $mainContent, string $checkContent, float $threshold = 70.0): bool
    {
        return $this->check($mainContent, $checkContent)['score'] >= $threshold;
    }

    /**
     * Override default algorithm weights at runtime.
     * All values must be floats and the array must sum to 1.0 (±0.001 tolerance).
     *
     * @param array{cosine?:float, jaccard?:float, word_freq?:float, ngram?:float, levenshtein?:float} $weights
     */
    public function setWeights(array $weights): void
    {
        $map = [
            'cosine'      => 'weightCosine',
            'jaccard'     => 'weightJaccard',
            'word_freq'   => 'weightWordFreq',
            'ngram'       => 'weightNgram',
            'levenshtein' => 'weightLevenshtein',
        ];

        foreach ($map as $key => $property) {
            if (isset($weights[$key])) {
                $this->{$property} = (float) $weights[$key];
            }
        }

        $sum = $this->weightCosine + $this->weightJaccard + $this->weightWordFreq
             + $this->weightNgram + $this->weightLevenshtein;

        if (abs($sum - 1.0) > 0.001) {
            throw new \InvalidArgumentException(
                sprintf('Algorithm weights must sum to 1.0; got %.4f.', $sum)
            );
        }
    }

    // =========================================================================
    // ALGORITHM IMPLEMENTATIONS
    // =========================================================================

    /**
     * Algorithm 1 — Cosine TF-IDF Similarity  (weight: 35%)
     *
     * Converts both texts into term-frequency vectors [word => count],
     * strips stopwords, then computes the cosine of the angle between vectors.
     * Catches vocabulary reuse regardless of word order or paragraph shuffling.
     *
     * Returns 0–100.
     */
    public function cosineSimilarity(string $text1, string $text2): float
    {
        $v1 = $this->buildTermVector($text1);
        $v2 = $this->buildTermVector($text2);

        if (empty($v1) || empty($v2)) {
            return 0.0;
        }

        return round((new CosineSimilarityComparison())->similarity($v1, $v2) * 100, 4);
    }

    /**
     * Algorithm 2 — Jaccard Index  (weight: 25%)
     *
     * Measures the overlap of unique token sets: |A ∩ B| / |A ∪ B|.
     * Immune to word repetition tricks — only unique vocabulary matters.
     * Complements Cosine by catching raw keyword matching in short texts.
     *
     * Returns 0–100.
     */
    public function jaccardSimilarity(string $text1, string $text2): float
    {
        $clean1 = $this->normalizeText($text1);
        $clean2 = $this->normalizeText($text2);

        if ($clean1 === '' || $clean2 === '') {
            return 0.0;
        }

        return round(Jaccard::index($clean1, $clean2) * 100, 4);
    }

    /**
     * Algorithm 3 — Word Frequency Overlap  (weight: 20%)
     *
     * Frequency-aware intersection: counts how many words from $checkContent
     * appear in $mainContent, respecting repetition counts in both texts.
     * Returns the match as a percentage of $checkContent's word count.
     *
     * Returns 0–100.
     */
    public function contentPercentageDifference(string $mainContent, string $checkContent): float
    {
        $main  = $this->normalizeText($mainContent);
        $check = $this->normalizeText($checkContent);

        if ($main === '' || $check === '') {
            return 0.0;
        }

        $mainWords  = $this->splitWords($main);
        $checkWords = $this->splitWords($check);

        $checkCount = count($checkWords);
        if ($checkCount === 0) {
            return 0.0;
        }

        $mainFreq   = array_count_values($mainWords);
        $matchCount = 0;

        foreach (array_count_values($checkWords) as $word => $freq) {
            if (isset($mainFreq[$word])) {
                $matchCount += min($freq, $mainFreq[$word]);
            }
        }

        return round(($matchCount / $checkCount) * 100, 4);
    }

    /**
     * Algorithm 4 — N-Gram Overlap  (weight: 12%)
     *
     * Splits both texts into consecutive n-word phrases (bigrams by default),
     * then computes a Jaccard-style overlap on those phrase sets.
     * Catches phrase-level copying and sentence reordering that single-word
     * methods miss. The most effective defence against synonym-swap tricks.
     *
     * Returns 0–100.
     */
    public function ngramSimilarity(string $text1, string $text2): float
    {
        $ngrams1 = $this->buildNgrams($text1, $this->ngramSize);
        $ngrams2 = $this->buildNgrams($text2, $this->ngramSize);

        if (empty($ngrams1) || empty($ngrams2)) {
            return 0.0;
        }

        $set1        = array_unique($ngrams1);
        $set2        = array_unique($ngrams2);
        $intersection = array_intersect($set1, $set2);
        $union        = array_unique(array_merge($set1, $set2));

        if (empty($union)) {
            return 0.0;
        }

        return round((count($intersection) / count($union)) * 100, 4);
    }

    /**
     * Algorithm 5 — Chunked Levenshtein Structural Similarity  (weight: 8%)
     *
     * Measures character-level edit distance between the two texts.
     * Catches light punctuation swaps, minor rephrasing, and character edits.
     * Chunked to stay within PHP's native levenshtein() 255-char cap and
     * avoid the O(N³) CPU blowup of similar_text() on long content.
     *
     * Returns 0–100.
     */
    public function levenshteinSimilarity(string $text1, string $text2): float
    {
        if ($text1 === $text2) {
            return 100.0;
        }

        $chunksA = str_split($text1, $this->levenshteinChunkSize);
        $chunksB = str_split($text2, $this->levenshteinChunkSize);
        $count   = max(count($chunksA), count($chunksB));

        if ($count === 0) {
            return 0.0;
        }

        $total = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a      = $chunksA[$i] ?? '';
            $b      = $chunksB[$i] ?? '';
            $maxLen = max(strlen($a), strlen($b));

            if ($maxLen === 0) {
                $total += 100.0;
                continue;
            }

            $total += (1 - (levenshtein($a, $b) / $maxLen)) * 100;
        }

        return round($total / $count, 4);
    }

    /**
     * String-level character similarity (legacy helper).
     * Uses frequency-aware character intersection rather than array_intersect.
     * Returns percentage of characters in $checkString found in $mainString.
     */
    public function stringPercentageDifference(string $mainString, string $checkString): float
    {
        $main  = mb_strtolower(trim($mainString));
        $check = mb_strtolower(trim($checkString));

        if ($main === '' || $check === '') {
            return 0.0;
        }

        $checkLen = mb_strlen($check);
        $mainFreq = array_count_values(mb_str_split($main));
        $matches  = 0;

        foreach (array_count_values(mb_str_split($check)) as $char => $freq) {
            if (isset($mainFreq[$char])) {
                $matches += min($freq, $mainFreq[$char]);
            }
        }

        return round(($matches / $checkLen) * 100, 4);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Strips HTML, collapses whitespace, lowercases.
     * Adds a space after strip_tags to prevent touching tags from merging words.
     */
    private function normalizeText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        // Insert space between adjacent tags before stripping
        $text = preg_replace('/<\/[^>]+>/', '$0 ', $text);
        $text = strip_tags($text);
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/u', ' ', $text);   // strip punctuation
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Splits normalised text into words, filtering out empty tokens.
     *
     * @return string[]
     */
    private function splitWords(string $normalizedText): array
    {
        return array_values(
            array_filter(
                preg_split('/\s+/', $normalizedText),
                fn($w) => $w !== ''
            )
        );
    }

    /**
     * Builds a term-frequency vector [word => count].
     * Removes stopwords and empty tokens.
     *
     * @return array<string, int>
     */
    private function buildTermVector(string $normalizedText): array
    {
        if ($normalizedText === '') {
            return [];
        }

        $tokens = normalize_tokens(tokenize($normalizedText));

        $stopWords = array_merge(
            $this->defaultStopWords(),
            $this->customStopWords
        );

        $stopWords = array_flip($stopWords);

        $tokens = array_filter(
            $tokens,
            static fn(string $token): bool =>
                $token !== '' && !isset($stopWords[$token])
        );

        return array_count_values(array_values($tokens));
    }

    /**
     * Generates an array of n-gram strings from normalised text.
     * e.g. "the cat sat" with n=2 → ["the cat", "cat sat"]
     *
     * @return string[]
     */
    private function buildNgrams(string $normalizedText, int $n): array
    {
        $words  = $this->splitWords($normalizedText);
        $total  = count($words);
        $ngrams = [];

        if ($total < $n) {
            return $ngrams;
        }

        for ($i = 0; $i <= $total - $n; $i++) {
            $ngrams[] = implode(' ', array_slice($words, $i, $n));
        }

        return $ngrams;
    }

    /**
     * Assembles the final result array and resolves the verdict.
     *
     * @param  float $score     Composite 0–100.
     * @param  array $breakdown Per-algorithm scores.
     * @return array
     */
    private function buildResult(float $score, array $breakdown): array
    {
        $roundedBreakdown = array_map(fn($v) => round($v, 2), $breakdown);

        return [
            'score'          => $score,
            'verdict'        => $this->resolveVerdict($score),
            'is_plagiarised' => $score >= 70.0,
            'breakdown'      => $roundedBreakdown,
        ];
    }

    /**
     * Maps a composite score to a human-readable verdict string.
     */
    private function resolveVerdict(float $score): string
    {
        return match (true) {
            $score >= 90.0 => self::VERDICT_BLOCKED,
            $score >= 70.0 => self::VERDICT_FLAGGED,
            $score >= 50.0 => self::VERDICT_WARNING,
            default        => self::VERDICT_CLEAN,
        };
    }

    /**
     * Default English stop words.
     *
     * Common filler words removed before similarity analysis.
     *
     * @return string[]
     */
    private function defaultStopWords(): array
    {
        return [
            'a', 'an', 'and', 'are', 'as', 'at',
            'be', 'by',
            'for', 'from',
            'has', 'he', 'in', 'is', 'it',
            'its',
            'of', 'on',
            'that', 'the', 'to',
            'was', 'were', 'will', 'with',
            'this', 'these', 'those',
            'i', 'you', 'your', 'we', 'they',
            'them', 'their', 'our',
            'or', 'if', 'then', 'than',
            'but', 'about', 'into',
            'up', 'down', 'over', 'under',
            'again', 'further',
            'once'
        ];
    }

}


// =============================================================================
// USAGE EXAMPLES
// =============================================================================
/*

// 1. Basic two-content check
$checker = new LCS_PlagiarismChecker();
$result  = $checker->check($existingPost, $newSubmission);

// $result:
// [
//   'score'          => 84.37,
//   'verdict'        => 'flagged',
//   'is_plagiarised' => true,
//   'breakdown'      => [
//     'cosine_tfidf'   => 91.20,
//     'jaccard_index'  => 78.50,
//     'word_frequency' => 85.00,
//     'ngram_overlap'  => 72.30,
//     'levenshtein'    => 61.10,
//   ]
// ]

// 2. Check against a whole database of posts
$corpus  = $db->getColumn('SELECT content FROM posts');
$corpus_result = $checker->checkAgainstCorpus($newSubmission, $corpus);

echo $corpus_result['highest_match']['score'];   // highest similarity found
echo $corpus_result['corpus_index'];             // which post it matched

// 3. Quick boolean check
if ($checker->isPlagiarised($existingPost, $newSubmission)) {
    throw new Exception('Duplicate content detected.');
}

// 4. Custom weights — e.g. academic strict mode (phrase-matching boosted)
$strictChecker = new LCS_PlagiarismChecker([
    'weights' => [
        'cosine'      => 0.30,
        'jaccard'     => 0.20,
        'word_freq'   => 0.15,
        'ngram'       => 0.25,   // boosted — catches paraphrasing
        'levenshtein' => 0.10,
    ],
    'ngram_size'  => 3,          // trigrams for tighter phrase detection
    'stop_words'  => ['however', 'therefore', 'furthermore'],
]);

// 5. Route by verdict
$result = $checker->check($existingPost, $newSubmission);

match ($result['verdict']) {
    LCS_PlagiarismChecker::VERDICT_BLOCKED => blockAndNotify($result),
    LCS_PlagiarismChecker::VERDICT_FLAGGED => queueForReview($result),
    LCS_PlagiarismChecker::VERDICT_WARNING => warnUser($result),
    LCS_PlagiarismChecker::VERDICT_CLEAN   => acceptSubmission(),
};

*/