<?php

namespace App\Services;

use App\Models\AsinData;

class MetricsCalculationServiceAggregate
{
    private GradeCalculationService $gradeService;

    public function __construct()
    {
        $this->gradeService = new GradeCalculationService();
    }

    /**
     * Calculate final metrics for analyzed product using aggregate LLM data.
     */
    public function calculateFinalMetrics(AsinData $asinData): array
    {
        $policy = app(ProductAnalysisPolicy::class);
        $reviews = $asinData->getReviewsArray();
        $openaiResult = $asinData->openai_result;

        if (!$policy->isAnalyzable($asinData) || empty($openaiResult)) {
            return $policy->getDefaultMetrics();
        }

        // Extract aggregate analysis data
        $aggregateData = $this->extractAggregateData($openaiResult);

        if (empty($aggregateData)) {
            return $policy->getDefaultMetrics();
        }

        // Use LLM's aggregate fake percentage directly
        $fakePercentage = (float) $aggregateData['fake_percentage'];
        $totalReviews = count($reviews);
        $fakeCount = round(($fakePercentage / 100) * $totalReviews);

        // Calculate ratings (simplified since we don't have per-review scores)
        $averageRating = $this->calculateAverageRating($reviews);
        $adjustedRating = $this->calculateAdjustedRating($averageRating, $fakePercentage);

        // Generate grade using existing service
        $grade = $this->gradeService->calculateGrade($fakePercentage);

        // Use LLM's explanation or generate fallback
        $explanation = $aggregateData['explanation'] ?? $this->generateExplanation($totalReviews, $fakeCount, $fakePercentage);

        // Only update the model if it hasn't been analyzed yet or if metrics are missing
        $needsUpdate = $asinData->status !== 'completed' ||
                      is_null($asinData->fake_percentage) ||
                      is_null($asinData->grade);

        if ($needsUpdate) {
            $asinData->update([
                'fake_percentage'  => $fakePercentage,
                'grade'            => $grade,
                'explanation'      => $explanation,
                'adjusted_rating'  => $adjustedRating,
                'status'           => 'completed',
                'last_analyzed_at' => now(),
            ]);

            LoggingService::log("Updated metrics for ASIN {$asinData->asin}: {$fakePercentage}% fake, Grade {$grade}");
        }

        return [
            'fake_percentage' => $fakePercentage,
            'grade'           => $grade,
            'explanation'     => $explanation,
            'adjusted_rating' => $adjustedRating,
            'total_reviews'   => $totalReviews,
            'fake_count'      => $fakeCount,
            'genuine_count'   => $totalReviews - $fakeCount,
            'confidence'      => $aggregateData['confidence'] ?? 'medium',
            'key_patterns'    => $aggregateData['key_patterns'] ?? [],
            'fake_examples'   => $aggregateData['fake_examples'] ?? [],
        ];
    }

    /**
     * Extract aggregate analysis data from LLM result.
     */
    private function extractAggregateData($openaiResult): array
    {
        if (is_string($openaiResult)) {
            $openaiResult = json_decode($openaiResult, true);
        }

        if (!is_array($openaiResult)) {
            return [];
        }

        // Check if this is aggregate format
        if (isset($openaiResult['fake_percentage'])) {
            return $openaiResult;
        }

        // Check if this is wrapped in a results key
        if (isset($openaiResult['results']) && isset($openaiResult['results']['fake_percentage'])) {
            return $openaiResult['results'];
        }

        return [];
    }

    /**
     * Calculate average rating from reviews.
     */
    private function calculateAverageRating(array $reviews): float
    {
        if (empty($reviews)) {
            return 0.0;
        }

        $totalRating = 0;
        $validRatings = 0;

        foreach ($reviews as $review) {
            $rating = $review['rating'] ?? null;
            if (is_numeric($rating) && $rating >= 1 && $rating <= 5) {
                $totalRating += $rating;
                $validRatings++;
            }
        }

        return $validRatings > 0 ? round($totalRating / $validRatings, 1) : 0.0;
    }

    /**
     * Calculate adjusted rating based on fake percentage.
     */
    private function calculateAdjustedRating(float $averageRating, float $fakePercentage): float
    {
        if ($averageRating <= 0) {
            return 0.0;
        }

        // Adjust rating based on fake percentage
        // Higher fake percentage = lower adjusted rating
        $adjustmentFactor = 1 - ($fakePercentage / 200); // Max 50% reduction for 100% fake
        $adjustedRating = $averageRating * max(0.5, $adjustmentFactor); // Minimum 50% of original

        return round($adjustedRating, 1);
    }

    /**
     * Generate explanation text.
     */
    private function generateExplanation(int $totalReviews, int $fakeCount, float $fakePercentage): string
    {
        $genuineCount = $totalReviews - $fakeCount;

        if ($fakePercentage >= 70) {
            return "High risk: {$fakeCount} of {$totalReviews} reviews appear fake ({$fakePercentage}%). Exercise caution when considering this product.";
        } elseif ($fakePercentage >= 30) {
            return "Moderate risk: {$fakeCount} of {$totalReviews} reviews appear fake ({$fakePercentage}%). Some reviews may be inauthentic.";
        } else {
            return "Low risk: {$genuineCount} of {$totalReviews} reviews appear genuine ({$fakePercentage}% fake). Most reviews seem authentic.";
        }
    }
}
