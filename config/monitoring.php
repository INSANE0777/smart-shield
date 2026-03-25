<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for system monitoring, alerting, and
    | performance tracking across all services.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Alert System
    |--------------------------------------------------------------------------
    |
    | Configuration for the centralized alerting system.
    |
    */
    'alerts' => [
        'enabled'            => env('ALERTS_ENABLED', true),
        'environment_prefix' => env('ALERT_ENV_PREFIX', true),
        'max_message_length' => 1024,
        'max_title_length'   => 250,

        // Alert Recipients
        'recipients' => [
            'pushover' => [
                'user'    => env('PUSHOVER_USER_KEY'),
                'token'   => env('PUSHOVER_APP_TOKEN'),
                'api_url' => env('PUSHOVER_API_URL', 'https://api.pushover.net/1/messages.json'),
            ],
            'email' => [
                'enabled' => env('ALERT_EMAIL_ENABLED', false),
                'to'      => env('ALERT_EMAIL_TO', env('MAIL_ADMIN_EMAIL')),
            ],
        ],

        // Enabled Alert Types
        'enabled_types' => [
            'amazon_session_expired' => env('ALERT_AMAZON_SESSION', true),
            'openai_quota_exceeded'  => env('ALERT_OPENAI_QUOTA', true),
            'openai_api_error'       => env('ALERT_OPENAI_API', true),
            'amazon_api_error'       => env('ALERT_AMAZON_API', true),
            'api_timeout'            => env('ALERT_API_TIMEOUT', true),
            'connectivity_issue'     => env('ALERT_CONNECTIVITY_ISSUE', true),
            'system_error'           => env('ALERT_SYSTEM_ERROR', true),
            'rate_limit_exceeded'    => env('ALERT_RATE_LIMIT', true),
            'database_error'         => env('ALERT_DATABASE_ERROR', true),
            'external_api_error'     => env('ALERT_EXTERNAL_API', true),
            'security_alert'         => env('ALERT_SECURITY', true),
            'performance_alert'      => env('ALERT_PERFORMANCE', true),
        ],

        // Throttling Settings
        'throttling' => [
            'enabled'     => env('ALERT_THROTTLING_ENABLED', true),
            'cache_store' => env('ALERT_THROTTLING_CACHE', 'default'),
        ],

        // Development Settings
        'development' => [
            'log_only'       => env('ALERT_LOG_ONLY', false),
            'test_recipient' => env('ALERT_TEST_RECIPIENT'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Settings for tracking system performance and resource usage.
    |
    */
    'performance' => [
        'enabled'                 => env('PERFORMANCE_MONITORING', true),
        'slow_query_threshold'    => env('SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'memory_usage_threshold'  => env('MEMORY_THRESHOLD', 128), // MB
        'response_time_threshold' => env('RESPONSE_TIME_THRESHOLD', 5000), // milliseconds

        // Metrics Collection
        'metrics' => [
            'llm_response_times'     => env('TRACK_LLM_METRICS', true),
            'scraping_success_rates' => env('TRACK_SCRAPING_METRICS', true),
            'queue_processing_times' => env('TRACK_QUEUE_METRICS', true),
            'api_error_rates'        => env('TRACK_API_METRICS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Configuration for system health monitoring.
    |
    */
    'health_checks' => [
        'enabled'  => env('HEALTH_CHECKS_ENABLED', true),
        'interval' => env('HEALTH_CHECK_INTERVAL', 300), // 5 minutes

        'checks' => [
            'database' => [
                'enabled'  => true,
                'timeout'  => 5,
                'critical' => true,
            ],
            'queue' => [
                'enabled'  => true,
                'timeout'  => 10,
                'critical' => true,
            ],
            'cache' => [
                'enabled'  => true,
                'timeout'  => 5,
                'critical' => false,
            ],
            'external_apis' => [
                'enabled'  => env('HEALTH_CHECK_APIS', true),
                'timeout'  => 15,
                'critical' => false,
                'apis'     => [
                    'openai'     => env('OPENAI_API_KEY') ? true : false,
                    'brightdata' => env('BRIGHTDATA_API_KEY') ? true : false,
                    'ollama'     => env('OLLAMA_BASE_URL') ? true : false,
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Monitoring
    |--------------------------------------------------------------------------
    |
    | Monitor specific services and their health status.
    |
    */
    'services' => [
        'brightdata' => [
            'enabled'                => env('MONITOR_BRIGHTDATA', true),
            'timeout_threshold'      => 300, // 5 minutes
            'failure_threshold'      => 3, // failures before alert
            'success_rate_threshold' => 0.8, // 80% success rate
        ],

        'llm_providers' => [
            'enabled'                 => env('MONITOR_LLM', true),
            'response_time_threshold' => 30, // seconds
            'error_rate_threshold'    => 0.1, // 10% error rate
            'cost_tracking'           => env('LLM_COST_TRACKING', true),
        ],

        'amazon_scraping' => [
            'enabled'                     => env('MONITOR_SCRAPING', true),
            'captcha_detection_threshold' => 0.2, // 20% captcha rate
            'session_health_threshold'    => 0.7, // 70% healthy sessions
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Monitoring-specific logging settings.
    |
    */
    'logging' => [
        'level'    => env('MONITORING_LOG_LEVEL', 'info'),
        'channels' => [
            'alerts'        => env('ALERT_LOG_CHANNEL', 'single'),
            'performance'   => env('PERFORMANCE_LOG_CHANNEL', 'single'),
            'health_checks' => env('HEALTH_LOG_CHANNEL', 'single'),
        ],
        'retention_days' => env('MONITORING_LOG_RETENTION', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Configure different notification channels for different alert types.
    |
    */
    'notification_channels' => [
        'critical' => ['pushover'],
        'warning'  => ['pushover'],
        'info'     => ['log'],
        'debug'    => ['log'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring dashboard and metrics display.
    |
    */
    'dashboard' => [
        'enabled'           => env('MONITORING_DASHBOARD', false),
        'refresh_interval'  => env('DASHBOARD_REFRESH', 30), // seconds
        'metrics_retention' => env('METRICS_RETENTION_HOURS', 168), // 7 days
        'real_time_updates' => env('DASHBOARD_REALTIME', true),
    ],
];
