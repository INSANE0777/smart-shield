<?php

namespace App\Services\Amazon;

use App\Models\AsinData;
use App\Services\AlertManager;
use App\Services\LoggingService;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Amazon AJAX Review Service - Bypasses direct URL protections.
 *
 * This service uses Amazon's internal AJAX endpoints to fetch reviews,
 * mimicking legitimate browser behavior and bypassing anti-bot measures
 * targeting direct URL access patterns.
 */
class AmazonAjaxReviewService implements AmazonReviewServiceInterface
{
    private Client $httpClient;
    private CookieJar $cookieJar;
    private array $headers;
    private ProxyManager $proxyManager;
    private ?array $currentProxyConfig = null;
    private CookieSessionManager $cookieSessionManager;
    private ?array $currentCookieSession = null;

    public function __construct()
    {
        $this->proxyManager = new ProxyManager();
        $this->cookieSessionManager = new CookieSessionManager();
        $this->cookieJar = new CookieJar();
        $this->setupCookies();

        // Headers matching successful browser behavior
        $this->headers = [
            'User-Agent'                => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language'           => 'en-GB,en-US;q=0.9,en;q=0.8',
            'Accept-Encoding'           => 'gzip, deflate, br, zstd',
            'Connection'                => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest'            => 'document',
            'Sec-Fetch-Mode'            => 'navigate',
            'Sec-Fetch-Site'            => 'same-origin',
            'Sec-Fetch-User'            => '?1',
            'Cache-Control'             => 'no-cache',
            'Priority'                  => 'u=0, i',
            'sec-ch-ua'                 => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'sec-ch-ua-mobile'          => '?0',
            'sec-ch-ua-platform'        => '"Linux"',
        ];

        $this->initializeHttpClient();
    }

    /**
     * Setup Amazon cookies using multi-session cookie manager with rotation.
     */
    private function setupCookies(): void
    {
        // Get the next available cookie session using round-robin rotation
        $this->currentCookieSession = $this->cookieSessionManager->getNextCookieSession();

        if (!$this->currentCookieSession) {
            LoggingService::log('No Amazon cookie sessions available for AJAX service - falling back to legacy AMAZON_COOKIES');
            $this->setupLegacyCookies();

            return;
        }

        // Create cookie jar from the selected session
        $this->cookieJar = $this->cookieSessionManager->createCookieJar($this->currentCookieSession);

        LoggingService::log('Setup AJAX cookies from multi-session manager', [
            'session_name'    => $this->currentCookieSession['name'],
            'session_env_var' => $this->currentCookieSession['env_var'],
        ]);
    }

    /**
     * Fallback method to setup cookies from legacy AMAZON_COOKIES environment variable.
     */
    private function setupLegacyCookies(): void
    {
        $amazonCookie = env('AMAZON_COOKIES_1') ?? env('AMAZON_COOKIE');

        if (!$amazonCookie) {
            LoggingService::log('No Amazon cookie found for AJAX service', [
                'service'      => 'AmazonAjaxReviewService',
                'checked_vars' => ['AMAZON_COOKIES_1', 'AMAZON_COOKIE'],
            ]);

            return;
        }

        // Parse and set cookies
        $domain = '.amazon.com';
        $cookiePairs = explode(';', $amazonCookie);

        foreach ($cookiePairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (count($parts) === 2) {
                $this->cookieJar->setCookie(new \GuzzleHttp\Cookie\SetCookie([
                    'Name'     => trim($parts[0]),
                    'Value'    => trim($parts[1]),
                    'Domain'   => $domain,
                    'Path'     => '/',
                    'Secure'   => true,
                    'HttpOnly' => false,
                ]));
            }
        }

        LoggingService::log('Amazon legacy cookies configured for AJAX service', [
            'cookie_count' => count($cookiePairs),
            'domain'       => $domain,
        ]);
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
            'headers'         => $this->headers,
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

            LoggingService::log('AJAX service using proxy', [
                'type'    => $this->currentProxyConfig['type'],
                'country' => $this->currentProxyConfig['country'],
            ]);
        }

