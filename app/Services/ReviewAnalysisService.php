<?php

namespace App\Services;

use App\Models\AsinData;
use App\Services\Amazon\AmazonUrlService;
use App\Services\Amazon\ReviewFetchingService;
use Illuminate\Support\Carbon;

class ReviewAnalysisService
{
    private AmazonUrlService $urlService;
    private ReviewFetchingService $fetchService;
    private ReviewService $reviewService;
    private MetricsCalculationService $metricsService;

    public function __construct(
        ReviewService $reviewService
    ) {
        $this->urlService = new AmazonUrlService();
        $this->fetchService = new ReviewFetchingService();
        $this->reviewService = $reviewService;
        $this->metricsService = new MetricsCalculationService();
    }

    /**
     * Main analysis method - orchestrates the entire process.
     */
    public function analyzeProduct(string $asin, string $country = 'us'): array
    {
        LoggingService::log("Starting product analysis for ASIN: {$asin}, Country: {$country}");

        try {
            // Step 1: Check if product exists in database
            $productUrl = $this->urlService->buildProductUrl($asin, $country);
            $existsResult = $this->checkProductExists($productUrl);

            $asinData = $existsResult['asin_data'];

            // Step 2: Fetch reviews if needed
            if ($existsResult['needs_fetching']) {
                $asinData = $this->fetchReviews($asin, $country, $productUrl);
            }

            // Step 3: Analyze with LLM if needed
            if ($existsResult['needs_openai'] || !$asinData->openai_result) {
                $asinData = $this->analyzeWithLLM($asinData);
            }

            // Step 4: Calculate final metrics
            $metrics = $this->calculateFinalMetrics($asinData);

            LoggingService::log("Product analysis completed for ASIN: {$asin}");

            return array_merge($existsResult, $metrics, [
                'asin_data' => $asinData->fresh(),
                'success'   => true,
            ]);
        } catch (\Exception $e) {
            LoggingService::handleException($e, "Product analysis failed for ASIN: {$asin}");

            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'asin'    => $asin,
                'country' => $country,
            ];
        }
    }

    /**
     * Extract ASIN from URL - delegates to URL service.
     */
    public function extractAsinFromUrl($url): string
    {
        return $this->urlService->extractAsinFromUrl($url);
    }

    /**
     * Check if product exists in database.
     */
    public function checkProductExists(string $productUrl): array
    {
        $asin = $this->urlService->extractAsinFromUrl($productUrl);
        $country = $this->urlService->extractCountryFromUrl($productUrl);
        $normalizedUrl = $this->urlService->buildProductUrl($asin, $country);

        $asinData = AsinData::where('asin', $asin)->where('country', $country)->first();

        $asinData = $this->maybeStartNoReviewsRetry($asinData);

        // Check if product is fully analyzed (has all required data)
        $isFullyAnalyzed = $asinData &&
                          $asinData->status === 'completed' &&
                          !is_null($asinData->openai_result) &&
                          !is_null($asinData->fake_percentage) &&
                          !is_null($asinData->grade);

        // If we just initiated a retry, we need to re-fetch reviews and re-run analysis.
        $needsRetryFetch = $asinData && $asinData->status === 'processing';

        return [
            'asin'           => $asin,
            'country'        => $country,
            'product_url'    => $normalizedUrl,
            'exists'         => $asinData !== null,
            'asin_data'      => $asinData,
            'needs_fetching' => $asinData === null || $needsRetryFetch,
            'needs_openai'   => !$isFullyAnalyzed || $needsRetryFetch,
        ];
    }

    /**
     * Determine if a Grade U product should be automatically retried on a new user request.
     *
     * Rule: max 3 total attempts, min 8 hours between attempts.
     * Implementation: initial attempt produced Grade U; allow 2 additional retries.
     */
    private function shouldAutoRetryNoReviews(AsinData $asinData): bool
    {
        if (!$this->isCompletedGradeU($asinData)) {
            return false;
        }

        if (!$this->hasNoExtractedReviews($asinData)) {
            return false;
        }

        $meta = $this->getNoReviewsRetryMeta($asinData);

        return $this->retryMetaAllowsNoReviewRetry($meta);
    }

    /**
     * If the product is in a "no reviews" (Grade U) state, treat it as retryable on user submit.
     * We cap retries to avoid runaway costs and apply a cooldown to prevent hammering the same ASIN.
     */
    private function maybeStartNoReviewsRetry(?AsinData $asinData): ?AsinData
    {
        if (!$asinData) {
            return null;
        }

        if (!$this->shouldAutoRetryNoReviews($asinData)) {
            return $asinData;
        }

        return $this->startNoReviewsRetry($asinData);
    }

    private function isCompletedGradeU(AsinData $asinData): bool
    {
        return $asinData->status === 'completed' && ($asinData->grade ?? '') === 'U';
    }

    private function hasNoExtractedReviews(AsinData $asinData): bool
    {
        return count($asinData->getReviewsArray()) === 0;
    }

    /**
     * @param array{retry_count?: int, last_retry_at?: \Illuminate\Support\Carbon|null} $meta
     */
    private function retryMetaAllowsNoReviewRetry(array $meta): bool
    {
        $retryCount = (int) ($meta['retry_count'] ?? 0);
        $lastRetryAt = $meta['last_retry_at'] ?? null;

        // Max 2 retries after the initial U (total attempts = 3)
        if ($retryCount >= 2) {
            return false;
        }

        if ($lastRetryAt instanceof Carbon) {
            return $lastRetryAt->diffInHours(now()) >= 8;
        }

        return true;
    }

    /**
     * Mark a Grade U product as being retried and increment the retry counter.
     *
     * This is intentionally lightweight: it clears analysis fields and flips status to processing,
     * so the existing pipeline will fetch reviews again and proceed normally.
     */
    private function startNoReviewsRetry(AsinData $asinData): AsinData
    {
        $notes = $this->getAnalysisNotesPayload($asinData);

        $retry = $notes['no_reviews_retry'] ?? [];
        $retryCount = (int) ($retry['retry_count'] ?? 0);
        $retryCount++;

        $retry['retry_count'] = $retryCount;
        $retry['last_retry_at'] = now()->toIso8601String();
        $retry['cooldown_hours'] = 8;
        $retry['max_total_attempts'] = 3;

        $notes['no_reviews_retry'] = $retry;

        LoggingService::log('Auto-retrying Grade U product on user request', [
            'asin'        => $asinData->asin,
            'country'     => $asinData->country,
            'retry_count' => $retryCount,
        ]);

        $asinData->update([
            'status'           => 'processing',
            'reviews'          => null,
            'openai_result'    => null,
            'fake_percentage'  => null,
            'grade'            => null,
            'explanation'      => null,
            'last_analyzed_at' => now(),
            'analysis_notes'   => json_encode($notes),
        ]);

        return $asinData->fresh();
    }

    /**
     * Parse analysis_notes into an array payload (best-effort).
     *
     * If existing notes aren't JSON, preserve them under 'legacy_notes'.
     *
     * @return array<string, mixed>
     */
    private function getAnalysisNotesPayload(AsinData $asinData): array
    {
        $raw = $asinData->analysis_notes;
        if (empty($raw)) {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [
            'legacy_notes' => $raw,
        ];
    }

    /**
     * Get retry metadata from analysis_notes (best-effort).
     *
     * @return array{retry_count?: int, last_retry_at?: \Illuminate\Support\Carbon|null}
     */
    private function getNoReviewsRetryMeta(AsinData $asinData): array
    {
        $notes = $this->getAnalysisNotesPayload($asinData);
        $retry = $notes['no_reviews_retry'] ?? null;

        if (!is_array($retry)) {
            return [];
        }

        $out = [
            'retry_count' => isset($retry['retry_count']) ? (int) $retry['retry_count'] : 0,
        ];

        if (!empty($retry['last_retry_at']) && is_string($retry['last_retry_at'])) {
            try {
                $out['last_retry_at'] = Carbon::parse($retry['last_retry_at']);
            } catch (\Exception $e) {
                $out['last_retry_at'] = null;
            }
        } else {
            $out['last_retry_at'] = null;
        }

        return $out;
    }

    /**
     * Fetch reviews from Amazon - delegates to fetching service.
     */
    public function fetchReviews(string $asin, string $country, string $productUrl): AsinData
    {
        $asinData = $this->fetchService->fetchReviews($asin, $country, $productUrl);

        // Queue product data scraping job if not already scraped
        if (!$asinData->have_product_data) {
            LoggingService::log('Queuing product data scraping job', [
                'asin'         => $asin,
                'asin_data_id' => $asinData->id,
            ]);

            \App\Jobs\ScrapeAmazonProductData::dispatch($asinData);
        }

        return $asinData;
    }

    /**
     * Analyze reviews with LLM (supports OpenAI, Ollama, DeepSeek via LLMServiceManager).
     */
    public function analyzeWithLLM(AsinData $asinData): AsinData
    {
        $policy = app(ProductAnalysisPolicy::class);

        if (!$policy->isAnalyzable($asinData)) {
            LoggingService::log("Product has no reviews to analyze, setting default analysis results for ASIN: {$asinData->asin}");

            return $policy->completeAnalysisWithoutReviews($asinData);
        }

        LoggingService::log('Starting LLM analysis for ASIN: '.$asinData->asin);

        $reviews = $asinData->getReviewsArray();

        try {
            // Use the multi-provider LLM service
            $llmService = app(LLMServiceManager::class);
            $analysisResult = $llmService->analyzeReviews($reviews);

            // Save the analysis result
            $asinData->update([
                'openai_result' => $analysisResult,
                'status'        => 'analyzed',
            ]);

            LoggingService::log('LLM analysis completed for ASIN: '.$asinData->asin);

            return $asinData->fresh();
        } catch (\Exception $e) {
            LoggingService::handleException($e, 'LLM analysis failed for ASIN: '.$asinData->asin);

            // Update status to indicate failure
            $asinData->update(['status' => 'failed']);

            throw $e;
        }
    }

    /**
     * Calculate final metrics - delegates to metrics service.
     */
    public function calculateFinalMetrics(AsinData $asinData): array
    {
        return $this->metricsService->calculateFinalMetrics($asinData);
    }

    /**
     * Enhanced Analysis for detailed insights.
     */
    public function performEnhancedAnalysis(AsinData $asinData): array
    {
        $reviews = $asinData->getReviewsArray();
        $openaiResult = $asinData->openai_result;

        if (empty($reviews) || empty($openaiResult)) {
            return [];
        }

        // Extract detailed scores
        $detailedScores = is_string($openaiResult)
            ? json_decode($openaiResult, true)['detailed_scores'] ?? []
            : $openaiResult['detailed_scores'] ?? [];

        $analysis = [
            'keyword_analysis'     => $this->analyzeKeywords($reviews),
            'timeline_patterns'    => $this->analyzeTimeline($reviews),
            'vocabulary_diversity' => $this->analyzeVocabulary($reviews),
            'fake_review_examples' => $this->extractFakeExamples($reviews, $detailedScores),
        ];

        // Save enhanced analysis
        $asinData->update([
            'detailed_analysis' => $analysis,
        ]);

        return $analysis;
    }

    /**
     * Analyze keywords in reviews.
     */
    private function analyzeKeywords(array $reviews): array
    {
        $keywords = [];
        $suspiciousPatterns = [
            'amazing', 'perfect', 'excellent', 'outstanding', 'fantastic',
            'highly recommend', 'must buy', 'love it', 'great product',
        ];

        foreach ($reviews as $review) {
            $text = strtolower($review['text'] ?? '');

            foreach ($suspiciousPatterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    $keywords[$pattern] = ($keywords[$pattern] ?? 0) + 1;
                }
            }
        }

        arsort($keywords);

        return array_slice($keywords, 0, 10, true);
    }

    /**
     * Analyze timeline patterns.
     */
    private function analyzeTimeline(array $reviews): array
    {
        $timeline = [];

        foreach ($reviews as $review) {
            if (isset($review['date'])) {
                $date = date('Y-m', strtotime($review['date']));
                $timeline[$date] = ($timeline[$date] ?? 0) + 1;
            }
        }

        ksort($timeline);

        return $timeline;
    }

    /**
     * Analyze vocabulary diversity.
     */
    private function analyzeVocabulary(array $reviews): array
    {
        $allWords = [];
        $uniqueWords = [];

        foreach ($reviews as $review) {
            $text = strtolower($review['text'] ?? '');
            $words = str_word_count($text, 1);

            $allWords = array_merge($allWords, $words);
            $uniqueWords = array_merge($uniqueWords, array_unique($words));
        }

        $totalWords = count($allWords);
        $uniqueWordCount = count(array_unique($uniqueWords));

        return [
            'total_words'     => $totalWords,
            'unique_words'    => $uniqueWordCount,
            'diversity_ratio' => $totalWords > 0 ? round($uniqueWordCount / $totalWords, 3) : 0,
        ];
    }

    /**
     * Extract examples of fake reviews for transparency.
     */
    private function extractFakeExamples(array $reviews, array $detailedScores): array
    {
        $fakeExamples = [];
        $exampleCount = 0;
        $maxExamples = 3;

        foreach ($reviews as $review) {
            if ($exampleCount >= $maxExamples) {
                break;
            }

            $reviewId = $review['id'];
            $fakeScore = $detailedScores[$reviewId] ?? 0;

            if ($fakeScore >= 85) {
                $fakeExamples[] = [
                    'text'       => substr($review['text'] ?? '', 0, 200).'...',
                    'rating'     => $review['rating'] ?? 0,
                    'fake_score' => $fakeScore,
                    'reasons'    => $this->generateFakeReasons($fakeScore),
                ];

                $exampleCount++;
            }
        }

        return $fakeExamples;
    }

    /**
     * Generate reasons why a review might be fake.
     */
    private function generateFakeReasons(int $fakeScore): array
    {
        $reasons = [];

        if ($fakeScore >= 95) {
            $reasons[] = 'Extremely generic language patterns';
            $reasons[] = 'Lacks specific product details';
        } elseif ($fakeScore >= 90) {
            $reasons[] = 'Overly promotional tone';
            $reasons[] = 'Suspicious timing patterns';
        } elseif ($fakeScore >= 85) {
            $reasons[] = 'Generic praise without specifics';
            $reasons[] = 'Unusual language patterns';
        }

        return $reasons;
    }
}

