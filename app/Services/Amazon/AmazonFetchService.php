<?php

namespace App\Services\Amazon;

use App\Models\AsinData;
use App\Services\AlertManager;
use App\Services\LoggingService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

/**
 * Service for fetching Amazon product reviews via Unwrangle API.
 */
class AmazonFetchService implements AmazonReviewServiceInterface
{
    private Client $httpClient;

    /**
     * Initialize the service with HTTP client configuration.
     */
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout'         => 120, // Default longer timeout
            'connect_timeout' => 15,  // Longer connect timeout
            'http_errors'     => false,
            'verify'          => false, // Skip SSL verification if needed
            'headers'         => [
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection'      => 'keep-alive',
            ],
        ]);
    }

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
    public function fetchReviewsAndSave(string $asin, string $country, string $productUrl): AsinData
    {
        // Fetch reviews from Amazon with optimized performance
        $reviewsData = $this->fetchReviewsOptimized($asin, $country);

        // Check if fetching failed and provide specific error message
        if (empty($reviewsData) || !isset($reviewsData['reviews'])) {
            // Provide more specific error messages based on common failure scenarios
            throw new \Exception('Unable to fetch product reviews at this time. This could be due to:
• The product URL being invalid or the product not existing on Amazon
• Amazon blocking our review service (temporary)
• Network connectivity issues
• The review service being overloaded

Please try again in a few minutes. If the problem persists, verify the Amazon URL is correct and the product exists on amazon.com.');
        }

        // Save to database - use updateOrCreate to handle re-scraping existing ASINs
        return AsinData::updateOrCreate(
            ['asin' => $asin],
            [
                'country'                 => $country,
                'product_description'     => $reviewsData['description'] ?? '',
                'reviews'                 => json_encode($reviewsData['reviews']),
                'total_reviews_on_amazon' => $reviewsData['total_reviews'] ?? count($reviewsData['reviews'] ?? []),
                'openai_result'           => null, // Will be populated later
            ]
        );
    }

    /**
     * Optimized version of fetchReviews with improved performance.
     */
    public function fetchReviewsOptimized(string $asin, string $country = 'us'): array
    {
        // Only do basic ASIN format validation - let client-side JS and Unwrangle API handle the rest
        if (!$this->isValidAsinFormat($asin)) {
            LoggingService::log('ASIN format validation failed', [
                'asin' => $asin,
            ]);

            return [];
        }

        LoggingService::log('Server-side validation skipped - using client-side validation', [
            'asin' => $asin,
        ]);

        // Quick connectivity test (skip in testing environment)
        if (!app()->environment('testing')) {
            LoggingService::log('Testing connectivity to Unwrangle API...');
            if (!$this->testApiConnectivity()) {
                LoggingService::log('API connectivity test failed - network or DNS issues');

                // Record connectivity failure
                app(AlertManager::class)->recordFailure(
                    'Unwrangle API',
                    'CONNECTIVITY_TEST_FAILED',
                    'Basic connectivity test to Unwrangle API failed',
                    ['asin' => $asin]
                );

                return [];
            }
        }

        $apiKey = env('UNWRANGLE_API_KEY');
        $cookie = env('UNWRANGLE_AMAZON_COOKIE');
        $baseUrl = 'https://data.unwrangle.com/api/getter/';
        $maxPages = 5; // Reduced from 10 for bandwidth optimization - 5 pages typically sufficient for analysis
        $country = 'us';

        $query = [
            'platform'     => 'amazon_reviews',
            'asin'         => $asin,
            'country_code' => $country,
            'max_pages'    => $maxPages,
            'api_key'      => $apiKey,
            'cookie'       => $cookie,
        ];

        // Try with progressively shorter timeouts and fewer pages for bandwidth optimization
        $attempts = [
            ['timeout' => 45, 'max_pages' => 3, 'description' => 'quick fetch (3 pages, 45s) - BANDWIDTH OPTIMIZED'],
            ['timeout' => 60, 'max_pages' => 5, 'description' => 'standard fetch (5 pages, 60s) - BANDWIDTH OPTIMIZED'],
            ['timeout' => 90, 'max_pages' => 7, 'description' => 'extended fetch (7 pages, 90s) - FALLBACK'],
        ];

        foreach ($attempts as $attemptIndex => $attempt) {
            try {
                LoggingService::log('Attempt '.($attemptIndex + 1).": {$attempt['description']}", [
                    'asin'         => $asin,
                    'timeout'      => $attempt['timeout'],
                    'max_pages'    => $attempt['max_pages'],
                    'url'          => $baseUrl,
                    'query_params' => array_merge($query, ['max_pages' => $attempt['max_pages']]),
                ]);

                $query['max_pages'] = $attempt['max_pages'];
                $startTime = microtime(true);

                $response = $this->httpClient->request('GET', $baseUrl, [
                    'query'           => $query,
                    'timeout'         => $attempt['timeout'],
                    'connect_timeout' => 15, // Increased connect timeout
                    'read_timeout'    => $attempt['timeout'], // Explicit read timeout
                ]);

                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2);

                $status = $response->getStatusCode();
                $body = $response->getBody()->getContents();

                LoggingService::log('Unwrangle API response', [
                    'attempt'     => $attemptIndex + 1,
                    'status'      => $status,
                    'has_data'    => !empty($body),
                    'body_length' => strlen($body),
                    'duration_ms' => $duration,
                    'max_pages'   => $attempt['max_pages'],
                ]);

                if ($status !== 200) {
                    LoggingService::log('Unwrangle API non-200 response', [
                        'attempt' => $attemptIndex + 1,
                        'status'  => $status,
                        'body'    => substr($body, 0, 500), // Limit log size
                    ]);

                    // Check for Amazon session/cookie expired errors
                    $data = json_decode($body, true);
                    if ($data && $this->isAmazonCookieExpiredError($data)) {
                        app(AlertManager::class)->recordFailure(
                            'Unwrangle API',
                            'SESSION_EXPIRED',
                            $data['message'] ?? 'Amazon session/cookie has expired',
                            [
                                'asin'        => $asin,
                                'status_code' => $status,
                                'error_code'  => $data['error_code'] ?? 'UNKNOWN',
                                'api_message' => $data['message'] ?? '',
                            ]
                        );
                    }

                    // Try next attempt if available
                    continue;
                }

                $data = json_decode($body, true);

                if (empty($data) || empty($data['success'])) {
                    LoggingService::log('Unwrangle API returned error', [
                        'attempt' => $attemptIndex + 1,
                        'error'   => $data['error'] ?? 'Unknown error',
                    ]);

                    // Check for Amazon cookie expiration even on success=false responses
                    if ($data && $this->isAmazonCookieExpiredError($data)) {
                        app(AlertManager::class)->recordFailure(
                            'Unwrangle API',
                            'SESSION_EXPIRED',
                            $data['message'] ?? 'Amazon session/cookie has expired',
                            [
                                'asin'        => $asin,
                                'status_code' => 200, // This was a 200 response with success=false
                                'error_code'  => $data['error_code'] ?? 'UNKNOWN',
                                'api_message' => $data['message'] ?? '',
                            ]
                        );
                    }

                    // Try next attempt if available
                    continue;
                }

                $totalReviews = $data['total_results'] ?? count($data['reviews'] ?? []);
                $description = $data['description'] ?? '';
                $allReviews = $data['reviews'] ?? [];

                // Success! Log and return results
                LoggingService::log('Successfully retrieved '.count($allReviews).' reviews for analysis (attempt '.($attemptIndex + 1).')');

                return [
                    'reviews'       => $allReviews,
                    'description'   => $description,
                    'total_reviews' => $totalReviews,
                ];
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $errorType = $this->categorizeError($errorMessage);

                LoggingService::log('Unwrangle API request exception', [
                    'attempt'      => $attemptIndex + 1,
                    'error'        => $errorMessage,
                    'asin'         => $asin,
                    'timeout_used' => $attempt['timeout'],
                    'error_type'   => $errorType,
                ]);

                // Send alerts for specific error types
                if ($errorType === 'CONNECTION_TIMEOUT_NO_DATA') {
                    app(AlertManager::class)->recordFailure(
                        'Unwrangle API',
                        'CONNECTION_TIMEOUT_NO_DATA',
                        $errorMessage,
                        [
                            'asin'             => $asin,
                            'timeout_duration' => $attempt['timeout'],
                            'attempt'          => $attemptIndex + 1,
                            'max_pages'        => $attempt['max_pages'],
                            'error_details'    => $errorMessage,
                        ]
                    );
                } elseif (in_array($errorType, ['CONNECTION_FAILED', 'DNS_RESOLUTION_FAILED'])) {
                    app(AlertManager::class)->recordFailure(
                        'Unwrangle API',
                        $errorType,
                        $errorMessage,
                        [
                            'asin'    => $asin,
                            'attempt' => $attemptIndex + 1,
                        ]
                    );
                }

                // Check if this is a timeout error
                if (str_contains($errorMessage, 'cURL error 28') || str_contains($errorMessage, 'timed out')) {
                    LoggingService::log('Timeout occurred on attempt '.($attemptIndex + 1)." after {$attempt['timeout']}s, trying next attempt if available");

                    // Continue to next attempt if timeout
                    continue;
                }

                // For non-timeout errors, break and return empty result
                LoggingService::log('Non-timeout error on attempt '.($attemptIndex + 1).", stopping attempts: {$errorMessage}");
                break;
            }
        }

        // If we get here, all attempts failed
        LoggingService::log('All Unwrangle API attempts failed', ['asin' => $asin]);

        return [];
    }

    /**
     * Fetch reviews from Amazon using Unwrangle API.
     *
     * @param string $asin    Amazon Standard Identification Number
     * @param string $country Two-letter country code (defaults to 'us')
     *
     * @return array<string, mixed> Array containing reviews, description, and total count
     */
    public function fetchReviews(string $asin, string $country = 'us'): array
    {
        // Use optimized version
        return $this->fetchReviewsOptimized($asin, $country);
    }

    /**
     * Validate ASIN format without hitting Amazon servers.
     */
    private function isValidAsinFormat(string $asin): bool
    {
        // ASIN should be exactly 10 characters, alphanumeric
        return preg_match('/^[A-Z0-9]{10}$/', $asin) === 1;
    }

    /**
     * Check if API response indicates Amazon cookie/session expiration.
     */
    private function isAmazonCookieExpiredError(array $data): bool
    {
        // Direct error code indicating sign-in required
        if (isset($data['error_code']) && $data['error_code'] === 'AMAZON_SIGNIN_REQUIRED') {
            return true;
        }

        // NO_REVIEWS_FOUND with cookie-related message
        if (isset($data['error_code']) && $data['error_code'] === 'NO_REVIEWS_FOUND') {
            $message = $data['message'] ?? '';
            // Check if message mentions cookie issues
            if (str_contains(strtolower($message), 'issue with the cookie') ||
                str_contains(strtolower($message), 'cookie')) {
                return true;
            }
        }

        // Other potential cookie-related error patterns
        if (isset($data['message'])) {
            $message = strtolower($data['message']);
            $cookieIndicators = [
                'session expired',
                'authentication failed',
                'unauthorized access',
                'invalid session',
                'cookie expired',
                'please sign in',
                'login required',
            ];

            foreach ($cookieIndicators as $indicator) {
                if (str_contains($message, $indicator)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function categorizeError(string $errorMessage): string
    {
        if (str_contains($errorMessage, 'cURL error 28')) {
            if (str_contains($errorMessage, '0 bytes received')) {
                return 'CONNECTION_TIMEOUT_NO_DATA';
            }

            return 'READ_TIMEOUT';
        }

        if (str_contains($errorMessage, 'cURL error 7')) {
            return 'CONNECTION_FAILED';
        }

        if (str_contains($errorMessage, 'cURL error 6')) {
            return 'DNS_RESOLUTION_FAILED';
        }

        if (str_contains($errorMessage, 'cURL error 35')) {
            return 'SSL_HANDSHAKE_FAILED';
        }

        if (str_contains($errorMessage, 'HTTP')) {
            return 'HTTP_ERROR';
        }

        return 'UNKNOWN_ERROR';
    }

    private function testApiConnectivity(): bool
    {
        try {
            $startTime = microtime(true);

            // Simple GET request to test connectivity
            $response = $this->httpClient->request('GET', 'https://data.unwrangle.com', [
                'timeout'         => 10,
                'connect_timeout' => 5,
            ]);

            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            LoggingService::log('API connectivity test result', [
                'status'      => $response->getStatusCode(),
                'duration_ms' => $duration,
                'success'     => $response->getStatusCode() < 500,
            ]);

            // Consider it successful if we get any response (even 404 is fine)
            return $response->getStatusCode() < 500;
        } catch (\Exception $e) {
            LoggingService::log('API connectivity test failed', [
                'error'      => $e->getMessage(),
                'error_type' => $this->categorizeError($e->getMessage()),
            ]);

            // If we can't even connect to the base domain, skip the full API attempts
            return false;
        }
    }
}
