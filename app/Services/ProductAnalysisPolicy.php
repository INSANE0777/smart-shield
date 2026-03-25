<?php

namespace App\Services;

use App\Models\AsinData;

/**
 * Centralized policy for product analysis business rules.
 *
 * This service consolidates all logic related to:
 * - What makes a product analyzable
 * - Default analysis results for products without reviews
 * - Display criteria for product listings
 *
 * This eliminates duplication across ReviewAnalysisService, MetricsCalculationService,
 * and various database queries.
 */
class ProductAnalysisPolicy
{
    /**
     * Determine if a product can be meaningfully analyzed.
     *
     * @param AsinData $product
     *
     * @return bool
     */
    public function isAnalyzable(AsinData $product): bool
    {
        $reviews = $product->getReviewsArray();

        return count($reviews) > 0;
    }

    /**
     * Determine if a product should be displayed in public listings.
     *
     * Products are displayed if they:
     * 1. Have completed analysis (status = completed)
     * 2. Have analysis results (fake_percentage and grade)
     * 3. Have at least 1 review (products with 0 reviews aren't useful to users)
     * 4. Have basic product data for display
     *
     * @param AsinData $product
     *
     * @return bool
     */
    public function shouldDisplayInListing(AsinData $product): bool
    {
        return $product->status === 'completed' &&
               !is_null($product->fake_percentage) &&
               !is_null($product->grade) &&
               $product->have_product_data &&
               !empty($product->product_title) &&
               $this->isAnalyzable($product);
    }

    /**
     * Get default analysis result for products without reviews.
     *
     * This provides consistent default values across all services.
     *
     * @return array
     */
    public function getDefaultAnalysisResult(): array
    {
        return [
            'detailed_scores'   => [],
            'analysis_provider' => 'system',
            'total_cost'        => 0.0,
        ];
    }

    /**
     * Get default metrics for products that cannot be analyzed.
     *
     * @return array
     */
    public function getDefaultMetrics(): array
    {
        return [
            'fake_percentage' => 0,
            'grade'           => 'U', // U = Unanalyzable (single char to fit database constraint)
            'explanation'     => 'No reviews could be extracted for analysis. This may occur when Amazon is actively blocking automated review collection, implementing anti-bot measures, or when the product genuinely has no customer reviews. Our system will automatically retry this analysis periodically to check if reviews become available.',
            'amazon_rating'   => 0.0,
            'adjusted_rating' => 0.0,
            'total_reviews'   => 0,
            'fake_count'      => 0,
        ];
    }

    /**
     * Complete analysis for a product without reviews.
     *
     * This sets appropriate default values and marks the product as completed
     * so it doesn't get stuck in processing loops.
     *
     * @param AsinData $product
     *
     * @return AsinData
     */
    public function completeAnalysisWithoutReviews(AsinData $product): AsinData
    {
        $defaultAnalysis = $this->getDefaultAnalysisResult();
        $defaultMetrics = $this->getDefaultMetrics();

        $product->update([
            'openai_result'     => $defaultAnalysis,
            'fake_percentage'   => $defaultMetrics['fake_percentage'],
            'grade'             => $defaultMetrics['grade'],
            'explanation'       => $defaultMetrics['explanation'],
            'amazon_rating'     => $defaultMetrics['amazon_rating'],
            'adjusted_rating'   => $defaultMetrics['adjusted_rating'],
            'status'            => 'completed',
            'first_analyzed_at' => $product->first_analyzed_at ?? now(),
            'last_analyzed_at'  => now(),
        ]);

        return $product->fresh();
    }

    /**
     * Get database query constraints for displayable products.
     *
     * This provides consistent filtering logic for all product listing queries.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyDisplayableConstraints($query)
    {
        return $query->where('status', 'completed')
            ->whereNotNull('fake_percentage')
            ->whereNotNull('grade')
            ->where('have_product_data', true)
            ->whereNotNull('product_title')
            ->whereNotNull('reviews')
            ->where('reviews', '!=', '[]')
            ->where('reviews', '!=', '""')
            ->where('reviews', '!=', 'null')
            ->where('reviews', '!=', '"[]"')  // Handle JSON string representation of empty array
            ->whereRaw("JSON_LENGTH(CASE WHEN JSON_VALID(reviews) THEN reviews ELSE '[]' END) > 0");
    }
}
