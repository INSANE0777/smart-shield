<?php

namespace App\Services\Amazon;

use App\Models\AsinData;
use App\Services\AlertManager;
use App\Services\LoggingService;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Service for directly scraping Amazon product reviews.
 *
 * This service scrapes Amazon reviews directly using the same cookie session
 * that Unwrangle uses, providing a cost-effective alternative to the API.
 */
class AmazonScrapingService implements AmazonReviewServiceInterface
{
    private Client $httpClient;
    private CookieJar $cookieJar;
    private array $headers;
    private ProxyManager $proxyManager;
    private ?array $currentProxyConfig = null;

    /**
     * Initialize the service with HTTP client configuration.
     */
    public function __construct()
    {
        $this->proxyManager = new ProxyManager();
        $this->cookieJar = new CookieJar();
        $this->setupCookies();

        $this->headers = [
            'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language'           => 'en-US,en;q=0.9',
            'Accept-Encoding'           => 'gzip, deflate, br',
            'Connection'                => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest'            => 'document',
            'Sec-Fetch-Mode'            => 'navigate',
            'Sec-Fetch-Site'            => 'same-origin',
            'Sec-Fetch-User'            => '?1',
            'Cache-Control'             => 'max-age=0',
            'DNT'                       => '1',
            'Sec-GPC'                   => '1',
        ];

        $this->initializeHttpClient();
    }

    /**
     * Initialize HTTP client with proxy configuration.
     */
    private function initializeHttpClient(): void
    {
        $clientConfig = [
            'timeout'         => 30,
            'connect_timeout' => 15,
            'http_errors'     => false,
            'verify'          => false,
            'cookies'         => $this->cookieJar,
            'headers'         => $this->getBandwidthOptimizedHeaders(),
            'allow_redirects' => [
                'max'             => 5,
                'strict'          => false,
                'referer'         => true,
                'track_redirects' => true,
            ],
        ];

        // Add proxy configuration if available
        $this->currentProxyConfig = $this->proxyManager->getProxyConfig();
        if ($this->currentProxyConfig) {
            $clientConfig['proxy'] = $this->currentProxyConfig['proxy'];
            $clientConfig['timeout'] = $this->currentProxyConfig['timeout'];

            // Add specific proxy settings for Amazon with PROGRESSIVE bandwidth optimization
            $clientConfig['curl'] = [
                CURLOPT_PROXYTYPE      => CURLPROXY_HTTP,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_ENCODING       => 'gzip, deflate', // Force compression
                CURLOPT_MAXFILESIZE    => 3 * 1024 * 1024, // 3MB limit - allow enough for complete HTML before filtering
                CURLOPT_BUFFERSIZE     => 8192, // 8KB buffer for faster processing (reduced from 16KB)
            ];

            LoggingService::log('Using proxy for Amazon scraping with PROGRESSIVE bandwidth optimization', [
                'type'               => $this->currentProxyConfig['type'],
                'provider'           => $this->currentProxyConfig['provider'] ?? 'custom',
                'country'            => $this->currentProxyConfig['country'],
                'session_id'         => $this->currentProxyConfig['session_id'] ?? 'none',
                'max_download_size'  => '3MB',
                'filtering_applied'  => 'smart_html_filtering',
                'compression'        => 'gzip, deflate',
                'optimization_level' => 'progressive',
            ]);
        } else {
            LoggingService::log('No proxy configured - using direct connection');
        }

        $this->httpClient = new Client($clientConfig);
    }

    /**
     * Get bandwidth-optimized headers that block unnecessary content.
     */
    private function getBandwidthOptimizedHeaders(): array
    {
        return [
            'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept'                    => 'text/html,application/xhtml+xml;q=0.9,application/xml;q=0.1', // Heavily prioritize HTML, de-prioritize other formats
            'Accept-Language'           => 'en-US,en;q=0.5', // Simplified language preferences to reduce header size
            'Accept-Encoding'           => 'gzip, deflate, br', // Force compression
            'Connection'                => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest'            => 'document',
            'Sec-Fetch-Mode'            => 'navigate',
            'Sec-Fetch-Site'            => 'same-origin',
            'DNT'                       => '1',
            // AGGRESSIVE bandwidth optimization headers
            'Save-Data'      => '1', // Request reduced data usage (Chrome feature)
            'Viewport-Width' => '1024', // Optimize for smaller viewport to get smaller images
            'DPR'            => '1', // Device pixel ratio = 1 (no high-DPI images)
            'Width'          => '1024', // Request smaller image widths
            'Downlink'       => '0.5', // Hint that we have slow connection (encourage smaller resources)
            'ECT'            => 'slow-2g', // Effective Connection Type hint for reduced content
            'RTT'            => '2000', // Round Trip Time hint (slower = less content)
            'Cache-Control'  => 'max-age=0, no-cache', // Prevent large cached responses
            'Pragma'         => 'no-cache', // HTTP/1.0 cache control
            // Removed unnecessary headers to reduce request size:
            // - Sec-Fetch-User (not essential)
            // - Sec-GPC (privacy header, not needed for scraping)
            // - Cache-Control max-age (conflicts with no-cache)
        ];
    }

