<?php

namespace App\Services;

use App\Models\AsinData;
use Illuminate\Support\Facades\Schema;

class ExtensionReviewService
{
    /**
     * Process review data from Chrome extension.
     */
    public function processExtensionData(array $extensionData): AsinData
    {
        $asin = $extensionData['asin'];
        $country = $extensionData['country'];

        LoggingService::log('Processing Chrome extension data', [
            'asin'              => $asin,
            'country'           => $country,
            'total_reviews'     => $extensionData['product_info']['total_reviews_on_amazon'] ?? count($extensionData['reviews']),
            'extracted_reviews' => count($extensionData['reviews']),
            'extension_version' => $extensionData['extension_version'],
        ]);

        // Check if product is already fully analyzed - don't overwrite completed analysis
        $existingData = AsinData::where('asin', $asin)->where('country', $country)->first();
        if ($existingData && $existingData->isAnalyzed()) {
            LoggingService::log('Extension data skipped - analysis already complete', [
                'asin'   => $asin,
                'status' => $existingData->status,
                'grade'  => $existingData->grade,
            ]);

            return $existingData;
        }

        // Transform extension review format to internal format
        $transformedReviews = $this->transformReviewsFormat($extensionData['reviews']);

        // Use product information provided by Chrome extension
        $productInfo = $extensionData['product_info'] ?? [];

        // Prepare data for AsinData record
        $updateData = [
            'product_title'           => isset($productInfo['title']) ? $this->sanitizeHtml($productInfo['title']) : null,
            'product_description'     => isset($productInfo['description']) ? $this->sanitizeHtml($productInfo['description']) : null,
            'product_image_url'       => $productInfo['image_url'] ?? null,
            'amazon_rating'           => $productInfo['amazon_rating'] ?? null,
            'reviews'                 => json_encode($transformedReviews),
            'total_reviews_on_amazon' => $productInfo['total_reviews_on_amazon'] ?? 0,
            'status'                  => 'fetched',
            'have_product_data'       => !empty($productInfo['title']),
            'product_data_scraped_at' => now(),
        ];

        // Store price data if available from extension
        if (!empty($productInfo['price'])) {
            $priceData = $this->parsePrice($productInfo['price']);
            if ($priceData['amount']) {
                $updateData['price'] = $priceData['amount'];
                $updateData['currency'] = $priceData['currency'];
                $updateData['price_updated_at'] = now();
            }
        }
        if (!empty($productInfo['availability'])) {
            $updateData['availability'] = $this->sanitizeHtml($productInfo['availability']);
        }

        // Add extension-specific fields if they exist in the database schema
        if (Schema::hasColumn('asin_data', 'source')) {
            $updateData['source'] = 'chrome_extension';
        }
        if (Schema::hasColumn('asin_data', 'extension_version')) {
            $updateData['extension_version'] = $extensionData['extension_version'];
        }
        if (Schema::hasColumn('asin_data', 'extraction_timestamp')) {
            $updateData['extraction_timestamp'] = $extensionData['extraction_timestamp'];
        }

        // Create or update AsinData record
        $asinData = AsinData::updateOrCreate(
            [
                'asin'    => $asin,
                'country' => $country,
            ],
            $updateData
        );

        LoggingService::log('Chrome extension data processed successfully', [
            'asin'         => $asin,
            'asin_data_id' => $asinData->id,
            'review_count' => count($transformedReviews),
        ]);

        return $asinData;
    }

    /**
     * Transform Chrome extension review format to internal format.
     */
    private function transformReviewsFormat(array $extensionReviews): array
    {
        $transformedReviews = [];

        foreach ($extensionReviews as $review) {
            $transformedReviews[] = [
                'id'                => $review['review_id'],
                'author'            => $this->sanitizeHtml($review['author']),
                'title'             => $this->sanitizeHtml($review['title']),
                'content'           => $this->sanitizeHtml($review['content']),
                'rating'            => $review['rating'],
                'date'              => $review['date'],
                'verified_purchase' => $review['verified_purchase'],
                'vine_customer'     => $review['vine_customer'],
                'helpful_votes'     => $review['helpful_votes'],
                'extraction_index'  => $review['extraction_index'],
            ];
        }

        LoggingService::log('Transformed extension reviews', [
            'original_count'    => count($extensionReviews),
            'transformed_count' => count($transformedReviews),
        ]);

        return $transformedReviews;
    }

