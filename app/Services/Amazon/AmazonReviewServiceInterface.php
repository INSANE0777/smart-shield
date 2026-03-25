<?php

namespace App\Services\Amazon;

use App\Models\AsinData;

/**
 * Interface for Amazon review fetching services.
 *
 * This interface ensures compatibility between different Amazon review
 * fetching implementations (Unwrangle API, direct scraping, etc.)
 */
interface AmazonReviewServiceInterface
{
    /**
     * Fetch Amazon reviews and save to database.
     *
     * @param string $asin       Amazon Standard Identification Number
     * @param string $country    Two-letter country code
     * @param string $productUrl Full Amazon product URL
     *
     * @throws \Exception If product doesn't exist or fetching fails
     *
     * @return AsinData The created database record
     */
    public function fetchReviewsAndSave(string $asin, string $country, string $productUrl): AsinData;

    /**
     * Fetch reviews from Amazon.
     *
     * @param string $asin    Amazon Standard Identification Number
     * @param string $country Two-letter country code (defaults to 'us')
     *
     * @return array<string, mixed> Array containing reviews, description, and total count
     */
    public function fetchReviews(string $asin, string $country = 'us'): array;
}