        $this->httpClient = new Client($clientConfig);
    }

    /**
     * Fetch reviews using AJAX bypass approach.
     */
    public function fetchReviews(string $asin, string $country = 'us'): array
    {
        LoggingService::log('Starting AJAX review extraction', [
            'asin'    => $asin,
            'service' => 'AmazonAjaxReviewService',
        ]);

        try {
            // Phase 1: Bootstrap session from page 1
            $sessionData = $this->bootstrapSession($asin);

            if (!$sessionData) {
                LoggingService::log('Session bootstrap failed, falling back to direct scraping', ['asin' => $asin]);

                return $this->fallbackToDirectScraping($asin);
            }

            // Phase 2: AJAX pagination for pages 2+
            $allReviews = $sessionData['page1_reviews'] ?? [];
            $maxPages = (int) env('AMAZON_SCRAPING_MAX_PAGES', 10);
            $maxReviews = (int) env('AMAZON_SCRAPING_MAX_REVIEWS', 100);

            LoggingService::log('Starting AJAX pagination', [
                'asin'          => $asin,
                'max_pages'     => $maxPages,
                'page1_reviews' => count($allReviews),
                'csrf_token'    => substr($sessionData['csrf_token'] ?? '', 0, 20).'...',
            ]);

            for ($page = 2; $page <= $maxPages; $page++) {
                $ajaxReviews = $this->fetchAjaxPage($asin, $page, $sessionData);

                if (empty($ajaxReviews)) {
                    LoggingService::log("No more reviews found on AJAX page {$page}", ['asin' => $asin]);
                    break;
                }

                $allReviews = array_merge($allReviews, $ajaxReviews);

                LoggingService::log("AJAX page {$page} extracted", [
                    'asin'          => $asin,
                    'page_reviews'  => count($ajaxReviews),
                    'total_reviews' => count($allReviews),
                ]);

                // Check limits
                if (count($allReviews) >= $maxReviews) {
                    LoggingService::log('Maximum review limit reached via AJAX', [
                        'asin'            => $asin,
                        'total_reviews'   => count($allReviews),
                        'pages_processed' => $page,
                    ]);
                    break;
                }

                // Progressive loading delay (mimic human behavior)
                usleep(rand(1000000, 3000000)); // 1-3 seconds
            }

            return [
                'reviews'       => $allReviews,
                'description'   => $sessionData['description'] ?? '',
                'total_reviews' => $sessionData['total_reviews'] ?? count($allReviews),
            ];
        } catch (\Exception $e) {
            LoggingService::log('AJAX service error', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            // Fallback to direct scraping
            return $this->fallbackToDirectScraping($asin);
        }
    }

    /**
     * Phase 1: Bootstrap session and extract AJAX parameters.
     */
    private function bootstrapSession(string $asin): ?array
    {
        try {
            $baseUrl = "https://www.amazon.com/product-reviews/{$asin}?ie=UTF8&reviewerType=all_reviews";

            LoggingService::log('Bootstrapping AJAX session', [
                'asin'     => $asin,
                'base_url' => $baseUrl,
            ]);

            $response = $this->httpClient->get($baseUrl);

            if ($response->getStatusCode() !== 200) {
                LoggingService::log('Bootstrap failed - non-200 response', [
                    'asin'   => $asin,
                    'status' => $response->getStatusCode(),
                ]);

                return null;
            }

            $html = $response->getBody()->getContents();

            // Check for CAPTCHA or blocking content first
            if ($this->detectCaptchaInResponse($html, $baseUrl)) {
                return null; // CAPTCHA detection handles session marking and alerts
            }

            // Check for login redirect
            if (strpos($html, 'ap/signin') !== false || strpos($html, 'Sign-In') !== false) {
                LoggingService::log('Bootstrap failed - redirected to login', ['asin' => $asin]);

                // Mark current session as unhealthy if using session manager
                if ($this->currentCookieSession) {
                    $this->cookieSessionManager->markSessionUnhealthy(
                        $this->currentCookieSession['index'],
                        'Login redirect detected',
                        60 // 60 minute cooldown for login issues
                    );
                }

                app(AlertManager::class)->recordFailure(
                    'Amazon AJAX Service',
                    'SESSION_EXPIRED',
                    'Amazon AJAX session expired - redirected to login',
                    [
                        'asin'         => $asin,
                        'service'      => 'AmazonAjaxReviewService',
                        'session_info' => $this->currentCookieSession,
                    ]
                );

                return null;
            }

            // Extract cr-state-object
            $crState = $this->extractCrStateObject($html);

            if (!$crState || !isset($crState['reviewsAjaxUrl']) || !isset($crState['reviewsCsrfToken'])) {
                LoggingService::log('Bootstrap failed - missing AJAX parameters', [
                    'asin'             => $asin,
                    'cr_state_found'   => $crState !== null,
                    'ajax_url_found'   => isset($crState['reviewsAjaxUrl']),
                    'csrf_token_found' => isset($crState['reviewsCsrfToken']),
                ]);

                return null;
            }

            // Extract page 1 reviews
            $page1Reviews = $this->parseReviewsFromHtml($html, $asin);

            // Extract additional metadata
            $description = $this->extractProductDescription($html);
            $totalReviews = $this->extractTotalReviewCount($html);

            LoggingService::log('Session bootstrap successful', [
                'asin'                    => $asin,
                'ajax_url'                => $crState['reviewsAjaxUrl'],
                'csrf_token'              => substr($crState['reviewsCsrfToken'], 0, 20).'...',
                'pagination_disabled'     => $crState['isArpPaginationDisabled'] ?? 'unknown',
                'page1_reviews'           => count($page1Reviews),
                'total_reviews_on_amazon' => $totalReviews,
            ]);

            return [
                'ajax_url'      => $crState['reviewsAjaxUrl'],
                'csrf_token'    => $crState['reviewsCsrfToken'],
                'base_url'      => $baseUrl,
                'page1_reviews' => $page1Reviews,
                'description'   => $description,
                'total_reviews' => $totalReviews,
                'cr_state'      => $crState,
            ];
        } catch (\Exception $e) {
            LoggingService::log('Bootstrap session error', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Phase 2: Fetch reviews from specific page using AJAX.
     */
    private function fetchAjaxPage(string $asin, int $page, array $sessionData): array
    {
        try {
            $ajaxUrl = 'https://www.amazon.com'.$sessionData['ajax_url'];

            // AJAX headers mimicking browser behavior
            $ajaxHeaders = [
                'Accept'           => 'application/json, text/javascript, */*; q=0.01',
                'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-CSRF-Token'     => $sessionData['csrf_token'],
                'Referer'          => $sessionData['base_url'],
                'Sec-Fetch-Dest'   => 'empty',
                'Sec-Fetch-Mode'   => 'cors',
                'Sec-Fetch-Site'   => 'same-origin',
            ];

            // AJAX parameters discovered from reverse engineering
            $ajaxParams = [
                'asin'         => $asin,
                'pageNumber'   => $page,
                'reviewerType' => 'all_reviews',
                'sortBy'       => 'recent',
                'scope'        => 'reviewsAjax',
                'filterByStar' => '',
                'pageSize'     => '10',
            ];

            LoggingService::log("Making AJAX request for page {$page}", [
                'asin'       => $asin,
                'ajax_url'   => $ajaxUrl,
                'parameters' => $ajaxParams,
            ]);

            $response = $this->httpClient->post($ajaxUrl, [
                'headers'     => array_merge($this->headers, $ajaxHeaders),
                'form_params' => $ajaxParams,
                'timeout'     => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                LoggingService::log("AJAX request failed for page {$page}", [
                    'asin'   => $asin,
                    'status' => $response->getStatusCode(),
                ]);

                return [];
            }

            $responseBody = $response->getBody()->getContents();

            // Check for CAPTCHA in AJAX response
            if ($this->detectCaptchaInResponse($responseBody, $ajaxUrl)) {
                return []; // CAPTCHA detection handles session marking and alerts
            }

            // Try to parse as JSON
            $jsonData = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                LoggingService::log("AJAX response not valid JSON for page {$page}", [
                    'asin'             => $asin,
                    'response_preview' => substr($responseBody, 0, 200),
                ]);

                return [];
            }

            // Extract reviews from AJAX response
            $reviews = [];

            if (isset($jsonData['html'])) {
                // Reviews embedded in HTML within JSON
                $reviews = $this->parseReviewsFromHtml($jsonData['html'], $asin);
            } elseif (isset($jsonData['reviews']) && is_array($jsonData['reviews'])) {
                // Direct review array in JSON
                $reviews = $this->formatAjaxReviews($jsonData['reviews']);
            }

            LoggingService::log("AJAX page {$page} processed", [
                'asin'          => $asin,
                'response_keys' => array_keys($jsonData),
                'reviews_found' => count($reviews),
                'response_size' => strlen($responseBody),
            ]);

            return $reviews;
        } catch (\Exception $e) {
            LoggingService::log("AJAX page {$page} error", [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Extract cr-state-object JSON from HTML.
     */
    private function extractCrStateObject(string $html): ?array
    {
        $pattern = '/<span[^>]*id="cr-state-object"[^>]*data-state="([^"]*)"[^>]*>/';

        if (preg_match($pattern, $html, $matches)) {
            try {
                $decodedData = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return json_decode($decodedData, true);
            } catch (\Exception $e) {
                LoggingService::log('Failed to parse cr-state-object', [
                    'error'    => $e->getMessage(),
                    'raw_data' => substr($matches[1], 0, 100),
                ]);
            }
        }

        return null;
    }

    /**
     * Parse reviews from HTML content.
     */
    private function parseReviewsFromHtml(string $html, string $asin): array
    {
        $reviews = [];

        try {
            $crawler = new Crawler($html);

            // Find review containers
            $reviewNodes = $crawler->filter('[data-hook="review"]');

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
     * Extract individual review from DOM node.
     */
    private function extractReviewFromNode(Crawler $node): array
    {
        $review = [];

        try {
            // Extract review text
            $reviewBodyNode = $node->filter('[data-hook="review-body"] span, .cr-original-review-item .review-text');
            $review['review_text'] = $reviewBodyNode->count() > 0 ? trim($reviewBodyNode->text()) : '';

            // Extract rating
            $ratingNode = $node->filter('.review-rating, [data-hook="review-star-rating"]');
            if ($ratingNode->count() > 0) {
                $ratingText = $ratingNode->attr('class').' '.$ratingNode->text();
                if (preg_match('/(\d+(?:\.\d+)?)/', $ratingText, $matches)) {
                    $review['rating'] = (float) $matches[1];
                }
            }

            // Extract reviewer name
            $reviewerNode = $node->filter('.review-byline .author, [data-hook="review-author"]');
            $review['reviewer_name'] = $reviewerNode->count() > 0 ? trim($reviewerNode->text()) : '';

            // Extract review title
            $titleNode = $node->filter('.review-title, [data-hook="review-title"]');
            $review['review_title'] = $titleNode->count() > 0 ? trim($titleNode->text()) : '';

            // Extract review date
            $dateNode = $node->filter('.review-date, [data-hook="review-date"]');
            $review['review_date'] = $dateNode->count() > 0 ? trim($dateNode->text()) : '';

            // Extract verified purchase status
            $verifiedNode = $node->filter('[data-hook="avp-badge"], .avp-badge');
            $review['verified_purchase'] = $verifiedNode->count() > 0;
        } catch (\Exception $e) {
            LoggingService::log('Error extracting review fields', [
                'error' => $e->getMessage(),
            ]);
        }

        return $review;
    }

    /**
     * Format reviews from AJAX JSON response.
     */
    private function formatAjaxReviews(array $ajaxReviews): array
    {
        $formattedReviews = [];

        foreach ($ajaxReviews as $ajaxReview) {
            $formattedReviews[] = [
                'review_text'       => $ajaxReview['text'] ?? '',
                'rating'            => isset($ajaxReview['rating']) ? (float) $ajaxReview['rating'] : 0,
                'reviewer_name'     => $ajaxReview['author'] ?? '',
                'review_title'      => $ajaxReview['title'] ?? '',
                'review_date'       => $ajaxReview['date'] ?? '',
                'verified_purchase' => $ajaxReview['verified'] ?? false,
            ];
        }

        return $formattedReviews;
    }

    /**
     * Extract product description from HTML.
     */
    private function extractProductDescription(string $html): string
    {
        try {
            $crawler = new Crawler($html);
            $descNode = $crawler->filter('#feature-bullets ul, .feature, .product-description');

            return $descNode->count() > 0 ? trim($descNode->first()->text()) : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Extract total review count from HTML.
     */
    private function extractTotalReviewCount(string $html): int
    {
        if (preg_match('/(\d+(?:,\d+)*)\s+(?:customer\s+)?reviews?/i', $html, $matches)) {
            return (int) str_replace(',', '', $matches[1]);
        }

        return 0;
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
        if (!empty($foundIndicators)) {
            $contentSize = strlen($html);
            $this->handleCaptchaDetection($url, $foundIndicators, $contentSize);

            return true;
        }

        return false;
    }

    /**
     * Handle CAPTCHA detection by logging and sending alerts.
     */
    private function handleCaptchaDetection(string $url, array $indicators, int $contentSize): void
    {
        $contextData = [
            'indicators_found'       => $indicators,
            'content_size'           => $contentSize,
            'content_size_formatted' => $this->formatBytes($contentSize),
            'detection_method'       => 'ajax_captcha_detection',
            'service'                => 'AmazonAjaxReviewService',
            'proxy_type'             => $this->currentProxyConfig['type'] ?? 'direct',
            'timestamp'              => now()->toISOString(),
        ];

        // Add current cookie session information to context
        if ($this->currentCookieSession) {
            $contextData['cookie_session'] = [
                'name'    => $this->currentCookieSession['name'],
                'env_var' => $this->currentCookieSession['env_var'],
                'index'   => $this->currentCookieSession['index'],
            ];

            // Mark this session as unhealthy
            $this->cookieSessionManager->markSessionUnhealthy(
                $this->currentCookieSession['index'],
                'CAPTCHA detected in AJAX service',
                30 // 30 minute cooldown
            );
        }

        LoggingService::log('CAPTCHA/blocking detected in Amazon AJAX response (fallback service)', array_merge($contextData, [
            'url'  => $url,
            'note' => 'Production uses BrightData - this is fallback only',
        ]));

        // If using proxy, rotate it
        if ($this->currentProxyConfig) {
            $this->rotateProxyAndReconnect();
        }
    }

    /**
     * Format bytes for human-readable display.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * Rotate proxy and reconnect HTTP client.
     */
    private function rotateProxyAndReconnect(): void
    {
        if ($this->currentProxyConfig) {
            LoggingService::log('Rotating proxy due to CAPTCHA detection in AJAX service');
            $this->proxyManager->rotateSession();
            $this->initializeHttpClient(); // Reinitialize with new proxy session
        }
    }

    /**
     * Fallback to direct scraping when AJAX fails.
     */
    protected function fallbackToDirectScraping(string $asin): array
    {
        LoggingService::log('Falling back to direct scraping', [
            'asin'   => $asin,
            'reason' => 'AJAX method failed',
        ]);

        try {
            $directService = app()->make(AmazonScrapingService::class);

            return $directService->fetchReviews($asin);
        } catch (\Exception $e) {
            LoggingService::log('Direct scraping fallback also failed', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            return [
                'reviews'       => [],
                'description'   => '',
                'total_reviews' => 0,
            ];
        }
    }

    /**
     * Required methods for interface compliance.
     */
    public function fetchReviewsAndSave(string $asin, string $country, string $productUrl): AsinData
    {
        $result = $this->fetchReviews($asin, $country);

        // Save to database
        $asinData = AsinData::firstOrCreate(['asin' => $asin]);
        $asinData->reviews = json_encode($result['reviews']);
        $asinData->product_description = $result['description'];
        $asinData->total_reviews_on_amazon = $result['total_reviews'];
        $asinData->save();

        return $asinData;
    }

    public function fetchProductData(string $asin, string $country = 'us'): array
    {
        // Bootstrap session to get product data
        $sessionData = $this->bootstrapSession($asin);

        return [
            'title'         => '',
            'description'   => $sessionData['description'] ?? '',
            'price'         => '',
            'image_url'     => '',
            'rating'        => 0,
            'total_reviews' => $sessionData['total_reviews'] ?? 0,
        ];
    }
}
