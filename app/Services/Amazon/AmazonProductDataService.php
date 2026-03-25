<?php

namespace App\Services\Amazon;

use App\Models\AsinData;
use App\Services\LoggingService;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Service for scraping Amazon product data (title, description, main image).
 *
 * This service focuses on extracting basic product information rather than reviews,
 * using multiple cookie sessions with round-robin rotation to reduce blocking.
 */
class AmazonProductDataService
{
    private Client $httpClient;
    private CookieJar $cookieJar;
    private array $headers;
    private CookieSessionManager $cookieSessionManager;
    private ?array $currentCookieSession = null;

    /**
     * Pool of realistic user agents for rotation.
     */
    private array $userAgents = [
        // Chrome on Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        // Chrome on Mac
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        // Firefox on Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0',
        // Firefox on Mac
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:133.0) Gecko/20100101 Firefox/133.0',
        // Safari on Mac
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        // Edge on Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
    ];

    /**
     * Initialize the service with HTTP client configuration.
     */
    public function __construct()
    {
        $this->cookieSessionManager = new CookieSessionManager();
        $this->setupCookies();

        $this->headers = $this->getRandomizedHeaders();

        $this->initializeHttpClient();
    }

    /**
     * Get randomized headers to avoid detection patterns.
     */
    private function getRandomizedHeaders(): array
    {
        $userAgent = $this->userAgents[array_rand($this->userAgents)];
        $isChrome = str_contains($userAgent, 'Chrome') && !str_contains($userAgent, 'Edg');
        $isFirefox = str_contains($userAgent, 'Firefox');
        $isEdge = str_contains($userAgent, 'Edg');
        $isSafari = str_contains($userAgent, 'Safari') && !str_contains($userAgent, 'Chrome');

        $headers = [
            'User-Agent'                => $userAgent,
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language'           => $this->getRandomAcceptLanguage(),
            'Accept-Encoding'           => 'gzip, deflate, br',
            'Connection'                => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ];

        // Add browser-specific headers
        if ($isChrome || $isEdge) {
            $version = $isEdge ? '131' : (string) rand(129, 131);
            $headers['sec-ch-ua'] = $isEdge
                ? "\"Microsoft Edge\";v=\"{$version}\", \"Chromium\";v=\"{$version}\", \"Not_A Brand\";v=\"24\""
                : "\"Google Chrome\";v=\"{$version}\", \"Chromium\";v=\"{$version}\", \"Not_A Brand\";v=\"24\"";
            $headers['sec-ch-ua-mobile'] = '?0';
            $headers['sec-ch-ua-platform'] = str_contains($userAgent, 'Windows') ? '"Windows"' : '"macOS"';
            $headers['Sec-Fetch-Dest'] = 'document';
            $headers['Sec-Fetch-Mode'] = 'navigate';
            $headers['Sec-Fetch-Site'] = 'none';
            $headers['Sec-Fetch-User'] = '?1';
        }

        return $headers;
    }

    /**
     * Get random Accept-Language header.
     */
    private function getRandomAcceptLanguage(): string
    {
        $languages = [
            'en-US,en;q=0.9',
            'en-GB,en-US;q=0.9,en;q=0.8',
            'en-CA,en-US;q=0.9,en;q=0.8',
            'en-AU,en-US;q=0.9,en;q=0.8',
            'en-US,en;q=0.9,es;q=0.8',
        ];

        return $languages[array_rand($languages)];
    }

    /**
     * Initialize HTTP client.
     */
    private function initializeHttpClient(): void
    {
        $this->httpClient = new Client([
            'timeout'         => 30,
            'connect_timeout' => 15,
            'http_errors'     => false,
            'verify'          => false,
            'cookies'         => $this->cookieJar,
            'headers'         => $this->headers,
            'allow_redirects' => [
                'max'     => 5,
                'strict'  => false,
                'referer' => true,
            ],
        ]);
    }

    /**
     * Setup cookies using the multi-session cookie manager.
     */
    private function setupCookies(): void
    {
        // Get the next available cookie session using round-robin rotation
        $this->currentCookieSession = $this->cookieSessionManager->getNextCookieSession();

        if (!$this->currentCookieSession) {
            LoggingService::log('No Amazon cookie sessions available for product data scraping - falling back to legacy AMAZON_COOKIES');
            $this->setupLegacyCookies();

            return;
        }

        // Create cookie jar from the selected session
        $this->cookieJar = $this->cookieSessionManager->createCookieJar($this->currentCookieSession);

        LoggingService::log('Setup product data cookies from multi-session manager', [
            'session_name'    => $this->currentCookieSession['name'],
            'session_env_var' => $this->currentCookieSession['env_var'],
        ]);
    }

    /**
     * Fallback method to setup cookies from legacy AMAZON_COOKIES environment variable.
     */
    private function setupLegacyCookies(): void
    {
        $cookieString = env('AMAZON_COOKIES', '');

        if (empty($cookieString)) {
            LoggingService::log('No Amazon cookies configured for product data scraping');
            $this->cookieJar = new CookieJar();

            return;
        }

        $this->cookieJar = new CookieJar();

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
                'Domain'   => '.amazon.com', // Note: cookies might not work for international domains without session management
                'Path'     => '/',
                'Secure'   => true,
                'HttpOnly' => true,
            ]));
        }

        LoggingService::log('Loaded '.count($cookies).' Amazon cookies from legacy configuration for product data');
    }

    /**
     * Scrape product data and save to database.
     *
     * @param \App\Models\AsinData $asinData The ASIN data record to update
     *
     * @return bool True if successful, false otherwise
     */
    public function scrapeAndSaveProductData(\App\Models\AsinData $asinData): bool
    {
        try {
            $productData = $this->scrapeProductData($asinData->asin, $asinData->country);

            if (empty($productData)) {
                LoggingService::log('No product data scraped', [
                    'asin'    => $asinData->asin,
                    'country' => $asinData->country,
                ]);

                return false;
            }

            // Update the database record
            $asinData->update([
                'product_title'           => $productData['title'] ?? null,
                'product_description'     => $productData['description'] ?? null,
                'product_image_url'       => $productData['image_url'] ?? null,
                'have_product_data'       => true,
                'product_data_scraped_at' => now(),
            ]);

            LoggingService::log('Successfully updated product data', [
                'asin'      => $asinData->asin,
                'title'     => $productData['title'] ?? 'N/A',
                'has_image' => !empty($productData['image_url']),
            ]);

            return true;
        } catch (\Exception $e) {
            LoggingService::log('Failed to scrape and save product data', [
                'asin'  => $asinData->asin,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Scrape product data from Amazon.
     *
     * @param string $asin    Amazon Standard Identification Number
     * @param string $country Two-letter country code (defaults to 'us')
     *
     * @return array Array containing title, description, and image_url
     */
    public function scrapeProductData(string $asin, string $country = 'us'): array
    {
        LoggingService::log('Starting Amazon product data scraping', [
            'asin'    => $asin,
            'country' => $country,
        ]);

        // In testing environment without cookies, return mock data to prevent failures
        if (!$this->hasCookiesConfigured() && app()->environment('testing')) {
            LoggingService::log('No cookies configured in test environment - returning mock data', [
                'asin'        => $asin,
                'environment' => app()->environment(),
            ]);

            return [
                'title'       => "Test Product {$asin}",
                'description' => 'Mock product description for testing',
                'image_url'   => 'https://via.placeholder.com/300x300?text='.$asin,
            ];
        }

        // Check cache first - product data doesn't change often
        $cacheKey = "product_data_{$asin}_{$country}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            LoggingService::log('Using cached product data', [
                'asin'      => $asin,
                'cache_key' => $cacheKey,
            ]);

            return $cachedData;
        }

        try {
            // First try Product Advertising API if available
            $productData = $this->tryProductAdvertisingApi($asin, $country);

            if (empty($productData)) {
                // Fallback to web scraping
                $productData = $this->scrapeFromWebsite($asin, $country);
            }

            if (!empty($productData)) {
                // Cache the result for 6 hours
                Cache::put($cacheKey, $productData, now()->addHours(6));

                LoggingService::log('Successfully scraped product data', [
                    'asin'      => $asin,
                    'title'     => $productData['title'] ?? 'N/A',
                    'has_image' => !empty($productData['image_url']),
                ]);
            }

            return $productData;
        } catch (\Exception $e) {
            LoggingService::log('Failed to scrape product data', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Try to get product data from Amazon Product Advertising API.
     */
    private function tryProductAdvertisingApi(string $asin, string $country): array
    {
        // Check if PA-API credentials are configured
        $accessKey = env('AMAZON_PA_API_ACCESS_KEY');
        $secretKey = env('AMAZON_PA_API_SECRET_KEY');
        $partnerTag = env('AMAZON_PA_API_PARTNER_TAG');

        if (empty($accessKey) || empty($secretKey) || empty($partnerTag)) {
            LoggingService::log('Amazon PA-API credentials not configured, skipping API approach');

            return [];
        }

        LoggingService::log('Attempting to fetch product data via Amazon PA-API', [
            'asin'    => $asin,
            'country' => $country,
        ]);

        // TODO: Implement PA-API integration
        // For now, we'll return empty array to fall back to scraping
        LoggingService::log('PA-API integration not yet implemented, falling back to scraping');

        return [];
    }

    /**
     * Scrape product data from Amazon website.
     */
    private function scrapeFromWebsite(string $asin, string $country): array
    {
        // Build country-specific Amazon URL
        $domains = [
            'us' => 'amazon.com',
            'gb' => 'amazon.co.uk',
            'ca' => 'amazon.ca',
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

        $domain = $domains[$country] ?? $domains['us'];
        $url = "https://www.{$domain}/dp/{$asin}";

        LoggingService::log('Scraping product data from website', [
            'url'     => $url,
            'asin'    => $asin,
            'country' => $country,
            'domain'  => $domain,
        ]);

        try {
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                LoggingService::log('Non-200 status code received', [
                    'status' => $statusCode,
                    'asin'   => $asin,
                ]);

                return [];
            }

            $html = $response->getBody()->getContents();

            if (empty($html)) {
                LoggingService::log('Empty response received', ['asin' => $asin]);

                return [];
            }

            return $this->parseProductDataFromHtml($html, $asin);
        } catch (\Exception $e) {
            LoggingService::log('Error scraping product data from website', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if cookies are configured for scraping.
     */
    private function hasCookiesConfigured(): bool
    {
        // Check if we have multi-session cookies
        if ($this->currentCookieSession !== null) {
            return true;
        }

        // Check if we have legacy cookies
        $legacyCookies = env('AMAZON_COOKIES', '');
        if (!empty($legacyCookies)) {
            return true;
        }

        return false;
    }

    /**
     * Parse product data from HTML content.
     */
    private function parseProductDataFromHtml(string $html, string $asin): array
    {
        try {
            $crawler = new Crawler($html);
            $productData = [];

            // Extract product title
            $productData['title'] = $this->extractProductTitle($crawler);

            // Extract main product image
            $productData['image_url'] = $this->extractProductImage($crawler);

            // Extract product description (optional, since it's already in the database)
            $productData['description'] = $this->extractProductDescription($crawler);

            // Extract product category from breadcrumb - only store category_tags (single source of truth)
            $categoryData = $this->extractProductCategory($crawler);
            if ($categoryData && !empty($categoryData['category_tags'])) {
                $productData['category_tags'] = $categoryData['category_tags'];
            }

            // NOTE: Total review count is handled by the review service (BrightData/etc)
            // Product scraping only handles title, image, description, and category_tags

            LoggingService::log('Parsed product data from HTML', [
                'asin'              => $asin,
                'title_found'       => !empty($productData['title']),
                'image_found'       => !empty($productData['image_url']),
                'description_found' => !empty($productData['description']),
                'category_found'    => !empty($productData['category_tags']),
            ]);

            return array_filter($productData); // Remove empty values
        } catch (\Exception $e) {
            LoggingService::log('Error parsing product data from HTML', [
                'asin'  => $asin,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Extract product title from HTML.
     */
    private function extractProductTitle(Crawler $crawler): ?string
    {
        // First try meta tags (most reliable for social sharing)
        $metaSelectors = [
            'meta[name="title"]',
            'meta[property="og:title"]',
            'meta[name="twitter:title"]',
            'title',
        ];

        foreach ($metaSelectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    $title = '';
                    if ($selector === 'title') {
                        $title = trim($element->text());
                    } else {
                        $title = trim($element->attr('content'));
                    }

                    if (!empty($title)) {
                        // Clean up Amazon-specific prefixes/suffixes
                        $title = preg_replace('/^Amazon\.com:\s*/', '', $title);
                        $title = preg_replace('/\s*:\s*[^:]*&\s*[^:]*$/', '', $title); // Remove " : Category & Subcategory"
                        $title = trim($title);

                        LoggingService::log('Found product title from meta', [
                            'selector'     => $selector,
                            'title_length' => strlen($title),
                        ]);

                        return $title;
                    }
                }
            } catch (\Exception $e) {
                // Continue to next selector
                continue;
            }
        }

        // Fallback to traditional selectors
        $titleSelectors = [
            '#productTitle',
            '.product-title',
            '[data-automation-id="product-title"]',
            'h1.a-size-large',
            'h1.a-size-base-plus',
            'span[data-automation-id="product-title"]',
        ];

        foreach ($titleSelectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    $title = trim($element->text());
                    if (!empty($title)) {
                        LoggingService::log('Found product title from DOM', [
                            'selector'     => $selector,
                            'title_length' => strlen($title),
                        ]);

                        return $title;
                    }
                }
            } catch (\Exception $e) {
                // Continue to next selector
                continue;
            }
        }

        LoggingService::log('No product title found with any selector');

        return null;
    }

    /**
     * Extract main product image from HTML.
     */
    private function extractProductImage(Crawler $crawler): ?string
    {
        // First try meta tags (most reliable for social sharing)
        $metaImageSelectors = [
            'meta[property="og:image"]',
            'meta[name="twitter:image"]',
            'meta[property="og:image:url"]',
        ];

        foreach ($metaImageSelectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    $imageUrl = trim($element->attr('content'));
                    if (!empty($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        LoggingService::log('Found product image from meta', [
                            'selector'  => $selector,
                            'image_url' => $imageUrl,
                        ]);

                        return $imageUrl;
                    }
                }
            } catch (\Exception $e) {
                // Continue to next selector
                continue;
            }
        }

        // Try to extract from JSON data-a-state script (modern Amazon)
        try {
            $scriptElements = $crawler->filter('script[type="a-state"]');
            foreach ($scriptElements as $script) {
                $dataState = $script->getAttribute('data-a-state');
                if (!empty($dataState)) {
                    $decodedState = json_decode(html_entity_decode($dataState), true);
                    if (isset($decodedState['key']) && $decodedState['key'] === 'desktop-landing-image-data') {
                        $scriptContent = trim($script->textContent);
                        if (!empty($scriptContent)) {
                            $imageData = json_decode($scriptContent, true);
                            if (isset($imageData['landingImageUrl']) && filter_var($imageData['landingImageUrl'], FILTER_VALIDATE_URL)) {
                                LoggingService::log('Found product image from a-state data', [
                                    'image_url' => $imageData['landingImageUrl'],
                                ]);

                                return $imageData['landingImageUrl'];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            LoggingService::log('Error extracting image from a-state data', ['error' => $e->getMessage()]);
        }

        // Fallback to traditional selectors
        $imageSelectors = [
            '#landingImage',
            '#imgBlkFront',
            '#main-image',
            '.a-dynamic-image',
            '[data-a-dynamic-image]',
            'img.a-dynamic-image',
            '#ebooksImgBlkFront',
        ];

        foreach ($imageSelectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    // Try to get the src attribute
                    $src = $element->attr('src');
                    if (!empty($src) && filter_var($src, FILTER_VALIDATE_URL)) {
                        LoggingService::log('Found product image', [
                            'selector'  => $selector,
                            'image_url' => $src,
                        ]);

                        return $src;
                    }

                    // Try to get data-a-dynamic-image attribute
                    $dynamicImage = $element->attr('data-a-dynamic-image');
                    if (!empty($dynamicImage)) {
                        $imageData = json_decode($dynamicImage, true);
                        if (is_array($imageData) && !empty($imageData)) {
                            $imageUrl = array_key_first($imageData);
                            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                                LoggingService::log('Found product image from dynamic data', [
                                    'selector'  => $selector,
                                    'image_url' => $imageUrl,
                                ]);

                                return $imageUrl;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to next selector
                continue;
            }
        }

        LoggingService::log('No product image found with any selector');

        return null;
    }

    /**
     * Extract product description from HTML.
     * Prioritizes detailed content like feature bullets over short meta descriptions.
     */
    private function extractProductDescription(Crawler $crawler): ?string
    {
        // First try detailed content selectors (feature bullets, product details, etc.)
        $detailedContentSelectors = [
            '#feature-bullets ul',  // Feature bullets list
            '#feature-bullets',     // Feature bullets container
            '[data-feature-name="featurebullets"]',  // Modern feature bullets
            '#productDescription',  // Product description section
            '#aplus_feature_div',   // Enhanced brand content
            '.a-unordered-list.a-vertical.a-spacing-mini',  // Bullet points
            '[data-feature-name="productDescription"]',  // Product description attribute
            '#bookDescription_feature_div',  // Book descriptions
            '#productDetails_feature_div',   // Product details
            '.a-section.a-spacing-medium.bucketDivider',  // Product sections
        ];

        foreach ($detailedContentSelectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    $description = trim($element->text());
                    if (!empty($description) && strlen($description) > 50) {
                        $cleanedDescription = $this->cleanProductDescription($description);
                        if (!empty($cleanedDescription) && strlen($cleanedDescription) > 50) {
                            LoggingService::log('Found detailed product content', [
                                'selector'           => $selector,
                                'description_length' => strlen($cleanedDescription),
                                'original_length'    => strlen($description),
                            ]);

                            return $cleanedDescription;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to next selector
                continue;
            }
        }

        // Fallback to meta tags (shorter descriptions)
        $metaDescriptionSelectors = [
            'meta[name="description"]',
            'meta[property="og:description"]',
            'meta[name="twitter:description"]',
        ];

        foreach ($metaDescriptionSelectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    $description = trim($element->attr('content'));
                    if (!empty($description) && strlen($description) > 20) {
                        $cleanedDescription = $this->cleanProductDescription($description);
                        if (!empty($cleanedDescription)) {
                            LoggingService::log('Found product description from meta (fallback)', [
                                'selector'           => $selector,
                                'description_length' => strlen($cleanedDescription),
                                'original_length'    => strlen($description),
                            ]);

                            return $cleanedDescription;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to next selector
                continue;
            }
        }

        LoggingService::log('No product description found with any selector');

        return null;
    }

    /**
     * Clean product description by removing Amazon domain prefixes and formatting.
     */
    private function cleanProductDescription(string $description): ?string
    {
        // Use regex to remove Amazon domain prefixes (case-insensitive)
        // This pattern matches "Amazon" followed by optional country domains, then ":" and optional space
        $pattern = '/^Amazon(?:\.(?:com|ca|co\.uk|de|fr|it|es|com\.au|in|com\.br|com\.mx|co\.jp))?\s*:\s*/i';

        $cleaned = preg_replace($pattern, '', $description);
        $cleaned = trim($cleaned);

        // Remove common Amazon boilerplate text
        $boilerplatePatterns = [
            '/Visit the .+ Store/',
            '/Brand: .+?\n/',
            '/\n\s*Learn more\s*$/',
            '/\n\s*See more\s*$/',
            '/\n\s*Read more\s*$/',
            '/\s*\[.*?\]\s*/', // Remove bracketed text like [See more]
        ];

        foreach ($boilerplatePatterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        // Clean up excessive whitespace and normalize line breaks
        $cleaned = preg_replace('/\s{3,}/', ' ', $cleaned); // Multiple spaces to single
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned); // Multiple newlines to double
        $cleaned = trim($cleaned);

        // If the cleaned description is too short or empty, return null
        if (empty($cleaned) || strlen($cleaned) < 20) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Extract product category from Amazon breadcrumb navigation.
     *
     * Amazon breadcrumbs are typically in a div with id="wayfinding-breadcrumbs_feature_div"
     * and contain links like: Electronics › Computers & Accessories › Networking Products › Routers
     *
     * Returns:
     * - category: The leaf category (e.g., "Routers")
     * - category_path: Full path as string (e.g., "Electronics > Computers > Networking > Routers")
     * - category_tags: Array of all categories (e.g., ["Electronics", "Computers", "Networking", "Routers"])
     *
     * @return array{category: string, category_path: string, category_tags: array}|null
     */
    private function extractProductCategory(Crawler $crawler): ?array
    {
        // Multiple selectors for breadcrumb extraction (Amazon uses different structures)
        $breadcrumbSelectors = [
            '#wayfinding-breadcrumbs_feature_div',
            '#wayfinding-breadcrumbs_container',
            '.a-breadcrumb',
            '[data-csa-c-type="widget"][data-csa-c-content-id*="breadcrumb"]',
            '.nav-a-content', // Category nav
        ];

        foreach ($breadcrumbSelectors as $selector) {
            try {
                $element = $crawler->filter($selector);
                if ($element->count() > 0) {
                    // Try to get all breadcrumb links
                    $links = $element->filter('a');
                    if ($links->count() > 0) {
                        $categories = [];
                        $links->each(function (Crawler $link) use (&$categories) {
                            $text = trim($link->text());
                            // Filter out empty, navigation, or generic text
                            if (!empty($text) &&
                                !in_array(strtolower($text), ['back to results', 'see all', '‹', '›', '']) &&
                                strlen($text) > 1) {
                                $categories[] = $text;
                            }
                        });

                        if (!empty($categories)) {
                            // Last category is the leaf (most specific)
                            $leafCategory = end($categories);
                            // Full path joined with " > "
                            $categoryPath = implode(' > ', $categories);

                            LoggingService::log('Found product category from breadcrumb', [
                                'selector'      => $selector,
                                'category'      => $leafCategory,
                                'category_path' => $categoryPath,
                                'category_tags' => $categories,
                                'depth'         => count($categories),
                            ]);

                            return [
                                'category'      => $leafCategory,
                                'category_path' => $categoryPath,
                                'category_tags' => $categories,
                            ];
                        }
                    }

                    // Fallback: try to extract from text content with separators
                    $text = $element->text();
                    if (!empty($text)) {
                        // Amazon uses › or > as separators
                        $separators = ['›', '>', '|', '/'];
                        foreach ($separators as $sep) {
                            if (str_contains($text, $sep)) {
                                $parts = array_map('trim', explode($sep, $text));
                                $parts = array_filter($parts, function ($part) {
                                    return !empty($part) &&
                                           strlen($part) > 1 &&
                                           !in_array(strtolower($part), ['back to results', 'see all']);
                                });
                                // Re-index after filter
                                $parts = array_values($parts);

                                if (count($parts) >= 1) {
                                    $leafCategory = end($parts);
                                    $categoryPath = implode(' > ', $parts);

                                    LoggingService::log('Found product category from text parsing', [
                                        'selector'      => $selector,
                                        'category'      => $leafCategory,
                                        'category_path' => $categoryPath,
                                        'category_tags' => $parts,
                                        'depth'         => count($parts),
                                    ]);

                                    return [
                                        'category'      => $leafCategory,
                                        'category_path' => $categoryPath,
                                        'category_tags' => $parts,
                                    ];
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue to next selector
                continue;
            }
        }

        LoggingService::log('No product category found with any selector');

        return null;
    }

    /**
     * Scrape categories for multiple products in parallel using HTTP pool.
     *
     * @param array<AsinData> $products    Array of AsinData models
     * @param int             $concurrency Number of concurrent requests
     *
     * @return array<int, array|null> Map of product_id => category_data
     */
    public function scrapeCategoriesBatchParallel(array $products, int $concurrency = 5): array
    {
        if (empty($products)) {
            return ['results' => [], 'throttled' => 0];
        }

        LoggingService::log('Starting parallel category scraping', [
            'product_count' => count($products),
            'concurrency'   => $concurrency,
        ]);

        // Build URLs for each product
        $productUrls = [];
        foreach ($products as $product) {
            $productUrls[$product->id] = [
                'product' => $product,
                'url'     => $this->buildProductUrl($product->asin, $product->country),
            ];
        }

        // Use Laravel HTTP pool for parallel requests with randomized headers per request
        $responses = Http::pool(function ($pool) use ($productUrls) {
            foreach ($productUrls as $productId => $data) {
                // Each request gets fresh randomized headers to avoid pattern detection
                $headers = $this->getRandomizedHeaders();

                $pool->as($productId)
                    ->withHeaders($headers)
                    ->timeout(30)
                    ->connectTimeout(15)
                    ->withOptions(['verify' => false])
                    ->get($data['url']);
            }
        });

        // Process responses and extract categories
        $results = [];
        $success = 0;
        $failed = 0;
        $throttled = 0;

        foreach ($productUrls as $productId => $data) {
            try {
                $response = $responses[$productId] ?? null;

                if (!$response || !$response->successful()) {
                    $status = $response ? $response->status() : 0;

                    // Detect throttling (429 Too Many Requests, 503 Service Unavailable)
                    if (in_array($status, [429, 503])) {
                        $throttled++;
                        LoggingService::log('Amazon throttling detected', [
                            'product_id' => $productId,
                            'asin'       => $data['product']->asin,
                            'status'     => $status,
                        ]);
                    } else {
                        LoggingService::log('Failed to fetch product page for category', [
                            'product_id' => $productId,
                            'asin'       => $data['product']->asin,
                            'status'     => $status ?: 'no_response',
                        ]);
                    }

                    $failed++;
                    $results[$productId] = null;

                    continue;
                }

                $html = $response->body();
                if (empty($html)) {
                    $failed++;
                    $results[$productId] = null;

                    continue;
                }

                // Detect CAPTCHA in response (Amazon returns 200 but shows CAPTCHA)
                if (str_contains($html, 'captcha') || str_contains($html, 'robot check') ||
                    str_contains($html, 'Type the characters') || str_contains($html, 'api-services-support@amazon')) {
                    $throttled++;
                    $failed++;
                    $results[$productId] = null;
                    LoggingService::log('Amazon CAPTCHA detected', [
                        'product_id' => $productId,
                        'asin'       => $data['product']->asin,
                    ]);

                    continue;
                }

                // Parse category from HTML
                $crawler = new Crawler($html);
                $categoryData = $this->extractProductCategory($crawler);

                if ($categoryData && !empty($categoryData['category_tags'])) {
                    $success++;
                    $results[$productId] = [
                        'category_tags' => $categoryData['category_tags'],
                    ];
                } else {
                    $failed++;
                    $results[$productId] = null;
                }
            } catch (\Exception $e) {
                $failed++;
                $results[$productId] = null;
                LoggingService::log('Error parsing category from HTML', [
                    'product_id' => $productId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        LoggingService::log('Completed parallel category scraping', [
            'total'     => count($products),
            'success'   => $success,
            'failed'    => $failed,
            'throttled' => $throttled,
        ]);

        // Explicitly unset responses to release HTTP connections and prevent "too many open files"
        unset($responses);

        // Return results with throttle count for command to display warning
        return [
            'results'   => $results,
            'throttled' => $throttled,
        ];
    }

    /**
     * Scrape a single product's category with proper anti-detection measures.
     *
     * @return array{category_tags: array<string>}|null
     */
    public function scrapeSingleProductCategory(AsinData $product, int $baseDelayMs = 1000): ?array
    {
        // Randomize delay to avoid pattern detection (±30%)
        $jitter = rand(-30, 30) / 100;
        $actualDelay = (int) ($baseDelayMs * (1 + $jitter));
        usleep($actualDelay * 1000);

        // Get fresh randomized headers for this request
        $headers = $this->getRandomizedHeaders();

        $url = $this->buildProductUrl($product->asin, $product->country);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->connectTimeout(15)
                ->withOptions(['verify' => false])
                ->get($url);

            // Check for throttling
            if (in_array($response->status(), [429, 503])) {
                LoggingService::log('Amazon throttling on single request', [
                    'asin'   => $product->asin,
                    'status' => $response->status(),
                ]);

                return null;
            }

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();

            // Check for CAPTCHA
            if (str_contains($html, 'captcha') || str_contains($html, 'robot check') ||
                str_contains($html, 'api-services-support@amazon')) {
                LoggingService::log('Amazon CAPTCHA on single request', ['asin' => $product->asin]);

                return null;
            }

            $crawler = new Crawler($html);
            $categoryData = $this->extractProductCategory($crawler);

            if ($categoryData && !empty($categoryData['category_tags'])) {
                return ['category_tags' => $categoryData['category_tags']];
            }

            return null;
        } catch (\Exception $e) {
            LoggingService::log('Error scraping single product category', [
                'asin'  => $product->asin,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the Amazon product URL for a given ASIN and country.
     */
    public function buildProductUrl(string $asin, string $country = 'us'): string
    {
        $domains = [
            'us' => 'amazon.com',
            'gb' => 'amazon.co.uk',
            'ca' => 'amazon.ca',
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

        $domain = $domains[$country] ?? $domains['us'];

        return "https://www.{$domain}/dp/{$asin}";
    }
}
