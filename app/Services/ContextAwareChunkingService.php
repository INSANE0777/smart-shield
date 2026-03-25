<?php

namespace App\Services;

class ContextAwareChunkingService
{
    /**
     * Analyze reviews with context-aware chunking for any LLM provider.
     *
     * @param array    $reviews        The complete review dataset
     * @param int      $chunkSize      Number of reviews per chunk
     * @param callable $chunkProcessor Function to process each chunk: fn(array $chunk, array $context) => array
     * @param array    $options        Additional options for chunking behavior
     *
     * @return array Aggregated results from all chunks
     */
    public function processWithContextAwareChunking(
        array $reviews,
        int $chunkSize,
        callable $chunkProcessor,
        array $options = []
    ): array {
        if (empty($reviews)) {
            return ['results' => []];
        }

        $totalReviews = count($reviews);
        LoggingService::log("Starting context-aware chunking for {$totalReviews} reviews with chunk size {$chunkSize}");

        // Extract global context from all reviews (no API calls)
        $globalContext = $this->extractGlobalContext($reviews);

        // Create chunks
        $chunks = array_chunk($reviews, $chunkSize);
        $chunkResults = [];
        $failedChunks = 0;
        $totalChunks = count($chunks);

        LoggingService::log("Processing {$totalChunks} chunks with global context awareness");

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;

            try {
                LoggingService::log("Processing chunk {$chunkNumber}/{$totalChunks} with ".count($chunk).' reviews');

                // Add chunk-specific context
                $chunkContext = array_merge($globalContext, [
                    'chunk_number'      => $chunkNumber,
                    'total_chunks'      => $totalChunks,
                    'chunk_size'        => count($chunk),
                    'chunk_start_index' => $index * $chunkSize,
                ]);

                // Process chunk with context
                $result = $chunkProcessor($chunk, $chunkContext);

                if (!empty($result)) {
                    $chunkResults[] = array_merge($result, [
                        'chunk_number' => $chunkNumber,
                        'review_count' => count($chunk),
                    ]);

                    LoggingService::log("Chunk {$chunkNumber} completed successfully");
                } else {
                    LoggingService::log("Chunk {$chunkNumber} returned empty result");
                    $failedChunks++;
                }

                // Rate limiting delay if specified
                if (isset($options['delay_ms']) && $options['delay_ms'] > 0) {
                    usleep($options['delay_ms'] * 1000);
                }
            } catch (\Exception $e) {
                LoggingService::log("Chunk {$chunkNumber} failed: ".$e->getMessage());
                $failedChunks++;

                // Continue processing other chunks unless configured to fail fast
                if ($options['fail_fast'] ?? false) {
                    throw $e;
                }
            }
        }

        // Validate results
        $maxFailureRate = $options['max_failure_rate'] ?? 0.5;
        if ($failedChunks > ($totalChunks * $maxFailureRate)) {
            throw new \Exception("Too many chunks failed ({$failedChunks}/{$totalChunks}), exceeds max failure rate of ".($maxFailureRate * 100).'%');
        }

        if (empty($chunkResults)) {
            throw new \Exception('No successful chunks to aggregate');
        }

        LoggingService::log("Chunking completed: {$totalChunks} chunks, {$failedChunks} failed");

