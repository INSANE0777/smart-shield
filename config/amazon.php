<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Amazon Services Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for all Amazon-related services including
    | scraping, affiliate links, and review collection.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Review Collection Service
    |--------------------------------------------------------------------------
    |
    | Configure which service to use for collecting Amazon reviews.
    | Options: 'brightdata', 'scraping', 'ajax'
    |
    */
    'review_service' => env('AMAZON_REVIEW_SERVICE', 'brightdata'),

    /*
    |--------------------------------------------------------------------------
    | Scraping Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for direct Amazon scraping (fallback service).
    |
    */
    'scraping' => [
        'max_pages'              => env('AMAZON_SCRAPING_MAX_PAGES', 10),
        'target_reviews'         => env('AMAZON_SCRAPING_TARGET_REVIEWS', 30),
        'max_reviews'            => env('AMAZON_SCRAPING_MAX_REVIEWS', 100),
        'timeout'                => env('AMAZON_SCRAPING_TIMEOUT', 30),
        'user_agent'             => env('AMAZON_SCRAPING_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
        'delay_between_requests' => env('AMAZON_SCRAPING_DELAY', 2), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | BrightData Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for BrightData web scraper service (primary service).
    | Cost: ~$500/month for typical usage
    |
    */
    'brightdata' => [
        'api_key'              => env('BRIGHTDATA_API_KEY'),
        'base_url'             => env('BRIGHTDATA_BASE_URL', 'https://api.brightdata.com'),
        'timeout'              => env('BRIGHTDATA_TIMEOUT', 300), // 5 minutes
        'polling_interval'     => env('BRIGHTDATA_POLLING_INTERVAL', 30), // seconds between polls
        'max_polling_attempts' => env('BRIGHTDATA_MAX_POLLING', 40), // 20 minutes total polling
        'job_timeout_minutes'  => env('BRIGHTDATA_JOB_TIMEOUT', 30), // Cancel jobs after 30 minutes
        'max_concurrent_jobs'  => env('BRIGHTDATA_MAX_CONCURRENT', 90), // Stop creating jobs at 90 running
        'auto_cancel_enabled'  => env('BRIGHTDATA_AUTO_CANCEL', true), // Enable automatic job cancellation
        'stale_job_threshold'  => env('BRIGHTDATA_STALE_THRESHOLD', 60), // Jobs older than 60 minutes are stale
        'max_reviews'          => env('BRIGHTDATA_MAX_REVIEWS', 200), // Maximum reviews to process per ASIN
    ],

    /*
    |--------------------------------------------------------------------------
    | Affiliate Links
    |--------------------------------------------------------------------------
    |
    | Amazon affiliate tag configuration for different countries.
    |
    */
    'affiliate' => [
        'enabled' => env('AMAZON_AFFILIATE_ENABLED', true),
        'tags'    => [
            'us' => env('AMAZON_AFFILIATE_TAG_US'),
            'uk' => env('AMAZON_AFFILIATE_TAG_UK'),
            'ca' => env('AMAZON_AFFILIATE_TAG_CA'),
            'de' => env('AMAZON_AFFILIATE_TAG_DE'),
            'fr' => env('AMAZON_AFFILIATE_TAG_FR'),
            'it' => env('AMAZON_AFFILIATE_TAG_IT'),
            'es' => env('AMAZON_AFFILIATE_TAG_ES'),
            'jp' => env('AMAZON_AFFILIATE_TAG_JP'),
            'au' => env('AMAZON_AFFILIATE_TAG_AU'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Countries
    |--------------------------------------------------------------------------
    |
    | List of supported Amazon country domains and their mappings.
    |
    */
    'countries' => [
        'us' => ['domain' => 'amazon.com', 'name' => 'United States'],
        'gb' => ['domain' => 'amazon.co.uk', 'name' => 'United Kingdom'],
        'ca' => ['domain' => 'amazon.ca', 'name' => 'Canada'],
        'de' => ['domain' => 'amazon.de', 'name' => 'Germany'],
        'fr' => ['domain' => 'amazon.fr', 'name' => 'France'],
        'it' => ['domain' => 'amazon.it', 'name' => 'Italy'],
        'es' => ['domain' => 'amazon.es', 'name' => 'Spain'],
        'ie' => ['domain' => 'amazon.ie', 'name' => 'Ireland'],
        'jp' => ['domain' => 'amazon.co.jp', 'name' => 'Japan'],
        'au' => ['domain' => 'amazon.com.au', 'name' => 'Australia'],
        'mx' => ['domain' => 'amazon.com.mx', 'name' => 'Mexico'],
        'in' => ['domain' => 'amazon.in', 'name' => 'India'],
        'sg' => ['domain' => 'amazon.sg', 'name' => 'Singapore'],
        'br' => ['domain' => 'amazon.com.br', 'name' => 'Brazil'],
        'nl' => ['domain' => 'amazon.nl', 'name' => 'Netherlands'],
        'tr' => ['domain' => 'amazon.com.tr', 'name' => 'Turkey'],
        'ae' => ['domain' => 'amazon.ae', 'name' => 'UAE'],
        'sa' => ['domain' => 'amazon.sa', 'name' => 'Saudi Arabia'],
        'se' => ['domain' => 'amazon.se', 'name' => 'Sweden'],
        'pl' => ['domain' => 'amazon.pl', 'name' => 'Poland'],
        'eg' => ['domain' => 'amazon.eg', 'name' => 'Egypt'],
        'be' => ['domain' => 'amazon.be', 'name' => 'Belgium'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Session Management
    |--------------------------------------------------------------------------
    |
    | Configuration for managing multiple Amazon cookie sessions.
    |
    */
    'sessions' => [
        'rotation_enabled'      => env('AMAZON_SESSION_ROTATION', true),
        'health_check_interval' => env('AMAZON_SESSION_HEALTH_CHECK', 3600), // 1 hour
        'unhealthy_cooldown'    => env('AMAZON_SESSION_COOLDOWN', 1800), // 30 minutes
        'max_sessions'          => env('AMAZON_MAX_SESSIONS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration to avoid being blocked by Amazon.
    |
    */
    'rate_limiting' => [
        'requests_per_minute'  => env('AMAZON_RATE_LIMIT_RPM', 30),
        'burst_limit'          => env('AMAZON_RATE_LIMIT_BURST', 5),
        'cooldown_after_block' => env('AMAZON_COOLDOWN_MINUTES', 60), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxy Configuration
    |--------------------------------------------------------------------------
    |
    | Proxy settings for Amazon requests.
    |
    */
    'proxy' => [
        'enabled'           => env('AMAZON_PROXY_ENABLED', false),
        'provider'          => env('AMAZON_PROXY_PROVIDER', 'brightdata'),
        'rotation_enabled'  => env('AMAZON_PROXY_ROTATION', true),
        'failure_threshold' => env('AMAZON_PROXY_FAILURE_THRESHOLD', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Detection
    |--------------------------------------------------------------------------
    |
    | Settings for detecting and handling Amazon CAPTCHA challenges.
    |
    */
    'captcha' => [
        'detection_enabled' => env('AMAZON_CAPTCHA_DETECTION', true),
        'keywords'          => [
            'captcha', 'robot', 'automated', 'verify', 'security check',
            'unusual traffic', 'please confirm', 'prove you\'re human',
        ],
        'size_threshold'     => env('AMAZON_CAPTCHA_SIZE_THRESHOLD', 5000), // bytes
        'alert_on_detection' => env('AMAZON_CAPTCHA_ALERT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Validation
    |--------------------------------------------------------------------------
    |
    | Settings for validating Amazon product data.
    |
    */
    'validation' => [
        'asin_pattern' => '/^[A-Z0-9]{10}$/',
        'url_patterns' => [
            '/\/dp\/([A-Z0-9]{10})/',
            '/\/product\/([A-Z0-9]{10})/',
            '/\/product-reviews\/([A-Z0-9]{10})/',
            '/\/gp\/product\/([A-Z0-9]{10})/',
            '/ASIN=([A-Z0-9]{10})/',
            '/\/([A-Z0-9]{10})(?:\/|\?|$)/',
        ],
        'required_fields'        => ['asin', 'title', 'rating', 'reviews'],
        'max_title_length'       => 500,
        'max_description_length' => 5000,
    ],
];
