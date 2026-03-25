<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Async Analysis Mode
    |--------------------------------------------------------------------------
    |
    | This determines whether to use asynchronous queue-based analysis or
    | synchronous processing. Async mode prevents Cloudflare timeouts but
    | requires queue workers to be running.
    |
    */
    'async_enabled' => env('ANALYSIS_ASYNC_ENABLED', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Progress Polling Interval
    |--------------------------------------------------------------------------
    |
    | How often (in milliseconds) to poll for analysis progress updates
    | when using async mode.
    |
    */
    'polling_interval' => env('ANALYSIS_POLLING_INTERVAL', 2000), // 2 seconds

    /*
    |--------------------------------------------------------------------------
    | Session Cleanup
    |--------------------------------------------------------------------------
    |
    | How long to keep completed analysis sessions before cleanup.
    |
    */
    'session_cleanup_after' => env('ANALYSIS_SESSION_CLEANUP_HOURS', 24), // 24 hours

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings specific to analysis jobs.
    |
    */
    'queue' => [
        'connection'  => env('ANALYSIS_QUEUE_CONNECTION', 'analysis'),
        'timeout'     => env('ANALYSIS_QUEUE_TIMEOUT', 300), // 5 minutes
        'retry_after' => env('ANALYSIS_QUEUE_RETRY_AFTER', 360), // 6 minutes
        'max_tries'   => env('ANALYSIS_QUEUE_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Throughput Monitoring
    |--------------------------------------------------------------------------
    |
    | Alert if analysis throughput is below a minimum threshold within a time
    | window. Designed to catch stuck workers, traffic spikes, or external
    | dependency degradation before users notice.
    |
    */
    'throughput_monitoring' => [
        'enabled'                      => env('ANALYSIS_THROUGHPUT_MONITOR_ENABLED', true),
        'window_minutes'               => env('ANALYSIS_THROUGHPUT_WINDOW_MINUTES', 60),
        'min_analyzed_products'        => env('ANALYSIS_THROUGHPUT_MIN_PER_WINDOW', 5),
        'alert_cooldown_minutes'       => env('ANALYSIS_THROUGHPUT_ALERT_COOLDOWN_MINUTES', 60),
        'schedule_interval_minutes'    => env('ANALYSIS_THROUGHPUT_SCHEDULE_INTERVAL_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analysis Steps
    |--------------------------------------------------------------------------
    |
    | Configuration for the analysis process steps and their weights.
    |
    */
    'steps' => [
        'validation'         => ['weight' => 12, 'message' => 'Validating product URL...'],
        'authentication'     => ['weight' => 13, 'message' => 'Authenticating request...'],
        'database_check'     => ['weight' => 25, 'message' => 'Checking product database...'],
        'fetch_reviews'      => ['weight' => 52, 'message' => 'Gathering review information...'],
        'openai_analysis'    => ['weight' => 70, 'message' => 'Analyzing reviews with AI...'],
        'calculate_metrics'  => ['weight' => 85, 'message' => 'Computing authenticity metrics...'],
        'fetch_product_data' => ['weight' => 92, 'message' => 'Fetching product information...'],
        'finalize'           => ['weight' => 98, 'message' => 'Generating final report...'],
        'complete'           => ['weight' => 100, 'message' => 'Analysis complete!'],
    ],
];