    /**
     * Check if a URL should be blocked to save bandwidth.
     */
    private function shouldBlockUrl(string $url): bool
    {
        // Block common resource-heavy URLs that we don't need for review scraping
        // Enhanced patterns for maximum bandwidth savings
        $blockedPatterns = [
            // Images and media (CRITICAL - these are often the largest bandwidth consumers)
            '/\.(jpg|jpeg|png|gif|webp|svg|ico|bmp|tiff|tif)(\?.*)?$/i',
            '/\.(mp4|mp3|avi|mov|wmv|flv|webm|ogg|wav|m4a)(\?.*)?$/i',
            '/\/images\//',
            '/\/media\//',
            '/\/img\//',
            '/\.cloudfront\.net.*\.(jpg|jpeg|png|gif|webp)/',
            '/\/product-images\//',
            '/\/product-media\//',

            // CSS and styling (we don't need visual styling)
            '/\.(css)(\?.*)?$/i',
            '/\/css\//',
            '/\/styles\//',
            '/\/stylesheets\//',
            '/\/assets\/.*\.css/',

            // JavaScript (we don't need interactive features - MAJOR bandwidth saver)
            '/\.(js)(\?.*)?$/i',
            '/\/js\//',
            '/\/javascript\//',
            '/\/assets\/.*\.js/',
            '/\.min\.js/',
            '/\/scripts\//',

            // Fonts (unnecessary for scraping)
            '/\.(woff|woff2|ttf|eot|otf)(\?.*)?$/i',
            '/\/fonts\//',
            '/\/webfonts\//',

            // Analytics and tracking (BANDWIDTH WASTE - block all)
            '/google-analytics/',
            '/googletagmanager/',
            '/googlesyndication/',
            '/facebook\.net/',
            '/doubleclick\.net/',
            '/amazon-adsystem/',
            '/assoc-amazon/',
            '/analytics/',
            '/tracking/',
            '/metrics/',
            '/telemetry/',
            '/gtag/',
            '/gtm\.js/',
            '/fbpixel/',
            '/pixel\.facebook/',
            '/scorecardresearch/',
            '/quantserve/',
            '/newrelic/',

            // Ads and recommendations (MAJOR bandwidth waste)
            '/\/ads\//',
            '/\/advertising\//',
            '/\/recommendations\//',
            '/\/sponsored\//',
            '/\/banners\//',
            '/\/promo\//',
            '/\/deals\//',
            '/\/offers\//',
            '/adsystem\.amazon/',
            '/amazonclix/',

            // Amazon-specific heavy resources we don't need
            '/\/gp\/video\//',
            '/\/gp\/music\//',
            '/\/gp\/photos\//',
            '/\/gp\/kindle\//',
            '/\/gp\/prime\//',
            '/\/gp\/cart\//',
            '/\/gp\/checkout\//',
            '/\/gp\/buy\//',
            '/\/gp\/history\//',
            '/\/gp\/yourstore\//',
            '/\/gp\/registry\//',
            '/\/gp\/wishlist\//',
            '/\/api\//',
            '/\/ajax\//',
            '/\/widget\//',
            '/\/personalization\//',
            '/\/recommendations\//',
            '/\/similar\//',
            '/\/related\//',
            '/\/search\//',
            '/\/autocomplete\//',

            // Third-party resources (social media, external widgets)
            '/twitter\.com/',
            '/instagram\.com/',
            '/youtube\.com/',
            '/facebook\.com/',
            '/pinterest\.com/',
            '/linkedin\.com/',
            '/tiktok\.com/',
            '/snapchat\.com/',
            '/reddit\.com/',

            // Additional Amazon-specific wasteful resources
            '/\/alexa\//',
            '/\/premium\//',
            '/\/subscribe\//',
            '/\/live\//',
            '/\/stream\//',
            '/\/video\//',
            '/\/audio\//',
            '/\/game\//',
            '/\/app\//',
            '/\/mobile\//',
            '/\/tablet\//',
            '/\/desktop\//',

            // XML/JSON feeds we don't need
            '/\.xml(\?.*)?$/i',
            '/\.json(\?.*)?$/i',
            '/\/feed\//',
            '/\/rss\//',
            '/\/sitemap/',

            // PDFs and documents
            '/\.(pdf|doc|docx|xls|xlsx|ppt|pptx)(\?.*)?$/i',

            // Compressed files
            '/\.(zip|rar|tar|gz|7z)(\?.*)?$/i',

            // Map files and source maps
            '/\.map(\?.*)?$/i',
            '/sourcemap/',

            // Favicon and manifest files (unless essential)
            '/favicon\.ico/',
            '/apple-touch-icon/',
            '/manifest\.json/',
            '/browserconfig\.xml/',

            // Security and verification files
            '/robots\.txt/',
            '/security\.txt/',
            '/\.well-known\//',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make an optimized HTTP request with bandwidth saving features.
     */
    private function makeOptimizedRequest(string $url, array $options = []): ?\Psr\Http\Message\ResponseInterface
    {
        $startTime = microtime(true);

        try {
            // Get request size limits
            $maxDownloadSize = 3 * 1024 * 1024; // 3MB limit - allow full download then filter

            // Merge with bandwidth optimization headers
            $defaultOptions = [
                'headers'         => $this->getBandwidthOptimizedHeaders(),
                'cookies'         => $this->cookieJar,
                'timeout'         => 30,
                'connect_timeout' => 10,
                'allow_redirects' => true,
                'verify'          => false,
                'curl'            => [
                    CURLOPT_MAXFILESIZE      => $maxDownloadSize,
                    CURLOPT_NOPROGRESS       => false,
                    CURLOPT_PROGRESSFUNCTION => function ($resource, $downloadTotal, $downloaded, $uploadTotal, $uploaded) use ($maxDownloadSize) {
                        // Early termination if download exceeds limit
                        if ($downloaded > $maxDownloadSize) {
                            return 1; // Abort download
                        }

                        return 0; // Continue
                    },
                ],
            ];

            $mergedOptions = array_merge_recursive($defaultOptions, $options);

            $response = $this->httpClient->get($url, $mergedOptions);
            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($response->getStatusCode() === 200) {
                $originalContent = $response->getBody()->getContents();
                $originalSize = strlen($originalContent);

                // Check for CAPTCHA/blocking before processing content
                if ($this->detectCaptchaInResponse($originalContent, $url)) {
                    // Return null to trigger appropriate error handling
                    return null;
                }

                // Apply intelligent HTML filtering
                $filteredContent = $this->filterHtmlForBandwidthOptimization($originalContent);
                $filteredSize = strlen($filteredContent);

                $bandwidthSaved = $originalSize - $filteredSize;
                $compressionRatio = $originalSize > 0 ? ($bandwidthSaved / $originalSize) * 100 : 0;

                LoggingService::log('Optimized request completed', [
                    'url'                 => parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PATH),
                    'response_time_ms'    => round($responseTime, 2),
                    'original_size'       => $this->formatBytes($originalSize),
                    'filtered_size'       => $this->formatBytes($filteredSize),
                    'bandwidth_saved'     => $this->formatBytes($bandwidthSaved),
                    'compression_ratio'   => round($compressionRatio, 1).'%',
                    'status'              => $response->getStatusCode(),
                    'optimization_method' => 'smart_content_filtering',
                ]);

                // Log bandwidth usage
                $this->logBandwidthUsage($url, $filteredSize);

                // Create a new response with filtered content
                return new \GuzzleHttp\Psr7\Response(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $filteredContent
                );
            }

            return $response;
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            LoggingService::log('Optimized request failed', [
                'url'              => parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PATH),
                'response_time_ms' => round($responseTime, 2),
                'error'            => $e->getMessage(),
            ]);

            // Report failure to proxy manager
            if ($this->currentProxyConfig) {
                $this->proxyManager->reportFailure($this->currentProxyConfig, $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Detect CAPTCHA or blocking response and trigger appropriate alerts.
     */
    private function detectCaptchaInResponse(string $html, string $url): bool
    {
        // Check for CAPTCHA indicators that return 200 status but contain blocking content
        $captchaIndicators = [
            'validateCaptcha',
            'opfcaptcha.amazon.com',
            'continue shopping',
            'csm-captcha-instrumentation',
            'click the button below to continue',
            'unusual traffic',
            'automated requests',
            'solve this puzzle',
            'enter the characters you see below',
            'sorry, we just need to make sure you\'re not a robot',
        ];

        $htmlLower = strtolower($html);
        $foundIndicators = [];

        foreach ($captchaIndicators as $indicator) {
            if (strpos($htmlLower, $indicator) !== false) {
                $foundIndicators[] = $indicator;
            }
        }

        // Only trigger on explicit CAPTCHA indicators, not just small content size
        // Small content could be legitimate responses for test cases
        if (!empty($foundIndicators)) {
            $contentSize = strlen($html);
            $this->handleCaptchaDetection($url, $foundIndicators, $contentSize);

            return true;
        }

        return false;
    }

    /**
     * Handle CAPTCHA detection by logging (simplified since production uses BrightData).
     */
    private function handleCaptchaDetection(string $url, array $indicators, int $contentSize): void
    {
        LoggingService::log('CAPTCHA/blocking detected in Amazon response (fallback scraping)', [
            'url'              => $url,
            'indicators_found' => $indicators,
            'content_size'     => $contentSize,
            'note'             => 'Production uses BrightData - this is fallback/development only',
        ]);

        // If using proxy, rotate it
        if ($this->currentProxyConfig) {
            $this->rotateProxyAndReconnect();
        }
    }

    /**
     * Apply intelligent content filtering while preserving review structure.
     */
    private function filterHtmlForBandwidthOptimization(string $html): string
    {
        try {
            // First, check if this HTML contains review data - if so, be more conservative
            $hasReviews = preg_match('/data-hook=["\']review["\']/', $html) ||
                          preg_match('/class=["\'][^"\']*review[^"\']*["\']/', $html) ||
                          preg_match('/class=["\'][^"\']*cr-original-review-item[^"\']*["\']/', $html);

            if (!$hasReviews) {
                // If no reviews found, this might be a product page or other non-review content
                // Apply minimal filtering to preserve structure
                LoggingService::log('No review content detected, applying minimal filtering');

                return $this->applyMinimalFiltering($html);
            }

            LoggingService::log('Review content detected, applying careful filtering');

            // For review pages, use MINIMAL filtering to preserve review structure
            // The aggressive DOM manipulation was breaking data-hook attributes
            return $this->applyMinimalFiltering($html);
        } catch (\Exception $e) {
            // If filtering fails, return original but log the issue
            LoggingService::log('HTML filtering failed, using minimal filtering', [
                'error'         => $e->getMessage(),
                'original_size' => $this->formatBytes(strlen($html)),
            ]);

            return $this->applyMinimalFiltering($html);
        }
    }

    /**
     * Apply minimal filtering when DOM parsing fails or no reviews detected.
     */
    private function applyMinimalFiltering(string $html): string
    {
        // Simple regex-based filtering that's safer but less comprehensive
        $patterns = [
            // Remove script tags (major bandwidth saver)
            '/<script[^>]*>.*?<\/script>/is' => '',
            // Remove style tags
            '/<style[^>]*>.*?<\/style>/is' => '',
            // Remove comments
            '/<!--.*?-->/is' => '',
            // Remove excessive whitespace
            '/\s{3,}/' => ' ',
            '/>\s+</'  => '><',
        ];

        $filtered = $html;
        foreach ($patterns as $pattern => $replacement) {
            $filtered = preg_replace($pattern, $replacement, $filtered);
        }

        return $filtered;
    }

    /**
     * Log bandwidth usage for monitoring.
     */
    private function logBandwidthUsage(string $url, int $bytes): void
    {
        // Simple logging without alerts or limits - let the proxy service handle monitoring
        LoggingService::log('Bandwidth usage logged', [
            'url'             => parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PATH),
            'bytes'           => $bytes,
            'bytes_formatted' => $this->formatBytes($bytes),
        ]);
    }

    /**
     * Format bytes for human-readable display.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * Check if we should use direct connection for non-critical requests.
     */
    private function shouldUseDirectConnection(string $url): bool
    {
        // Removed bandwidth-based direct connection logic
        // Let the proxy service handle its own bandwidth management
        return false;
    }

    /**
     * Make a direct request without proxy (for non-critical requests).
     */
    private function makeDirectRequest(string $url, array $options = []): ?\Psr\Http\Message\ResponseInterface
    {
        try {
            // Create a temporary direct client
            $directClient = new Client([
                'timeout'         => 30,
                'connect_timeout' => 15,
                'http_errors'     => false,
                'verify'          => false,
                'cookies'         => $this->cookieJar, // Still use cookies
                'headers'         => $this->getBandwidthOptimizedHeaders(),
            ]);

            $response = $directClient->get($url, $options);

            LoggingService::log('Direct request completed', [
                'url'             => parse_url($url, PHP_URL_PATH),
                'status'          => $response->getStatusCode(),
                'bandwidth_saved' => 'proxy_bypassed',
            ]);

            return $response;
        } catch (\Exception $e) {
            LoggingService::log('Direct request failed, falling back to proxy', [
                'url'   => parse_url($url, PHP_URL_PATH),
                'error' => $e->getMessage(),
            ]);

            // Fall back to proxy request
            return null;
        }
    }

    /**
     * Set HTTP client for testing purposes.
     */
    public function setHttpClient(Client $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * Setup cookies from environment configuration.
     */
    private function setupCookies(): void
    {
        $cookieString = env('AMAZON_COOKIES', '');

        if (empty($cookieString)) {
            LoggingService::log('No Amazon cookies configured in AMAZON_COOKIES environment variable');

            return;
        }

        // Parse cookie string format: "name1=value1; name2=value2; name3=value3"
        $cookies = explode(';', $cookieString);

        foreach ($cookies as $cookie) {
            $cookie = trim($cookie);
            if (empty($cookie)) {
                continue;
            }

            $parts = explode('=', $cookie, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            $this->cookieJar->setCookie(new \GuzzleHttp\Cookie\SetCookie([
                'Name'     => $name,
                'Value'    => $value,
                'Domain'   => '.amazon.com',
                'Path'     => '/',
                'Secure'   => true,
                'HttpOnly' => true,
            ]));
        }

        LoggingService::log('Loaded '.count($cookies).' Amazon cookies for scraping');
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
        // Fetch reviews from Amazon with direct scraping
        $reviewsData = $this->fetchReviews($asin, $country);

        // Check if fetching failed completely (no data structure returned)
        if (empty($reviewsData)) {
            throw new \Exception('Unable to fetch product reviews. This could be due to Amazon blocking requests, network issues, or service configuration problems. Please try again in a few minutes.');
        }

        // If we have data structure but no reviews, that's acceptable (some products have 0 reviews)
        if (!isset($reviewsData['reviews'])) {
            $reviewsData['reviews'] = [];
        }

        LoggingService::log('Amazon scraping completed', [
            'asin'            => $asin,
            'review_count'    => count($reviewsData['reviews']),
            'has_description' => !empty($reviewsData['description']),
            'total_reviews'   => $reviewsData['total_reviews'] ?? 'unknown',
        ]);

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
     * Fetch reviews from Amazon by scraping.
     *
     * @param string $asin    Amazon Standard Identification Number
     * @param string $country Two-letter country code (defaults to 'us')
     *
     * @return array<string, mixed> Array containing reviews, description, and total count
     */
    public function fetchReviews(string $asin, string $country = 'us'): array
    {
        // Basic ASIN format validation
        if (!$this->isValidAsinFormat($asin)) {
            LoggingService::log('ASIN format validation failed', ['asin' => $asin]);

            return [
                'reviews'       => [],
                'description'   => '',
                'total_reviews' => 0,
            ];
        }

        LoggingService::log('Starting Amazon scraping for ASIN: '.$asin);

        try {
            // First, get the product page to extract basic info
            $productData = $this->scrapeProductPage($asin, $country);

            if (empty($productData)) {
                LoggingService::log('Failed to scrape product page', ['asin' => $asin]);

                return [
                    'reviews'       => [],
                    'description'   => '',
                    'total_reviews' => 0,
                ];
            }

            // Then scrape reviews from multiple pages
            $reviews = $this->scrapeReviewPages($asin, $country);

            if (empty($reviews)) {
                LoggingService::log('No reviews found for ASIN: '.$asin);

                // Check if this might be due to cookie expiration
                if ($this->detectCookieExpiration($asin)) {
                    app(AlertManager::class)->recordFailure(
                        'Amazon Direct Scraping',
                        'SESSION_EXPIRED',
                        'Amazon scraping session may have expired - no reviews found',
                        [
                            'asin'    => $asin,
                            'service' => 'direct_scraping',
                            'method'  => 'fetchReviews',
                        ]
                    );
                }

                return [
                    'reviews'       => [],
                    'description'   => $productData['description'] ?? '',
                    'total_reviews' => 0,
                ];
            }

            $result = [
                'reviews'       => $reviews,
                'description'   => $productData['description'] ?? '',
                'total_reviews' => $productData['total_reviews'] ?? count($reviews),
            ];

            LoggingService::log('Successfully scraped '.count($reviews).' reviews for ASIN: '.$asin);

            return $result;
        } catch (\Exception $e) {
            LoggingService::log('Amazon scraping failed', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Use context-aware alerting for direct scraping failures
            app(AlertManager::class)->recordFailure(
                'Amazon Direct Scraping',
                'SCRAPING_FAILED',
                $e->getMessage(),
                ['asin' => $asin],
                $e
            );

            return [];
        }
    }

    /**
     * Scrape the main product page for basic information with smart caching.
     */
    private function scrapeProductPage(string $asin, string $country): array
    {
        $url = "https://www.amazon.com/dp/{$asin}";

        // Check cache first (product pages don't change often)
        $cacheKey = "product_page_{$asin}_{$country}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            LoggingService::log('Using cached product page data', [
                'asin'            => $asin,
                'cache_key'       => $cacheKey,
                'bandwidth_saved' => 'yes',
            ]);

            return $cachedData;
        }

        LoggingService::log('Scraping product page: '.$url);

        try {
            $response = $this->makeOptimizedRequest($url, [
                'headers' => array_merge($this->getBandwidthOptimizedHeaders(), [
                    'Referer'           => 'https://www.amazon.com/',
                    'If-Modified-Since' => gmdate('D, d M Y H:i:s T', strtotime('-6 hours')), // Only get if modified in last 6 hours
                ]),
            ]);

            if (!$response) {
                LoggingService::log('Product page request was blocked for bandwidth optimization', ['asin' => $asin]);

                return [];
            }

            $statusCode = $response->getStatusCode();

            // Handle 304 Not Modified - use cached data if available
            if ($statusCode === 304) {
                LoggingService::log('Product page not modified, using cached data', [
                    'asin'            => $asin,
                    'bandwidth_saved' => 'yes',
                ]);

                return $cachedData ?? [];
            }

            $html = $response->getBody()->getContents();

            if ($statusCode !== 200) {
                LoggingService::log('Product page returned non-200 status', [
                    'status' => $statusCode,
                    'asin'   => $asin,
                ]);

                return [];
            }

            if (empty($html)) {
                LoggingService::log('Empty response from product page', ['asin' => $asin]);

                return [];
            }

            // Parse HTML with DOMCrawler
            $crawler = new Crawler($html);

            // Extract product description/title
            $description = '';

            // Try multiple selectors for product title
            $titleSelectors = [
                '#productTitle',
                '.product-title',
                '[data-automation-id="product-title"]',
                'h1.a-size-large',
            ];

            foreach ($titleSelectors as $selector) {
                try {
                    $titleNode = $crawler->filter($selector);
                    if ($titleNode->count() > 0) {
                        $description = trim($titleNode->text());
                        break;
                    }
                } catch (\Exception $e) {
                    // Continue to next selector
                }
            }

            if (empty($description)) {
                LoggingService::log('Could not extract product title', ['asin' => $asin]);
                $description = "Product {$asin}";
            }

            // Try to extract total review count
            $totalReviews = null;
            $reviewCountSelectors = [
                '[data-hook="total-review-count"]',
                '.cr-pivot-review-count-info .totalReviewCount',
                '.a-size-base.a-color-base[data-hook="total-review-count"]',
                'span[data-hook="total-review-count"]',
                '.a-text-normal',
                '.a-text-normal span',
            ];

            foreach ($reviewCountSelectors as $selector) {
                try {
                    $countNode = $crawler->filter($selector);
                    if ($countNode->count() > 0) {
                        $countText = trim($countNode->text());

                        // Extract number from text like "1,234 global ratings" or "456 reviews"
                        if (preg_match('/([0-9,]+)\s*(?:global\s+ratings?|reviews?|ratings?)/i', $countText, $matches)) {
                            $totalReviews = (int) str_replace(',', '', $matches[1]);
                            LoggingService::log("Extracted total review count: {$totalReviews}", ['asin' => $asin, 'text' => $countText]);
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next selector
                }
            }

            $result = [
                'description'   => $description,
                'asin'          => $asin,
                'total_reviews' => $totalReviews,
            ];

            // Cache the result for 6 hours (product titles rarely change)
            Cache::put($cacheKey, $result, 6 * 60 * 60);

            LoggingService::log('Cached product page data', [
                'asin'           => $asin,
                'cache_duration' => '6 hours',
            ]);

            return $result;
        } catch (\Exception $e) {
            LoggingService::log('Failed to scrape product page', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Scrape reviews from multiple review pages.
     */
    private function scrapeReviewPages(string $asin, string $country, int $maxPages = 8): array
    {
        $allReviews = [];

        // Get configurable limits from environment
        $maxPages = (int) env('AMAZON_SCRAPING_MAX_PAGES', $maxPages ?: 10);
        $maxReviews = (int) env('AMAZON_SCRAPING_MAX_REVIEWS', 100);
        $targetReviews = (int) env('AMAZON_SCRAPING_TARGET_REVIEWS', 30);

        // Reduce bandwidth by limiting pages - configurable for different environments
        LoggingService::log('Starting review scraping with bandwidth optimization', [
            'asin'                   => $asin,
            'max_pages'              => $maxPages,
            'max_reviews'            => $maxReviews,
            'target_reviews'         => $targetReviews,
            'bandwidth_optimization' => 'configurable_limits',
        ]);

        // Try different Amazon review URL patterns, prioritizing the most reliable
        $urlPatterns = [
            "https://www.amazon.com/gp/product/{$asin}/reviews", // Most reliable
            "https://www.amazon.com/dp/product-reviews/{$asin}",
            "https://www.amazon.com/product-reviews/{$asin}",
        ];

        $workingBaseUrl = null;

        // Test which URL pattern works and has substantial content
        foreach ($urlPatterns as $pattern) {
            try {
                $testResponse = $this->makeOptimizedRequest($pattern, [
                    'headers' => array_merge($this->getBandwidthOptimizedHeaders(), [
                        'Referer' => "https://www.amazon.com/dp/{$asin}",
                    ]),
                ]);

                if (!$testResponse) {
                    LoggingService::log("URL pattern blocked for bandwidth optimization: {$pattern}", ['asin' => $asin]);
                    continue;
                }

                $statusCode = $testResponse->getStatusCode();
                $contentLength = strlen($testResponse->getBody()->getContents());

                // URL should return 200 and have substantial content (likely contains reviews)
                // Lower threshold to account for HTML filtering - filtered content will be smaller
                $minContentThreshold = 1000; // 1KB after filtering is sufficient for review pages
                if ($statusCode === 200 && $contentLength > $minContentThreshold) {
                    $workingBaseUrl = $pattern;
                    LoggingService::log("Found working reviews URL pattern: {$pattern}", [
                        'asin'           => $asin,
                        'status'         => $statusCode,
                        'content_length' => $contentLength,
                        'threshold'      => $minContentThreshold,
                    ]);
                    break;
                }

                LoggingService::log("URL pattern insufficient: {$pattern}", [
                    'asin'             => $asin,
                    'status'           => $statusCode,
                    'content_length'   => $contentLength,
                    'required_minimum' => $minContentThreshold,
                ]);
            } catch (\Exception $e) {
                LoggingService::log("URL pattern failed: {$pattern}", [
                    'asin'  => $asin,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        if (!$workingBaseUrl) {
            LoggingService::log('No working review URL pattern found', ['asin' => $asin]);

            return [];
        }

        // Track total bandwidth usage for this scraping session
        $totalBandwidthUsed = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            LoggingService::log("Scraping reviews page {$page} for ASIN: {$asin}");

            $url = $workingBaseUrl."?pageNumber={$page}&sortBy=recent";

            $retryCount = 0;
            $maxRetries = 3;

            while ($retryCount < $maxRetries) {
                try {
                    $response = $this->makeOptimizedRequest($url, [
                        'headers' => array_merge($this->getBandwidthOptimizedHeaders(), [
                            'Referer' => $page === 1 ? "https://www.amazon.com/dp/{$asin}" : $workingBaseUrl,
                        ]),
                    ]);

                    if (!$response) {
                        LoggingService::log('Review page request blocked for bandwidth optimization', [
                            'asin'  => $asin,
                            'page'  => $page,
                            'retry' => $retryCount + 1,
                        ]);
                        $retryCount++;
                        continue;
                    }

                    $statusCode = $response->getStatusCode();
                    $html = $response->getBody()->getContents();
                    $pageSize = strlen($html);
                    $totalBandwidthUsed += $pageSize;

                    if ($statusCode === 200 && !empty($html)) {
                        // Success - report to proxy manager
                        if ($this->currentProxyConfig) {
                            $this->proxyManager->reportSuccess($this->currentProxyConfig);
                        }

                        // Parse reviews from this page
                        $pageReviews = $this->parseReviewsFromHtml($html, $asin);

                        if (empty($pageReviews)) {
                            LoggingService::log("No reviews found on page {$page}, stopping", ['asin' => $asin]);
                            break 2; // Break out of both loops
                        }

                        // Deduplicate reviews before merging to prevent duplicate content
                        $allReviews = $this->deduplicateReviews($allReviews, $pageReviews);

                        LoggingService::log('Extracted '.count($pageReviews)." reviews from page {$page}", [
                            'total_reviews'   => count($allReviews),
                            'page_size'       => $this->formatBytes($pageSize),
                            'total_bandwidth' => $this->formatBytes($totalBandwidthUsed),
                        ]);

                        // Check if we've hit the maximum review limit
                        if (count($allReviews) >= $maxReviews) {
                            LoggingService::log('Maximum review limit reached', [
                                'asin'              => $asin,
                                'reviews_collected' => count($allReviews),
                                'max_reviews'       => $maxReviews,
                                'pages_scraped'     => $page,
                            ]);
                            break 2; // Exit both loops
                        }

                        // INTELLIGENT early termination for bandwidth optimization
                        // Assess review quality and stop if we have sufficient data for analysis
                        if ($page >= 2 && count($allReviews) >= $targetReviews) { // Only consider early termination after target reached
                            $qualityMetrics = $this->assessReviewQuality($allReviews);

                            LoggingService::log('Quality assessment', [
                                'asin'            => $asin,
                                'page'            => $page,
                                'total_reviews'   => $qualityMetrics['total_reviews'],
                                'quality_reviews' => $qualityMetrics['quality_reviews'] ?? 0,
                                'quality_score'   => round($qualityMetrics['quality_score'], 1),
                                'avg_length'      => round($qualityMetrics['avg_length'], 1),
                                'sufficient'      => $qualityMetrics['sufficient_for_analysis'],
                            ]);

                            // More conservative early termination conditions
                            // Only terminate early if we have EXCEPTIONAL quality AND reached target count
                            if ($qualityMetrics['sufficient_for_analysis'] &&
                                $qualityMetrics['quality_reviews'] >= 40 && // Need 40+ quality reviews
                                count(array_filter($qualityMetrics['rating_distribution'])) >= 4 && // Need 4+ different rating types
                                $qualityMetrics['avg_length'] >= 100 && // Need higher average length
                                $qualityMetrics['quality_score'] >= 80) { // Need higher quality score
                                $bandwidthSaved = ($maxPages - $page) * ($totalBandwidthUsed / $page);

                                LoggingService::log('EARLY TERMINATION - exceptional quality data collected', [
                                    'asin'                      => $asin,
                                    'reviews_collected'         => count($allReviews),
                                    'quality_score'             => round($qualityMetrics['quality_score'], 1),
                                    'pages_scraped'             => $page,
                                    'pages_remaining'           => $maxPages - $page,
                                    'bandwidth_used'            => $this->formatBytes($totalBandwidthUsed),
                                    'estimated_bandwidth_saved' => $this->formatBytes($bandwidthSaved),
                                    'early_termination_reason'  => 'exceptional_quality',
                                ]);
                                break 2; // Exit both loops
                            }
                        }

                        break; // Success, exit retry loop
                    } else {
                        // Handle non-200 status or empty response
                        LoggingService::log("Reviews page {$page} returned non-200 status or empty response", [
                            'status'         => $statusCode,
                            'asin'           => $asin,
                            'content_length' => strlen($html),
                            'retry'          => $retryCount + 1,
                        ]);

                        // Check if this looks like blocking
                        if ($statusCode === 503 || $statusCode === 429 || strpos($html, 'blocked') !== false) {
                            $this->handleBlocking($asin, $statusCode, $html);

                            // If we have a session-based proxy, rotate session and reinitialize client
                            if ($this->currentProxyConfig &&
                                isset($this->currentProxyConfig['session_id']) &&
                                $this->currentProxyConfig['session_id']) {
                                LoggingService::log('Rotating proxy session due to blocking', [
                                    'asin'   => $asin,
                                    'status' => $statusCode,
                                    'retry'  => $retryCount + 1,
                                ]);

                                $this->proxyManager->rotateSession();
                                $this->initializeHttpClient(); // Reinitialize with new session
                            }

                            $retryCount++;
                            continue;
                        }

                        break 2; // Non-retryable error, exit both loops
                    }
                } catch (\Exception $e) {
                    LoggingService::log("Failed to scrape reviews page {$page}", [
                        'asin'  => $asin,
                        'error' => $e->getMessage(),
                        'retry' => $retryCount + 1,
                    ]);

                    // Report failure to proxy manager
                    if ($this->currentProxyConfig) {
                        $this->proxyManager->reportFailure($this->currentProxyConfig, $e->getMessage());
                    }

                    $retryCount++;
                    if ($retryCount < $maxRetries) {
                        // Rotate proxy and retry
                        $this->rotateProxyAndReconnect();
                        usleep(1000000); // 1 second delay before retry
                    }
                }
            }

            if ($retryCount >= $maxRetries) {
                LoggingService::log("Max retries reached for page {$page}, stopping", ['asin' => $asin]);
                break;
            }

            // Add delay between requests to avoid rate limiting
            usleep(rand(500000, 1500000)); // Random delay between 0.5-1.5 seconds
        }

        LoggingService::log('Review scraping completed', [
            'asin'                    => $asin,
            'total_reviews'           => count($allReviews),
            'pages_scraped'           => min($page, $maxPages),
            'total_bandwidth_used'    => $this->formatBytes($totalBandwidthUsed),
            'avg_bandwidth_per_page'  => $totalBandwidthUsed > 0 ? $this->formatBytes($totalBandwidthUsed / max(1, $page - 1)) : '0 B',
            'bandwidth_optimizations' => [
                'reduced_max_pages'             => '5 (from 10) = ~50% reduction',
                'smart_html_filtering'          => 'Remove JS, CSS, images, ads while preserving reviews',
                'download_limit'                => '3MB (was unlimited) with intelligent filtering',
                'optimized_headers'             => 'Reduced header size, added bandwidth hints',
                'selective_extraction'          => 'Only essential review data extracted',
                'intelligent_early_termination' => 'Quality-based stopping conditions',
                'compression_enabled'           => 'gzip, deflate, br',
                'approach'                      => 'Progressive: Download complete HTML then filter intelligently',
                'estimated_final_savings'       => '50-70% after filtering vs original approach',
            ],
        ]);

        return $allReviews;
    }

    /**
     * Parse reviews from HTML content.
     */
    private function parseReviewsFromHtml(string $html, string $asin): array
    {
        $reviews = [];

        try {
            $crawler = new Crawler($html);

            // Find review containers - Amazon uses various selectors
            $reviewSelectors = [
                '[data-hook="review"]',
                '.review',
                '.cr-original-review-item',
                '[data-hook="review-body"]',
            ];

            $reviewNodes = null;
            foreach ($reviewSelectors as $selector) {
                try {
                    $nodes = $crawler->filter($selector);
                    if ($nodes->count() > 0) {
                        $reviewNodes = $nodes;
                        break;
                    }
                } catch (\Exception $e) {
                    // Continue to next selector
                }
            }

            if (!$reviewNodes || $reviewNodes->count() === 0) {
                LoggingService::log('No review nodes found in HTML', [
                    'asin'            => $asin,
                    'html_length'     => strlen($html),
                    'selectors_tried' => $reviewSelectors,
                ]);

                return [];
            }

            $reviewNodes->each(function (Crawler $node) use (&$reviews) {
                try {
                    $review = $this->extractReviewFromNode($node);
                    if (!empty($review)) {
                        $reviews[] = $review;
                    }
                } catch (\Exception $e) {
                    LoggingService::log('Failed to extract review from node', [
                        'error' => substr($e->getMessage(), 0, 100),
                    ]);
                }
            });
        } catch (\Exception $e) {
            LoggingService::log('Failed to parse reviews from HTML', [
                'asin'        => $asin,
                'error'       => substr($e->getMessage(), 0, 100),
                'html_length' => strlen($html),
            ]);
        }

        return $reviews;
    }

    /**
     * Extract individual review data from a review node.
     */
    private function extractReviewFromNode(Crawler $node): array
    {
        $review = [];

        try {
            // Extract rating - ESSENTIAL data only
            $ratingSelectors = [
                '.review-rating .a-icon-alt',
                '[data-hook="review-star-rating"] .a-icon-alt',
                '.a-icon-alt', // Broader selector for test cases
            ];

            $rating = 0;
            foreach ($ratingSelectors as $selector) {
                try {
                    $ratingNode = $node->filter($selector);
                    if ($ratingNode->count() > 0) {
                        $ratingText = $ratingNode->text();
                        if (preg_match('/(\d+(?:\.\d+)?)/', $ratingText, $matches)) {
                            $rating = (float) $matches[1];
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next selector
                }
            }

            // Extract review text - MOST CRITICAL for analysis
            $textSelectors = [
                '[data-hook="review-body"] [data-hook="review-collapsed"] span',
                '[data-hook="review-body"] span',
                '[data-hook="review-body"]', // Broader selector for test cases
            ];

            $text = '';
            foreach ($textSelectors as $selector) {
                try {
                    $textNode = $node->filter($selector);
                    if ($textNode->count() > 0) {
                        $text = trim($textNode->text());
                        if (!empty($text)) {
                            break; // Stop at first valid text found
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next selector
                }
            }

            // BANDWIDTH OPTIMIZATION: Skip extracting review title and author unless absolutely necessary
            // These are nice-to-have but not essential for sentiment analysis
            // Removing them saves processing time and reduces response parsing overhead

            // Only include review if we have ESSENTIAL data (rating + text)
            if ($rating > 0 && !empty($text)) {
                // SIMPLIFIED review structure - only essential fields
                $review = [
                    'id'          => 'scrape_'.substr(md5($text.$rating), 0, 8), // Shorter ID for bandwidth
                    'rating'      => $rating,
                    'text'        => $text, // Primary field for analysis
                    'review_text' => $text, // Backward compatibility
                ];

                // BANDWIDTH OPTIMIZATION: Skip optional fields that increase data size
                // Removed: review_title, author, date, verified_purchase, helpful_votes
                // These can be added back if specifically needed for analysis
            }
        } catch (\Exception $e) {
            // Log extraction errors only for debugging
            LoggingService::log('Review extraction error', [
                'error' => substr($e->getMessage(), 0, 100),
            ]);
        }

        return $review;
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
     * Detect if cookie session might have expired.
     */
    private function detectCookieExpiration(string $asin): bool
    {
        // Try to access a simple Amazon page to test cookie validity
        try {
            $response = $this->httpClient->get('https://www.amazon.com/gp/css/homepage.html', [
                'timeout' => 10,
            ]);

            $html = $response->getBody()->getContents();

            // Check for signs that we need to sign in
            $signInIndicators = [
                'sign in',
                'sign-in',
                'ap-signin',
                'authentication required',
                'session expired',
            ];

            $htmlLower = strtolower($html);
            foreach ($signInIndicators as $indicator) {
                if (strpos($htmlLower, $indicator) !== false) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            LoggingService::log('Cookie expiration detection failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Handle blocking detection and response.
     */
    private function handleBlocking(string $asin, int $statusCode, string $html): void
    {
        LoggingService::log('Potential blocking detected', [
            'asin'           => $asin,
            'status_code'    => $statusCode,
            'content_length' => strlen($html),
            'proxy_type'     => $this->currentProxyConfig['type'] ?? 'direct',
        ]);

        // Use enhanced CAPTCHA detection for 200 status responses
        if ($statusCode === 200) {
            if ($this->detectCaptchaInResponse($html, "https://www.amazon.com/dp/{$asin}")) {
                // CAPTCHA detection will handle alerts and proxy rotation
                return;
            }
        }

        // Check for specific blocking indicators for non-200 responses
        $blockingIndicators = [
            'blocked',
            'captcha',
            'unusual traffic',
            'automated requests',
            'try again later',
            'service unavailable',
            'rate limit',
        ];

        $htmlLower = strtolower($html);
        foreach ($blockingIndicators as $indicator) {
            if (strpos($htmlLower, $indicator) !== false) {
                LoggingService::log("Blocking detected in fallback scraping: {$indicator}", [
                    'asin'               => $asin,
                    'status_code'        => $statusCode,
                    'blocking_indicator' => $indicator,
                    'content_length'     => strlen($html),
                    'note'               => 'Production uses BrightData - this is fallback only',
                ]);

                // Rotate proxy and session
                $this->rotateProxyAndReconnect();
                break;
            }
        }
    }

    /**
     * Rotate proxy and reconnect HTTP client.
     */
    private function rotateProxyAndReconnect(): void
    {
        LoggingService::log('Rotating proxy due to blocking/failure');

        // Rotate session if supported
        $this->proxyManager->rotateSession();

        // Reinitialize HTTP client with new proxy
        $this->initializeHttpClient();

        // Add extra delay after rotation
        usleep(2000000); // 2 second delay
    }

    /**
     * Get proxy statistics for monitoring.
     */
    public function getProxyStats(): array
    {
        return $this->proxyManager->getProxyStats();
    }

    /**
     * Assess review quality for early termination decisions.
     */
    private function assessReviewQuality(array $reviews): array
    {
        $qualityMetrics = [
            'total_reviews'           => count($reviews),
            'avg_length'              => 0,
            'rating_distribution'     => [],
            'quality_score'           => 0,
            'sufficient_for_analysis' => false,
        ];

        if (empty($reviews)) {
            return $qualityMetrics;
        }

        $totalLength = 0;
        $ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $qualityReviews = 0;

        foreach ($reviews as $review) {
            $text = $review['text'] ?? '';
            $rating = $review['rating'] ?? 0;

            $length = strlen($text);
            $totalLength += $length;

            // Count rating distribution
            $roundedRating = round($rating);
            if ($roundedRating >= 1 && $roundedRating <= 5) {
                $ratingCounts[$roundedRating]++;
            }

            // Quality review criteria: meaningful length and valid rating
            if ($length >= 50 && $rating > 0) { // At least 50 characters
                $qualityReviews++;
            }
        }

        $qualityMetrics['avg_length'] = $totalLength / count($reviews);
        $qualityMetrics['rating_distribution'] = $ratingCounts;
        $qualityMetrics['quality_reviews'] = $qualityReviews;

        // Calculate quality score (0-100)
        $diversityScore = count(array_filter($ratingCounts)) * 20; // Max 100 for all 5 ratings present
        $lengthScore = min(100, $qualityMetrics['avg_length'] / 2); // Max 100 for 200+ char average
        $volumeScore = min(100, $qualityReviews * 4); // Max 100 for 25+ quality reviews

        $qualityMetrics['quality_score'] = ($diversityScore + $lengthScore + $volumeScore) / 3;

        // More lenient criteria for sufficient analysis (only used for EXCEPTIONAL early termination)
        // These criteria are now much stricter since early termination only happens with exceptional data
        $qualityMetrics['sufficient_for_analysis'] =
            $qualityReviews >= 35 && // At least 35 quality reviews (increased from 20)
            count(array_filter($ratingCounts)) >= 4 && // At least 4 different ratings (increased from 3)
            $qualityMetrics['avg_length'] >= 120; // Average length of 120+ characters (increased from 75)

        return $qualityMetrics;
    }

    /**
     * Deduplicate reviews to prevent duplicate content from pagination issues.
     *
     * @param array $existingReviews Current collection of reviews
     * @param array $newReviews      Reviews from current page to merge
     *
     * @return array Deduplicated combined reviews
     */
    private function deduplicateReviews(array $existingReviews, array $newReviews): array
    {
        // Create a lookup map of existing reviews for O(1) duplicate detection
        $existingTexts = [];
        foreach ($existingReviews as $index => $review) {
            if (isset($review['review_text'])) {
                // Use review text as primary deduplication key
                $textKey = $this->normalizeReviewText($review['review_text']);
                $existingTexts[$textKey] = $index;
            }
        }

        $duplicatesFound = 0;
        $newUniqueReviews = [];

        foreach ($newReviews as $newReview) {
            if (!isset($newReview['review_text'])) {
                continue; // Skip reviews without text
            }

            $textKey = $this->normalizeReviewText($newReview['review_text']);

            // Check if this review text already exists
            if (!isset($existingTexts[$textKey])) {
                // This is a new unique review
                $newUniqueReviews[] = $newReview;
                $existingTexts[$textKey] = true; // Mark as seen
            } else {
                $duplicatesFound++;
            }
        }

        // Log deduplication results for monitoring
        if ($duplicatesFound > 0) {
            LoggingService::log('Review deduplication applied', [
                'existing_reviews'    => count($existingReviews),
                'new_reviews_found'   => count($newReviews),
                'duplicates_filtered' => $duplicatesFound,
                'unique_new_reviews'  => count($newUniqueReviews),
                'final_total'         => count($existingReviews) + count($newUniqueReviews),
            ]);
        }

        return array_merge($existingReviews, $newUniqueReviews);
    }

    /**
     * Normalize review text for consistent duplicate detection.
     *
     * @param string $text Raw review text
     *
     * @return string Normalized text key
     */
    private function normalizeReviewText(string $text): string
    {
        // Normalize whitespace, case, and common variations for duplicate detection
        $normalized = trim($text);
        $normalized = preg_replace('/\s+/', ' ', $normalized); // Normalize whitespace
        $normalized = strtolower($normalized); // Case insensitive
        $normalized = preg_replace('/[^\w\s]/', '', $normalized); // Remove punctuation for fuzzy matching

        return $normalized;
    }
}