        // Aggregate results with context awareness
        return $this->aggregateChunkResults($chunkResults, $globalContext, $options);
    }

    /**
     * Extract global context and patterns from all reviews (no API calls).
     */
    public function extractGlobalContext(array $reviews): array
    {
        $totalReviews = count($reviews);
        $ratingDistribution = [];
        $verifiedCount = 0;
        $vineCount = 0;
        $datePatterns = [];
        $textLengths = [];

        foreach ($reviews as $review) {
            // Rating distribution
            $rating = $review['rating'] ?? 0;
            $ratingDistribution[$rating] = ($ratingDistribution[$rating] ?? 0) + 1;

            // Verification status
            if (isset($review['meta_data']['verified_purchase']) && $review['meta_data']['verified_purchase']) {
                $verifiedCount++;
            }

            // Vine reviews
            if (isset($review['meta_data']['is_vine_voice']) && $review['meta_data']['is_vine_voice']) {
                $vineCount++;
            }

            // Date patterns (if available)
            if (isset($review['date'])) {
                $month = date('Y-m', strtotime($review['date']));
                $datePatterns[$month] = ($datePatterns[$month] ?? 0) + 1;
            }

            // Text length analysis
            $text = $review['text'] ?? $review['review_text'] ?? '';
            $textLengths[] = strlen($text);
        }

        // Calculate key statistics
        $fiveStarPercentage = round(($ratingDistribution[5] ?? 0) / $totalReviews * 100, 1);
        $fourPlusPct = round((($ratingDistribution[4] ?? 0) + ($ratingDistribution[5] ?? 0)) / $totalReviews * 100, 1);
        $verifiedPercentage = round($verifiedCount / $totalReviews * 100, 1);
        $vinePercentage = round($vineCount / $totalReviews * 100, 1);
        $avgTextLength = !empty($textLengths) ? round(array_sum($textLengths) / count($textLengths)) : 0;

        // Detect suspicious patterns
        $suspiciousPatterns = $this->detectSuspiciousPatterns([
            'five_star_percentage' => $fiveStarPercentage,
            'four_plus_percentage' => $fourPlusPct,
            'verified_percentage'  => $verifiedPercentage,
            'vine_percentage'      => $vinePercentage,
            'avg_text_length'      => $avgTextLength,
            'total_reviews'        => $totalReviews,
        ]);

        return [
            'total_reviews'        => $totalReviews,
            'rating_distribution'  => $ratingDistribution,
            'five_star_percentage' => $fiveStarPercentage,
            'four_plus_percentage' => $fourPlusPct,
            'verified_percentage'  => $verifiedPercentage,
            'vine_percentage'      => $vinePercentage,
            'avg_text_length'      => $avgTextLength,
            'suspicious_patterns'  => $suspiciousPatterns,
            'date_patterns'        => $datePatterns,
            'context_summary'      => $this->generateContextSummary($fiveStarPercentage, $verifiedPercentage, $vinePercentage, $suspiciousPatterns),
        ];
    }

    /**
     * Detect suspicious patterns in global statistics.
     */
    private function detectSuspiciousPatterns(array $stats): array
    {
        $patterns = [];

        if ($stats['five_star_percentage'] > 85) {
            $patterns[] = "Extremely high 5-star concentration ({$stats['five_star_percentage']}%)";
        }

        if ($stats['four_plus_percentage'] > 95) {
            $patterns[] = "Overwhelming positive ratings ({$stats['four_plus_percentage']}% are 4-5 stars)";
        }

        if ($stats['verified_percentage'] < 30) {
            $patterns[] = "Low verified purchase rate ({$stats['verified_percentage']}%)";
        }

        if ($stats['vine_percentage'] > 20) {
            $patterns[] = "High Vine review concentration ({$stats['vine_percentage']}%)";
        }

        if ($stats['avg_text_length'] < 50) {
            $patterns[] = "Unusually short reviews (avg {$stats['avg_text_length']} chars)";
        }

        if ($stats['total_reviews'] > 1000 && $stats['five_star_percentage'] > 90) {
            $patterns[] = 'High-volume product with suspicious rating uniformity';
        }

        return $patterns;
    }

    /**
     * Generate compact context summary for inclusion in prompts (~100 tokens).
     */
    public function generateContextSummary(float $fiveStarPct, float $verifiedPct, float $vinePct, array $suspiciousPatterns): string
    {
        $summary = "GLOBAL CONTEXT: {$fiveStarPct}% 5-star, {$verifiedPct}% verified, {$vinePct}% Vine. ";

        if (!empty($suspiciousPatterns)) {
            $summary .= 'ALERTS: '.implode('; ', array_slice($suspiciousPatterns, 0, 2)).'. ';
        } else {
            $summary .= 'No major red flags detected. ';
        }

        return $summary;
    }

    /**
     * Aggregate results from multiple chunks with context awareness.
     */
    public function aggregateChunkResults(array $chunkResults, array $globalContext, array $options = []): array
    {
        $totalReviewsProcessed = array_sum(array_column($chunkResults, 'review_count'));
        $weightedFakePercentage = 0;
        $allExamples = [];
        $allPatterns = [];
        $explanations = [];

        // Calculate weighted average fake percentage
        foreach ($chunkResults as $chunk) {
            $weight = $chunk['review_count'] / $totalReviewsProcessed;
            $weightedFakePercentage += ($chunk['fake_percentage'] ?? 0) * $weight;

            // Collect examples and patterns
            if (isset($chunk['fake_examples'])) {
                $allExamples = array_merge($allExamples, $chunk['fake_examples']);
            }
            if (isset($chunk['key_patterns'])) {
                $allPatterns = array_merge($allPatterns, $chunk['key_patterns']);
            }
            if (isset($chunk['explanation'])) {
                $explanations[] = $chunk['explanation'];
            }
        }

        // Determine confidence based on chunk consistency
        $fakePercentages = array_column($chunkResults, 'fake_percentage');
        $standardDeviation = $this->calculateStandardDeviation($fakePercentages);

        $confidence = match (true) {
            $standardDeviation < 10 => 'high',
            $standardDeviation < 20 => 'medium',
            default                 => 'low'
        };

        // Create context-aware explanation
        $explanation = $this->synthesizeContextAwareExplanation(
            $globalContext,
            $weightedFakePercentage,
            $confidence,
            $explanations,
            $options
        );

        return [
            'fake_percentage'   => round($weightedFakePercentage, 1),
            'confidence'        => $confidence,
            'explanation'       => $explanation,
            'fake_examples'     => array_slice($this->deduplicateExamples($allExamples), 0, 3),
            'key_patterns'      => array_slice($this->deduplicatePatterns($allPatterns), 0, 5),
            'chunk_consistency' => $confidence,
            'chunks_processed'  => count($chunkResults),
            'global_context'    => $globalContext,
        ];
    }

    /**
     * Create synthesized explanation that avoids repetition and includes global context.
     */
    private function synthesizeContextAwareExplanation(
        array $globalContext,
        float $fakePercentage,
        string $confidence,
        array $explanations,
        array $options = []
    ): string {
        $totalReviews = $globalContext['total_reviews'];
        $chunkCount = count($explanations);

        // Start with global context
        $explanation = "Analysis of {$totalReviews} reviews across {$chunkCount} chunks. ";
        $explanation .= 'Weighted fake percentage: '.round($fakePercentage, 1).'%. ';
        $explanation .= "Chunk consistency: {$confidence}. ";

        // Add global pattern insights
        if (!empty($globalContext['suspicious_patterns'])) {
            $explanation .= 'Global patterns: '.$globalContext['suspicious_patterns'][0].'. ';
        } else {
            $explanation .= "Rating distribution appears normal ({$globalContext['five_star_percentage']}% 5-star). ";
        }

        // Add unique insights from chunks (avoid repetition)
        $uniqueInsights = $this->extractUniqueInsights($explanations);
        if (!empty($uniqueInsights)) {
            $insight = $uniqueInsights[0];
            // Ensure the insight ends with proper punctuation
            if (!str_ends_with($insight, '.') && !str_ends_with($insight, '!') && !str_ends_with($insight, '?')) {
                $insight .= '.';
            }
            $explanation .= $insight;
        }

        return $explanation;
    }

    /**
     * Deduplicate fake examples based on review text content.
     */
    private function deduplicateExamples(array $examples): array
    {
        $seen = [];
        $unique = [];

        foreach ($examples as $example) {
            if (is_array($example)) {
                $key = ($example['text'] ?? '').'|'.($example['reason'] ?? '');
            } else {
                $key = (string) $example;
            }

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $example;
            }
        }

        return $unique;
    }

    /**
     * Deduplicate key patterns, handling both string and array formats.
     */
    private function deduplicatePatterns(array $patterns): array
    {
        $seen = [];
        $unique = [];

        foreach ($patterns as $pattern) {
            if (is_array($pattern)) {
                $key = json_encode($pattern);
            } else {
                $key = (string) $pattern;
            }

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $pattern;
            }
        }

        return $unique;
    }

    /**
     * Extract unique insights from chunk explanations, removing duplicates.
     */
    private function extractUniqueInsights(array $explanations): array
    {
        $insights = [];
        $seenConcepts = [];

        foreach ($explanations as $explanation) {
            $sentences = preg_split('/[.!?]+/', $explanation, -1, PREG_SPLIT_NO_EMPTY);

            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (strlen($sentence) < 20) {
                    continue;
                }

                // Extract key concepts for uniqueness checking
                $concepts = $this->extractKeyConcepts($sentence);

                // Check if this insight is unique
                $isUnique = true;
                foreach ($concepts as $concept) {
                    if (in_array($concept, $seenConcepts)) {
                        $isUnique = false;
                        break;
                    }
                }

                if ($isUnique && !empty($concepts)) {
                    $insights[] = $sentence.'.';
                    $seenConcepts = array_merge($seenConcepts, $concepts);

                    // Limit to prevent bloat
                    if (count($insights) >= 2) {
                        break 2;
                    }
                }
            }
        }

        return $insights;
    }

    /**
     * Extract key concepts from a sentence for uniqueness checking.
     */
    private function extractKeyConcepts(string $sentence): array
    {
        $concepts = [];

        $patterns = [
            '/(\d+%?\s*(?:fake|genuine|suspicious|positive|negative|reviews?))/i',
            '/(high|low|moderate|extreme)\s+(?:concentration|percentage|ratings?)/i',
            '/(verified|unverified)\s+purchase/i',
            '/(5-star|4-star|3-star|2-star|1-star)\s+ratings?/i',
            '/review\s+(manipulation|authenticity|patterns?)/i',
            '/(linguistic|content|metadata)\s+patterns?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sentence, $matches)) {
                $concepts = array_merge($concepts, $matches[0]);
            }
        }

        return array_unique(array_map('strtolower', $concepts));
    }

    /**
     * Calculate standard deviation for consistency measurement.
     */
    private function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0;
        }

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / $count;

        return sqrt($variance);
    }

    /**
     * Generate context header for inclusion in chunk prompts.
     */
    public function generateContextHeader(array $globalContext): string
    {
        return 'CONTEXT: '.$globalContext['context_summary'];
    }
}