    /**
     * Extract basic product information from Amazon URL.
     */
    private function extractProductInfoFromUrl(string $productUrl): array
    {
        $productInfo = [
            'title'       => null,
            'description' => '',
            'image_url'   => null,
        ];

        // Try to extract product title from URL path
        if (preg_match('/\/([^\/]+)\/dp\/[A-Z0-9]{10}/', $productUrl, $matches)) {
            $urlTitle = $matches[1];
            // Convert URL-encoded title to readable format
            $productInfo['title'] = str_replace('-', ' ', urldecode($urlTitle));
            $productInfo['title'] = ucwords(strtolower($productInfo['title']));
        }

        LoggingService::log('Extracted product info from URL', [
            'url'             => $productUrl,
            'extracted_title' => $productInfo['title'],
        ]);

        return $productInfo;
    }

    /**
     * Validate Chrome extension data format.
     */
    public function validateExtensionData(array $data): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['asin', 'country', 'product_url', 'total_reviews', 'reviews'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // ASIN format validation
        if (isset($data['asin']) && !preg_match('/^[A-Z0-9]{10}$/', $data['asin'])) {
            $errors[] = 'Invalid ASIN format';
        }

        // Country code validation
        if (isset($data['country']) && strlen($data['country']) !== 2) {
            $errors[] = 'Invalid country code format';
        }

        // Reviews validation
        if (isset($data['reviews']) && is_array($data['reviews'])) {
            foreach ($data['reviews'] as $index => $review) {
                $requiredReviewFields = ['author', 'content', 'rating', 'review_id'];
                foreach ($requiredReviewFields as $field) {
                    if (!isset($review[$field])) {
                        $errors[] = "Missing required review field '{$field}' in review {$index}";
                    }
                }

                // Rating validation
                if (isset($review['rating']) && ($review['rating'] < 1 || $review['rating'] > 5)) {
                    $errors[] = "Invalid rating in review {$index}: must be 1-5";
                }
            }
        }

        return $errors;
    }

    /**
     * Get supported Amazon domains for extension.
     */
    public function getSupportedDomains(): array
    {
        return [
            'us' => 'amazon.com',
            'ca' => 'amazon.ca',
            'gb' => 'amazon.co.uk',
            'de' => 'amazon.de',
            'fr' => 'amazon.fr',
            'it' => 'amazon.it',
            'es' => 'amazon.es',
            'ie' => 'amazon.ie',
            'jp' => 'amazon.co.jp',
            'au' => 'amazon.com.au',
            'mx' => 'amazon.com.mx',
            'in' => 'amazon.in',
            'sg' => 'amazon.sg',
            'br' => 'amazon.com.br',
            'nl' => 'amazon.nl',
            'tr' => 'amazon.com.tr',
            'ae' => 'amazon.ae',
            'sa' => 'amazon.sa',
            'se' => 'amazon.se',
            'pl' => 'amazon.pl',
            'eg' => 'amazon.eg',
            'be' => 'amazon.be',
        ];
    }

    /**
     * Sanitize HTML content to prevent XSS attacks.
     *
     * @param string $content The content to sanitize
     *
     * @return string The sanitized content
     */
    private function sanitizeHtml(string $content): string
    {
        // Strip all HTML tags to prevent XSS
        $sanitized = strip_tags($content);

        // Decode HTML entities to prevent double encoding
        $sanitized = html_entity_decode($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Re-encode special characters to prevent XSS
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Trim whitespace
        return trim($sanitized);
    }

    /**
     * Parse price string into amount and currency.
     * Handles formats like "$29.99", "£19.99", "€24.99", "CDN$ 39.99".
     */
    private function parsePrice(string $priceString): array
    {
        $result = ['amount' => null, 'currency' => 'USD'];

        // Currency symbol mapping
        $currencyMap = [
            '$'    => 'USD',
            '£'    => 'GBP',
            '€'    => 'EUR',
            '¥'    => 'JPY',
            'CDN$' => 'CAD',
            'A$'   => 'AUD',
            'MX$'  => 'MXN',
            '₹'    => 'INR',
        ];

        // Detect currency from symbol
        foreach ($currencyMap as $symbol => $currency) {
            if (str_contains($priceString, $symbol)) {
                $result['currency'] = $currency;
                break;
            }
        }

        // Extract numeric value
        // Remove currency symbols and text, keep numbers and decimal points
        $cleanPrice = preg_replace('/[^0-9.,]/', '', $priceString);

        // Handle European format (1.234,56) vs US format (1,234.56)
        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d{2})?$/', $cleanPrice)) {
            // European format: replace . with nothing, , with .
            $cleanPrice = str_replace('.', '', $cleanPrice);
            $cleanPrice = str_replace(',', '.', $cleanPrice);
        } else {
            // US format: just remove commas
            $cleanPrice = str_replace(',', '', $cleanPrice);
        }

        if (is_numeric($cleanPrice)) {
            $result['amount'] = (float) $cleanPrice;
        }

        return $result;
    }
}
