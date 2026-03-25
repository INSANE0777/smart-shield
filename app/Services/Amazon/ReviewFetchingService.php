<?php

namespace App\Services\Amazon;

use App\Models\AsinData;
use App\Services\LoggingService;

class ReviewFetchingService
{
    private AmazonReviewServiceInterface $fetchService;

    public function __construct()
    {
        // Use the configured review service (respects AMAZON_REVIEW_SERVICE env var)
        // This allows switching between BrightData, direct scraping, etc. as needed
        $this->fetchService = AmazonReviewServiceFactory::create();
    }

    /**
     * Fetch reviews for a product and save to database.
     */
    public function fetchReviews(string $asin, string $country, string $productUrl): AsinData
    {
        LoggingService::log("Starting review fetch for ASIN: {$asin}, Country: {$country}");

        try {
            // Use the factory-created service to fetch reviews
            $asinData = $this->fetchService->fetchReviewsAndSave($asin, $country, $productUrl);

            if (!$asinData) {
                throw new \Exception("Failed to fetch reviews for ASIN: {$asin}");
            }

            LoggingService::log("Successfully fetched reviews for ASIN: {$asin}");

            return $asinData;
        } catch (\Exception $e) {
            LoggingService::handleException($e, "Review fetching failed for ASIN: {$asin}");

            // Don't create empty records - let the exception propagate
            // This ensures that failed fetches don't create useless database entries
            throw $e;
        }
    }

    /**
     * Get available review services.
     */
    public function getAvailableServices(): array
    {
        return [
            'brightdata' => 'BrightData Web Scraper (Production)',
            'scraping'   => 'Direct Amazon Scraping (Development)',
            'ajax'       => 'Amazon AJAX Service (Legacy)',
        ];
    }

    /**
     * Check if reviews need to be fetched.
     */
    public function needsReviewFetch(AsinData $asinData): bool
    {
        // Check if we have reviews
        $reviews = $asinData->getReviewsArray();
        if (empty($reviews)) {
            return true;
        }

        // Check if analysis is incomplete
        if ($asinData->status !== 'completed') {
            return true;
        }

        // Check if data is stale (older than 30 days)
        if ($asinData->updated_at && $asinData->updated_at->diffInDays() > 30) {
            return true;
        }

        return false;
    }

    /**
     * Get review statistics.
     */
    public function getReviewStatistics(AsinData $asinData): array
    {
        $reviews = $asinData->getReviewsArray();

        if (empty($reviews)) {
            return [
                'total_reviews'       => 0,
                'average_rating'      => 0,
                'rating_distribution' => [],
                'verified_percentage' => 0,
            ];
        }

        $totalReviews = count($reviews);
        $ratingSum = 0;
        $verifiedCount = 0;
        $ratingDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($reviews as $review) {
            if (isset($review['rating']) && is_numeric($review['rating'])) {
                $rating = (int) $review['rating'];
                $ratingSum += $rating;

                if (isset($ratingDistribution[$rating])) {
                    $ratingDistribution[$rating]++;
                }
            }

            if (isset($review['meta_data']['verified_purchase']) && $review['meta_data']['verified_purchase']) {
                $verifiedCount++;
            }
        }

        return [
            'total_reviews'       => $totalReviews,
            'average_rating'      => $totalReviews > 0 ? round($ratingSum / $totalReviews, 2) : 0,
            'rating_distribution' => $ratingDistribution,
            'verified_percentage' => $totalReviews > 0 ? round(($verifiedCount / $totalReviews) * 100, 1) : 0,
        ];
    }
}
