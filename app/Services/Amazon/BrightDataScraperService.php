<?php

namespace App\Services\Amazon;

use App\Models\AsinData;
use App\Services\AlertManager;
use App\Services\LoggingService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;

class BrightDataScraperService implements AmazonReviewServiceInterface
{
    private Client $httpClient;
    private string $apiKey;
    private string $datasetId;
    private string $baseUrl;
    private int $pollInterval;
    private int $maxAttempts;

    public function __construct(
        ?Client $httpClient = null,
        ?string $apiKey = null,
        ?string $datasetId = null,
        ?string $baseUrl = null,
        ?int $pollInterval = null,
        ?int $maxAttempts = null
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'timeout'         => 1200, // 20 minutes - BrightData jobs can take a long time
            'connect_timeout' => 30,
            'http_errors'     => false,
        ]);

        $this->apiKey = $apiKey ?? env('BRIGHTDATA_SCRAPER_API', '');
        $this->datasetId = $datasetId ?? env('BRIGHTDATA_DATASET_ID', 'gd_le8e811kzy4ggddlq');
        $this->baseUrl = $baseUrl ?? 'https://api.brightdata.com/datasets/v3';
        $this->pollInterval = $pollInterval ?? (app()->environment('testing') ? 0 : config('amazon.brightdata.polling_interval', 30));
        $this->maxAttempts = $maxAttempts ?? (app()->environment('testing') ? 3 : config('amazon.brightdata.max_polling_attempts', 40));

        if (empty($this->apiKey)) {
            LoggingService::log('BrightData API key not configured', [
                'service'      => 'BrightDataScraperService',
                'checked_vars' => ['BRIGHTDATA_SCRAPER_API'],
            ]);
        }
    }

    /**
     * Set HTTP client for testing.
     */
    public function setHttpClient(Client $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * Fetch reviews using BrightData's managed scraper API.
     */
    public function fetchReviews(string $asin, string $country = 'us'): array
    {
        if (empty($this->apiKey)) {
            LoggingService::log('BrightData API key missing, cannot fetch reviews', [
                'asin'    => $asin,
                'service' => 'BrightDataScraperService',
            ]);

            return [
                'reviews'       => [],
                'description'   => '',
                'total_reviews' => 0,
            ];
        }

        LoggingService::log('Starting BrightData scraping for ASIN', [
            'asin'    => $asin,
            'country' => $country,
            'service' => 'BrightDataScraperService',
        ]);

        try {
            // Construct Amazon product URL
            $productUrl = $this->buildAmazonUrl($asin, $country);

            // Trigger BrightData scraping job
            $jobId = $this->triggerScrapingJob([$productUrl]);

            if (!$jobId) {
                LoggingService::log('Failed to trigger BrightData scraping job', [
                    'asin' => $asin,
                    'url'  => $productUrl,
                ]);

                return [
                    'reviews'       => [],
                    'description'   => '',
                    'total_reviews' => 0,
                ];
            }

            // Poll for results
            $results = $this->pollForResults($jobId, $asin);

            if (empty($results)) {
                LoggingService::log('No results returned from BrightData', [
                    'asin'   => $asin,
                    'job_id' => $jobId,
                ]);

                return [
                    'reviews'       => [],
                    'description'   => '',
                    'total_reviews' => 0,
                ];
            }

            // Transform BrightData format to our internal format
            $transformedData = $this->transformBrightDataResults($results, $asin);

            LoggingService::log('BrightData scraping completed successfully', [
                'asin'          => $asin,
                'reviews_found' => count($transformedData['reviews']),
                'job_id'        => $jobId,
            ]);

            return $transformedData;
        } catch (\Exception $e) {
            LoggingService::log('BrightData scraping failed', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Use context-aware alerting - let AlertManager determine if notification is needed
            app(AlertManager::class)->recordFailure(
                'BrightData Web Scraper',
                'SCRAPING_FAILED',
                $e->getMessage(),
                ['asin' => $asin],
                $e
            );

            return [
                'reviews'       => [],
                'description'   => '',
                'total_reviews' => 0,
            ];
        }
    }

    /**
     * Fetch reviews and save to database using job chain (async).
     */
    public function fetchReviewsAndSave(string $asin, string $country, string $productUrl): AsinData
    {
        // CRITICAL: If we're already running inside a queue job, always use sync mode
        // to avoid dispatching jobs from within jobs
        if (app()->runningInConsole() &&
            str_contains(implode(' ', $_SERVER['argv'] ?? []), 'queue:work')) {
            LoggingService::log('BrightData forced to sync mode - running inside queue worker', [
                'asin'    => $asin,
                'country' => $country,
                'argv'    => $_SERVER['argv'] ?? [],
            ]);

            return $this->fetchReviewsSync($asin, $country);
        }

        // For async processing, dispatch the job chain
        $asyncEnabled = config('analysis.async_enabled') ??
                       filter_var(env('ANALYSIS_ASYNC_ENABLED'), FILTER_VALIDATE_BOOLEAN) ??
                       (env('APP_ENV') === 'production');

        if ($asyncEnabled) {
            return $this->fetchReviewsAsync($asin, $country);
        }

        // Synchronous fallback for testing or when async is disabled
        return $this->fetchReviewsSync($asin, $country);
    }

    /**
     * Fetch reviews asynchronously using job chain.
     */
    public function fetchReviewsAsync(string $asin, string $country): AsinData
    {
        LoggingService::log('Starting BrightData async job chain', [
            'asin'    => $asin,
            'country' => $country,
        ]);

        // Create or get existing AsinData record and set it to processing for async mode
        $asinData = AsinData::firstOrCreate(
            ['asin' => $asin, 'country' => $country],
            ['status' => 'processing']
        );

        // CRITICAL: Never overwrite a completed analysis
        if ($asinData->isAnalyzed()) {
            LoggingService::log('BrightData async skipped - analysis already complete', [
                'asin'   => $asin,
                'status' => $asinData->status,
                'grade'  => $asinData->grade,
            ]);

            return $asinData;
        }

        // Set to processing status for async mode
        if ($asinData->status !== 'processing') {
            $asinData->update(['status' => 'processing']);
        }

        // Dispatch the job chain
        \App\Jobs\TriggerBrightDataScraping::dispatch($asin, $country);

        return $asinData;
    }

    /**
     * Fetch reviews synchronously (for testing or fallback).
     */
    private function fetchReviewsSync(string $asin, string $country): AsinData
    {
        $result = $this->fetchReviews($asin, $country);

        // Empty reviews is a valid result (product has no reviews), not an error
        // Let ProductAnalysisPolicy handle the "no reviews" scenario gracefully

        // Save to database using existing columns only
        $asinData = AsinData::firstOrCreate(
            ['asin' => $asin, 'country' => $country],
            ['status' => 'pending_analysis']
        );

        $asinData->reviews = json_encode($result['reviews']);
        $asinData->product_description = $result['description'] ?? '';
        $asinData->total_reviews_on_amazon = $result['total_reviews'] ?? count($result['reviews']);
        $asinData->country = $country;
        $asinData->status = 'pending_analysis';

        // Extract product data if available from BrightData
        $hasProductTitle = false;
        $hasProductImage = false;

        if (!empty($result['product_name'])) {
            $asinData->product_title = $result['product_name'];
            $hasProductTitle = true;
        }
        if (!empty($result['product_image_url'])) {
            $asinData->product_image_url = $result['product_image_url'];
            $hasProductImage = true;
        }

        // Only set have_product_data = true if we actually have both title and image
        // If BrightData doesn't provide complete product metadata, let AmazonProductDataService handle it
        $asinData->have_product_data = $hasProductTitle && $hasProductImage;

        LoggingService::log('BrightData product data extraction results', [
            'asin'                           => $asin,
            'has_product_title'              => $hasProductTitle,
            'has_product_image'              => $hasProductImage,
            'have_product_data'              => $asinData->have_product_data,
            'will_trigger_separate_scraping' => !$asinData->have_product_data,
        ]);

        $asinData->save();

        return $asinData;
    }

    /**
     * Fetch product data using BrightData.
     */
    public function fetchProductData(string $asin, string $country = 'us'): array
    {
        $result = $this->fetchReviews($asin, $country);

        return [
            'title'         => $result['product_name'] ?? '',
            'description'   => $result['description'] ?? '',
            'price'         => $result['price'] ?? '',
            'image_url'     => $result['product_image_url'] ?? '',
            'rating'        => 0, // Rating not available from BrightData review data
            'total_reviews' => $result['total_reviews'] ?? 0,
        ];
    }

    /**
     * Build Amazon product URL for the given ASIN and country.
     */
    private function buildAmazonUrl(string $asin, string $country): string
    {
        $domains = [
            'us' => 'amazon.com',
            'gb' => 'amazon.co.uk',  // Fixed: use 'gb' to match ReviewService
            'uk' => 'amazon.co.uk',  // Keep 'uk' for backward compatibility
            'ca' => 'amazon.ca',
            'de' => 'amazon.de',
            'fr' => 'amazon.fr',
            'it' => 'amazon.it',
            'es' => 'amazon.es',
            'ie' => 'amazon.ie',
            'jp' => 'amazon.co.jp',
            'au' => 'amazon.com.au',
            // Additional countries for expanded support
            'mx' => 'amazon.com.mx',  // Mexico
            'in' => 'amazon.in',      // India
            'sg' => 'amazon.sg',      // Singapore
            'br' => 'amazon.com.br',  // Brazil
            // Already supported by ReviewService
            'nl' => 'amazon.nl',
            'tr' => 'amazon.com.tr',
            'ae' => 'amazon.ae',
            'sa' => 'amazon.sa',
            'se' => 'amazon.se',
            'pl' => 'amazon.pl',
            'eg' => 'amazon.eg',
            'be' => 'amazon.be',
        ];

        $domain = $domains[$country] ?? $domains['us'];

        return "https://www.{$domain}/dp/{$asin}/";
    }

    /**
     * Trigger a BrightData scraping job.
     */
    private function triggerScrapingJob(array $urls): ?string
    {
        // Check if we're approaching the concurrent job limit
        if (!$this->canCreateNewJob()) {
            LoggingService::log('BrightData job creation blocked - too many concurrent jobs', [
                'urls_count' => count($urls),
                'reason'     => 'Approaching concurrent job limit',
            ]);

            return null;
        }

        try {
            $payload = array_map(function ($url) {
                return ['url' => $url];
            }, $urls);

            // Get review limit from environment variable
            $maxReviews = (int) env('BRIGHTDATA_MAX_REVIEWS', 200);

            LoggingService::log('Triggering BrightData scraping job with review limit', [
                'urls_count'  => count($urls),
                'dataset_id'  => $this->datasetId,
                'max_reviews' => $maxReviews,
                'payload'     => $payload,
            ]);

            $queryParams = [
                'dataset_id'     => $this->datasetId,
                'include_errors' => 'true',
            ];

            // Add limit_multiple_results parameter for cost control
            if ($maxReviews > 0) {
                $queryParams['limit_multiple_results'] = $maxReviews;
            }

            $response = $this->httpClient->post("{$this->baseUrl}/trigger", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type'  => 'application/json',
                ],
                'query' => $queryParams,
                'json'  => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                LoggingService::log('BrightData job trigger failed', [
                    'status_code'   => $statusCode,
                    'response_body' => $body,
                ]);

                // Check if this is the "too many running jobs" error
                if ($statusCode === 429 && strpos($body, 'too many running jobs') !== false) {
                    LoggingService::log('BrightData rate limit hit - too many running jobs', [
                        'status_code'   => $statusCode,
                        'response_body' => $body,
                        'suggestion'    => 'Run: php artisan brightdata:analyze to see job distribution',
                    ]);

                    // Use AlertManager for rate limit issues
                    app(AlertManager::class)->recordFailure(
                        'BrightData API',
                        'RATE_LIMIT_EXCEEDED',
                        'Too many running jobs (100+ limit reached)',
                        [
                            'status_code'   => $statusCode,
                            'response'      => $body,
                            'action_needed' => 'Cancel old running jobs',
                        ]
                    );
                }

                return null;
            }

            $data = json_decode($body, true);
            $jobId = $data['snapshot_id'] ?? null;

            LoggingService::log('BrightData job triggered successfully', [
                'job_id'   => $jobId,
                'response' => $data,
            ]);

            return $jobId;
        } catch (RequestException $e) {
            LoggingService::log('BrightData job trigger request failed', [
                'error'    => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null,
            ]);

            // Use context-aware alerting for API trigger failures
            app(AlertManager::class)->recordFailure(
                'BrightData API',
                'JOB_TRIGGER_FAILED',
                $e->getMessage(),
                [
                    'dataset_id' => $this->datasetId,
                    'urls_count' => count($urls),
                    'response'   => $e->hasResponse() ? substr($e->getResponse()->getBody()->getContents(), 0, 200) : null,
                ],
                $e
            );

            return null;
        }
    }

    /**
     * Poll BrightData for job results.
     */
    private function pollForResults(string $jobId, string $asin): array
    {
        $attempt = 0;

        LoggingService::log('Starting to poll for BrightData results', [
            'job_id'        => $jobId,
            'asin'          => $asin,
            'max_attempts'  => $this->maxAttempts,
            'poll_interval' => $this->pollInterval,
        ]);

        while ($attempt < $this->maxAttempts) {
            try {
                // Use the correct progress API endpoint to check job status
                $response = $this->httpClient->get("{$this->baseUrl}/progress/{$jobId}", [
                    'headers' => [
                        'Authorization' => "Bearer {$this->apiKey}",
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();

                if ($statusCode !== 200) {
                    LoggingService::log('BrightData progress check failed', [
                        'job_id'        => $jobId,
                        'attempt'       => $attempt + 1,
                        'status_code'   => $statusCode,
                        'response_body' => substr($body, 0, 500),
                    ]);

                    $attempt++;
                    if ($this->pollInterval > 0) {
                        sleep($this->pollInterval);
                    }
                    continue;
                }

                $progressData = json_decode($body, true);
                $status = $progressData['status'] ?? 'unknown';

                LoggingService::log('BrightData job progress check', [
                    'job_id'     => $jobId,
                    'attempt'    => $attempt + 1,
                    'status'     => $status,
                    'total_rows' => $progressData['records'] ?? 0,
                ]);

                if ($status === 'ready') {
                    // Job completed successfully, fetch the actual data
                    return $this->fetchJobData($jobId);
                } elseif ($status === 'failed' || $status === 'error') {
                    LoggingService::log('BrightData job failed', [
                        'job_id'        => $jobId,
                        'status'        => $status,
                        'progress_data' => $progressData,
                    ]);

                    return [];
                } elseif ($status === 'running') {
                    LoggingService::log('BrightData job still running', [
                        'job_id'              => $jobId,
                        'attempt'             => $attempt + 1,
                        'max_attempts'        => $this->maxAttempts,
                        'time_elapsed'        => ($attempt * $this->pollInterval).'s',
                        'estimated_remaining' => (($this->maxAttempts - $attempt) * $this->pollInterval).'s',
                    ]);

                    $attempt++;
                    if ($this->pollInterval > 0) {
                        sleep($this->pollInterval);
                    }
                    continue;
                }

                // Unknown status - continue polling
                $attempt++;
                sleep($this->pollInterval);
            } catch (RequestException $e) {
                LoggingService::log('BrightData polling request failed', [
                    'job_id'  => $jobId,
                    'attempt' => $attempt + 1,
                    'error'   => $e->getMessage(),
                ]);

                // Record polling failure - AlertManager handles pattern detection automatically
                app(AlertManager::class)->recordFailure(
                    'BrightData API',
                    'POLLING_FAILED',
                    $e->getMessage(),
                    [
                        'job_id'       => $jobId,
                        'attempt'      => $attempt + 1,
                        'max_attempts' => $this->maxAttempts,
                    ],
                    $e
                );

                $attempt++;
                sleep($this->pollInterval);
            }
        }

        // Get final status before giving up
        $finalProgressInfo = $this->getJobProgressInfo($jobId);

        LoggingService::log('BrightData polling timeout - attempting to cancel job', [
            'job_id'       => $jobId,
            'asin'         => $asin,
            'max_attempts' => $this->maxAttempts,
            'total_time'   => ($this->maxAttempts * $this->pollInterval).'s',
            'final_status' => $finalProgressInfo['status'] ?? 'unknown',
            'final_rows'   => $finalProgressInfo['total_rows'] ?? 0,
        ]);

        // Cancel the job after polling timeout to prevent accumulation
        $cancelSuccess = $this->cancelJob($jobId);

        LoggingService::log('BrightData job cancellation after timeout', [
            'job_id'         => $jobId,
            'cancel_success' => $cancelSuccess,
            'reason'         => 'polling_timeout',
        ]);

        // Record timeout failure - AlertManager will determine appropriate response
        app(AlertManager::class)->recordFailure(
            'BrightData Web Scraper',
            'POLLING_TIMEOUT',
            "Job polling timed out after {$this->maxAttempts} attempts, cancellation ".($cancelSuccess ? 'successful' : 'failed'),
            [
                'job_id'           => $jobId,
                'asin'             => $asin,
                'timeout_duration' => $this->maxAttempts * $this->pollInterval,
                'max_attempts'     => $this->maxAttempts,
                'final_status'     => $finalProgressInfo['status'] ?? 'unknown',
                'final_rows'       => $finalProgressInfo['total_rows'] ?? 0,
                'cancel_success'   => $cancelSuccess,
            ]
        );

        return [];
    }

    /**
     * Fetch the actual job data from BrightData.
     */
    private function fetchJobData(string $jobId): array
    {
        $maxRetries = 3;
        $retryDelay = 30; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->httpClient->get("{$this->baseUrl}/snapshot/{$jobId}", [
                    'headers' => [
                        'Authorization' => "Bearer {$this->apiKey}",
                    ],
                    'query' => [
                        'format' => 'json',
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();

                if ($statusCode === 200) {
                    $data = json_decode($body, true);

                    LoggingService::log('BrightData data fetched successfully', [
                        'job_id'        => $jobId,
                        'records_count' => is_array($data) ? count($data) : 0,
                        'attempt'       => $attempt,
                    ]);

                    return is_array($data) ? $data : [];
                } elseif ($statusCode === 202) {
                    // Snapshot is still building
                    $responseData = json_decode($body, true);
                    $status = $responseData['status'] ?? 'unknown';
                    $message = $responseData['message'] ?? 'Snapshot building';

                    LoggingService::log('BrightData snapshot still building', [
                        'job_id'           => $jobId,
                        'attempt'          => $attempt,
                        'max_retries'      => $maxRetries,
                        'status'           => $status,
                        'message'          => $message,
                        'retry_in_seconds' => $retryDelay,
                    ]);

                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                        continue;
                    } else {
                        LoggingService::log('BrightData snapshot build timeout', [
                            'job_id'          => $jobId,
                            'max_retries'     => $maxRetries,
                            'total_wait_time' => $maxRetries * $retryDelay,
                        ]);

                        return [];
                    }
                } else {
                    LoggingService::log('BrightData data fetch failed', [
                        'job_id'        => $jobId,
                        'status_code'   => $statusCode,
                        'response_body' => substr($body, 0, 500),
                        'attempt'       => $attempt,
                    ]);

                    return [];
                }
            } catch (RequestException $e) {
                LoggingService::log('BrightData data fetch request failed', [
                    'job_id'  => $jobId,
                    'error'   => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt === $maxRetries) {
                    return [];
                }

                sleep($retryDelay);
            }
        }

        return [];
    }

    /**
     * Transform BrightData results to our internal format.
     */
    private function transformBrightDataResults(array $results, string $asin): array
    {
        $reviews = [];
        $productName = '';
        $productRating = 0;
        $totalReviews = 0;
        $productImageUrl = '';
        $description = '';

        foreach ($results as $item) {
            // Extract product-level data from first item
            if (empty($productName) && !empty($item['product_name'])) {
                $productName = $item['product_name'];
                $totalReviews = $item['product_rating_count'] ?? 0;

                // Extract product image URL if provided by BrightData
                if (empty($productImageUrl) && !empty($item['product_image_url'])) {
                    $productImageUrl = $item['product_image_url'];
                }
            }

            // Transform review data - BrightData provides very rich data
            if (!empty($item['review_text']) && !empty($item['review_id'])) {
                $review = [
                    'id'        => $item['review_id'],
                    'rating'    => $item['rating'] ?? 0,
                    'title'     => $item['review_header'] ?? '',
                    'text'      => $item['review_text'], // Use 'text' field for consistency with other services
                    'author'    => $item['author_name'] ?? 'Anonymous',
                    'date'      => $item['review_posted_date'] ?? '',
                    'meta_data' => [
                        'verified_purchase' => $item['is_verified'] ?? false,
                        'helpful_count'     => $item['helpful_count'] ?? 0,
                        'vine_review'       => $item['is_amazon_vine'] ?? false,
                        'country'           => $item['review_country'] ?? '',
                        'badge'             => $item['badge'] ?? '',
                        'author_id'         => $item['author_id'] ?? '',
                        'author_link'       => $item['author_link'] ?? '',
                        'variant_asin'      => $item['variant_asin'] ?? null,
                        'variant_name'      => $item['variant_name'] ?? null,
                        'brand'             => $item['brand'] ?? '',
                        'timestamp'         => $item['timestamp'] ?? '',
                    ],
                ];

                // Add image URLs if available
                if (!empty($item['review_images']) && is_array($item['review_images'])) {
                    $review['images'] = $item['review_images'];
                }

                // Add video URLs if available
                if (!empty($item['videos']) && is_array($item['videos'])) {
                    $review['videos'] = $item['videos'];
                }

                $reviews[] = $review;
            }
        }

        LoggingService::log('BrightData results transformed', [
            'asin'                    => $asin,
            'total_items'             => count($results),
            'reviews_extracted'       => count($reviews),
            'product_name'            => $productName,
            'total_reviews_on_amazon' => $totalReviews,
        ]);

        return [
            'reviews'           => $reviews,
            'description'       => $description,
            'total_reviews'     => $totalReviews,
            'product_name'      => $productName,
            'product_image_url' => $productImageUrl,
        ];
    }

    /**
     * Check BrightData progress for all jobs.
     */
    public function checkProgress(): array
    {
        try {
            $response = $this->httpClient->get("{$this->baseUrl}/progress/", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                LoggingService::log('BrightData progress check failed', [
                    'status_code'   => $statusCode,
                    'response_body' => substr($body, 0, 500),
                ]);

                return [];
            }

            $data = json_decode($body, true);

            LoggingService::log('BrightData progress check successful', [
                'active_jobs' => is_array($data) ? count($data) : 0,
            ]);

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            LoggingService::log('BrightData progress check error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get detailed progress information for a specific job.
     */
    private function getJobProgressInfo(string $jobId): array
    {
        try {
            $response = $this->httpClient->get("{$this->baseUrl}/progress/{$jobId}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $progressData = json_decode($response->getBody()->getContents(), true);

                // Response is directly for the specific job
                if (is_array($progressData)) {
                    return [
                        'status'     => $progressData['status'] ?? 'unknown',
                        'total_rows' => $progressData['records'] ?? 0,
                        'created_at' => $progressData['created_at'] ?? null,
                        'updated_at' => $progressData['updated_at'] ?? null,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Don't log progress API errors as they're supplemental
        }

        return ['status' => 'unknown', 'total_rows' => 0];
    }

    /**
     * Check if we can create a new BrightData job based on current limits.
     */
    private function canCreateNewJob(): bool
    {
        $maxConcurrent = config('amazon.brightdata.max_concurrent_jobs', 90);
        $alertThreshold = 70; // Alert when we have 70+ running jobs

        try {
            $runningJobs = $this->getJobsByStatus('running');
            $currentCount = count($runningJobs);

            LoggingService::log('BrightData concurrent job check', [
                'current_running' => $currentCount,
                'max_allowed'     => $maxConcurrent,
                'alert_threshold' => $alertThreshold,
                'can_create'      => $currentCount < $maxConcurrent,
            ]);

            // Alert if we're approaching the limit
            if ($currentCount >= $alertThreshold) {
                app(AlertManager::class)->recordFailure(
                    'BrightData Job Management',
                    'HIGH_CONCURRENT_JOBS',
                    "High number of running BrightData jobs: {$currentCount}/100 limit",
                    [
                        'current_running_jobs' => $currentCount,
                        'alert_threshold'      => $alertThreshold,
                        'max_limit'            => 100,
                        'recommendation'       => 'Consider running: php artisan brightdata:cleanup --force',
                        'jobs_until_limit'     => 100 - $currentCount,
                    ]
                );
            }

            return $currentCount < $maxConcurrent;
        } catch (\Exception $e) {
            LoggingService::log('Failed to check concurrent job limit', [
                'error'    => $e->getMessage(),
                'fallback' => 'Allowing job creation',
            ]);

            // If we can't check, allow job creation to avoid blocking legitimate requests
            return true;
        }
    }

    /**
     * Get jobs by status from BrightData API.
     */
    private function getJobsByStatus(string $status): array
    {
        try {
            $response = $this->httpClient->get("{$this->baseUrl}/snapshots", [
                'headers' => ['Authorization' => "Bearer {$this->apiKey}"],
                'query'   => ['dataset_id' => $this->datasetId, 'status' => $status],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            LoggingService::log('Failed to fetch jobs by status', [
                'status' => $status,
                'error'  => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Cancel a BrightData job by ID using the official API endpoint.
     */
    public function cancelJob(string $jobId): bool
    {
        try {
            // Use the correct BrightData API endpoint for cancellation
            $response = $this->httpClient->post("{$this->baseUrl}/snapshot/{$jobId}/cancel", [
                'headers' => ['Authorization' => "Bearer {$this->apiKey}"],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // BrightData returns 200 with "OK" response for successful cancellation
            $success = $statusCode === 200 && trim($body) === 'OK';

            LoggingService::log('BrightData job cancellation attempt', [
                'job_id'      => $jobId,
                'success'     => $success,
                'status_code' => $statusCode,
                'response'    => $body,
                'endpoint'    => "{$this->baseUrl}/snapshot/{$jobId}/cancel",
            ]);

            return $success;
        } catch (\Exception $e) {
            LoggingService::log('BrightData job cancellation failed', [
                'job_id'   => $jobId,
                'error'    => $e->getMessage(),
                'endpoint' => "{$this->baseUrl}/snapshot/{$jobId}/cancel",
            ]);

            return false;
        }
    }

    /**
     * Cancel stale jobs based on configuration.
     */
    public function cancelStaleJobs(): array
    {
        $autoCancelEnabled = config('amazon.brightdata.auto_cancel_enabled', true);
        if (!$autoCancelEnabled) {
            return ['message' => 'Auto-cancellation disabled'];
        }

        $staleThreshold = config('amazon.brightdata.stale_job_threshold', 60); // minutes
        $cutoffTime = now()->subMinutes($staleThreshold);

        try {
            $runningJobs = $this->getJobsByStatus('running');
            $staleCandidates = [];

            foreach ($runningJobs as $job) {
                $jobId = $job['id'] ?? null;
                $createdAt = $job['created_at'] ?? null;

                if (!$jobId || !$createdAt) {
                    continue;
                }

                try {
                    $jobCreatedAt = \Carbon\Carbon::parse($createdAt);
                    if ($jobCreatedAt->lt($cutoffTime)) {
                        $staleCandidates[] = [
                            'id'          => $jobId,
                            'created_at'  => $createdAt,
                            'age_minutes' => now()->diffInMinutes($jobCreatedAt),
                        ];
                    }
                } catch (\Exception $e) {
                    LoggingService::log('Could not parse job creation date', [
                        'job_id'     => $jobId,
                        'created_at' => $createdAt,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $canceled = [];
            $failed = [];

            foreach ($staleCandidates as $job) {
                if ($this->cancelJob($job['id'])) {
                    $canceled[] = $job;
                } else {
                    $failed[] = $job;
                }

                // Small delay to avoid overwhelming the API
                usleep(500000); // 0.5 seconds
            }

            LoggingService::log('BrightData stale job cleanup completed', [
                'stale_threshold_minutes' => $staleThreshold,
                'candidates_found'        => count($staleCandidates),
                'successfully_canceled'   => count($canceled),
                'failed_to_cancel'        => count($failed),
            ]);

            return [
                'stale_threshold_minutes' => $staleThreshold,
                'candidates_found'        => count($staleCandidates),
                'canceled'                => $canceled,
                'failed'                  => $failed,
            ];
        } catch (\Exception $e) {
            LoggingService::log('BrightData stale job cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            return ['error' => $e->getMessage()];
        }
    }
}
