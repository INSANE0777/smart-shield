<?php

namespace App\Enums;

enum AlertType: string
{
    case AMAZON_SESSION_EXPIRED = 'amazon_session_expired';
    case OPENAI_QUOTA_EXCEEDED = 'openai_quota_exceeded';
    case OPENAI_API_ERROR = 'openai_api_error';
    case AMAZON_API_ERROR = 'amazon_api_error';
    case API_TIMEOUT = 'api_timeout';
    case CONNECTIVITY_ISSUE = 'connectivity_issue';
    case SYSTEM_ERROR = 'system_error';
    case RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    case DATABASE_ERROR = 'database_error';
    case EXTERNAL_API_ERROR = 'external_api_error';
    case SECURITY_ALERT = 'security_alert';
    case PERFORMANCE_ALERT = 'performance_alert';

    /**
     * Get the display name for the alert type.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::AMAZON_SESSION_EXPIRED => 'Amazon Session Expired',
            self::OPENAI_QUOTA_EXCEEDED  => 'OpenAI Quota Exceeded',
            self::OPENAI_API_ERROR       => 'OpenAI API Error',
            self::AMAZON_API_ERROR       => 'Amazon API Error',
            self::API_TIMEOUT            => 'API Timeout',
            self::CONNECTIVITY_ISSUE     => 'Connectivity Issue',
            self::SYSTEM_ERROR           => 'System Error',
            self::RATE_LIMIT_EXCEEDED    => 'Rate Limit Exceeded',
            self::DATABASE_ERROR         => 'Database Error',
            self::EXTERNAL_API_ERROR     => 'External API Error',
            self::SECURITY_ALERT         => 'Security Alert',
            self::PERFORMANCE_ALERT      => 'Performance Alert',
        };
    }

    /**
     * Get the default priority for this alert type.
     */
    public function getDefaultPriority(): int
    {
        return match ($this) {
            self::AMAZON_SESSION_EXPIRED => 1, // High priority
            self::OPENAI_QUOTA_EXCEEDED  => 1, // High priority
            self::SECURITY_ALERT         => 2, // Emergency priority
            self::DATABASE_ERROR         => 2, // Emergency priority
            self::SYSTEM_ERROR           => 1, // High priority
            self::API_TIMEOUT            => 0, // Normal priority
            self::CONNECTIVITY_ISSUE     => 1, // High priority - might indicate broader issues
            self::OPENAI_API_ERROR       => 0, // Normal priority
            self::AMAZON_API_ERROR       => 0, // Normal priority
            self::RATE_LIMIT_EXCEEDED    => 0, // Normal priority
            self::EXTERNAL_API_ERROR     => 0, // Normal priority
            self::PERFORMANCE_ALERT      => 0, // Normal priority
        };
    }

    /**
     * Get the default sound for this alert type.
     */
    public function getDefaultSound(): ?string
    {
        return match ($this) {
            self::SECURITY_ALERT         => 'siren',
            self::DATABASE_ERROR         => 'alien',
            self::AMAZON_SESSION_EXPIRED => 'pushover',
            self::OPENAI_QUOTA_EXCEEDED  => 'cashregister',
            default                      => null, // Use default sound
        };
    }

    /**
     * Check if this alert type should be throttled.
     */
    public function shouldThrottle(): bool
    {
        return match ($this) {
            self::AMAZON_SESSION_EXPIRED => true,
            self::OPENAI_QUOTA_EXCEEDED  => true,
            self::RATE_LIMIT_EXCEEDED    => true,
            self::PERFORMANCE_ALERT      => true,
            self::API_TIMEOUT            => true,
            self::CONNECTIVITY_ISSUE     => true,
            default                      => false,
        };
    }

    /**
     * Get throttle duration in minutes.
     */
    public function getThrottleDuration(): int
    {
        return match ($this) {
            self::AMAZON_SESSION_EXPIRED => 60, // 1 hour
            self::OPENAI_QUOTA_EXCEEDED  => 60, // 1 hour
            self::RATE_LIMIT_EXCEEDED    => 30, // 30 minutes
            self::PERFORMANCE_ALERT      => 15, // 15 minutes
            self::API_TIMEOUT            => 15, // 15 minutes
            self::CONNECTIVITY_ISSUE     => 30, // 30 minutes - more serious
            default                      => 0,
        };
    }
}
