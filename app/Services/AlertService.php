<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AlertService
{
    /**
     * Send an alert notification.
     */
    public function alert(
        AlertType $type,
        string $message,
        array $context = [],
        ?int $priority = null,
        ?string $url = null,
        ?string $urlTitle = null
    ): void {
        if ($this->shouldSkipDueToThrottle($type, $context, $message)) {
            return;
        }

        // Use default priority if not specified
        $priority = $priority ?? $type->getDefaultPriority();

        // Log the alert
        $this->logAlert($type, $message, $context, $priority);

        // Create and send notification
        $notification = new SystemAlert($type, $message, $context, $priority, $url, $urlTitle);

        // Send to configured notification channels
        $this->sendNotification($notification);
    }

    private function shouldSkipDueToThrottle(AlertType $type, array $context, string $message): bool
    {
        if (!$type->shouldThrottle()) {
            return false;
        }

        if ($this->isThrottled($type, $context)) {
            Log::info('Alert throttled', [
                'type'    => $type->value,
                'message' => $message,
                'context' => $context,
            ]);

            return true;
        }

        $this->setThrottle($type, $context);

        return false;
    }

    /**
     * Send Amazon session expired alert.
     */
    public function amazonSessionExpired(string $errorMessage, array $context = []): void
    {
        $this->alert(
            AlertType::AMAZON_SESSION_EXPIRED,
            "Amazon session has expired and needs re-authentication. Error: {$errorMessage}",
            array_merge($context, ['error_code' => 'AMAZON_SIGNIN_REQUIRED']),
            null,
            config('app.url').'/admin/amazon-config',
            'Update Amazon Configuration'
        );
    }

    /**
     * Send Amazon CAPTCHA detected alert.
     */
    public function amazonCaptchaDetected(string $url, array $indicators, array $context = []): void
    {
        $indicatorsList = implode(', ', $indicators);

        $this->alert(
            AlertType::AMAZON_SESSION_EXPIRED, // Reuse same alert type but with specific messaging
            "Amazon CAPTCHA detected - cookies need renewal. URL: {$url}. Indicators found: {$indicatorsList}",
            array_merge($context, [
                'error_code'         => 'AMAZON_CAPTCHA_DETECTED',
                'captcha_indicators' => $indicators,
                'detection_url'      => $url,
                'alert_subtype'      => 'captcha_detection',
            ]),
            1, // High priority since this directly impacts scraping
            config('app.url').'/admin/amazon-config',
            'Refresh Amazon Cookies'
        );
    }

    /**
     * Send OpenAI quota exceeded alert.
     */
    public function openaiQuotaExceeded(string $errorMessage, array $context = []): void
    {
        $this->alert(
            AlertType::OPENAI_QUOTA_EXCEEDED,
            "OpenAI quota has been exceeded. Error: {$errorMessage}",
            array_merge($context, ['error_code' => 'QUOTA_EXCEEDED']),
            null,
            'https://platform.openai.com/usage',
            'Check OpenAI Usage'
        );
    }

    /**
     * Send OpenAI API error alert.
     */
    public function openaiApiError(string $errorMessage, int $statusCode, array $context = []): void
    {
        $priority = $statusCode >= 500 ? 1 : 0; // High priority for server errors

        $this->alert(
            AlertType::OPENAI_API_ERROR,
            "OpenAI API error (HTTP {$statusCode}): {$errorMessage}",
            array_merge($context, ['status_code' => $statusCode]),
            $priority
        );
    }

    /**
     * Send system error alert.
     */
    public function systemError(string $message, \Throwable $exception, array $context = []): void
    {
        $this->alert(
            AlertType::SYSTEM_ERROR,
            "System error: {$message}",
            array_merge($context, [
                'exception' => get_class($exception),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTraceAsString(),
            ]),
            1 // High priority
        );
    }

    /**
     * Send database error alert.
     */
    public function databaseError(string $message, \Throwable $exception, array $context = []): void
    {
        $this->alert(
            AlertType::DATABASE_ERROR,
            "Database error: {$message}",
            array_merge($context, [
                'exception' => get_class($exception),
                'message'   => $exception->getMessage(),
            ]),
            2 // Emergency priority
        );
    }

    /**
     * Send security alert.
     */
    public function securityAlert(string $message, array $context = []): void
    {
        $this->alert(
            AlertType::SECURITY_ALERT,
            "Security Alert: {$message}",
            $context,
            2 // Emergency priority
        );
    }

    /**
     * Send API timeout alert.
     */
    public function apiTimeout(string $service, string $asin, int $timeoutDuration, array $context = []): void
    {
        $this->alert(
            AlertType::API_TIMEOUT,
            "API timeout for {$service} after {$timeoutDuration}s (ASIN: {$asin})",
            array_merge($context, [
                'service'          => $service,
                'asin'             => $asin,
                'timeout_duration' => $timeoutDuration,
                'error_type'       => 'timeout',
            ])
        );
    }

    /**
     * Send connectivity issue alert.
     */
    public function connectivityIssue(string $service, string $errorType, string $errorMessage, array $context = []): void
    {
        $this->alert(
            AlertType::CONNECTIVITY_ISSUE,
            "Connectivity issue with {$service}: {$errorType} - {$errorMessage}",
            array_merge($context, [
                'service'    => $service,
                'error_type' => $errorType,
            ]),
            1 // High priority since connectivity affects the whole service
        );
    }

    /**
     * Send proxy service issue alert.
     */
    public function proxyServiceIssue(string $message, array $context = []): void
    {
        $this->alert(
            AlertType::CONNECTIVITY_ISSUE,
            "Proxy service issue: {$message}",
            array_merge($context, ['service_type' => 'proxy']),
            1 // High priority - proxy issues affect scraping capability
        );
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
     * Check if an alert type is currently throttled.
     */
    private function isThrottled(AlertType $type, array $context = []): bool
    {
        $key = $this->getThrottleKey($type, $context);

        return Cache::has($key);
    }

    /**
     * Set throttle for an alert type.
     */
    private function setThrottle(AlertType $type, array $context = []): void
    {
        $key = $this->getThrottleKey($type, $context);
        $duration = $type->getThrottleDuration();
        Cache::put($key, true, now()->addMinutes($duration));
    }

    /**
     * Generate throttle cache key.
     */
    private function getThrottleKey(AlertType $type, array $context = []): string
    {
        $contextHash = md5(serialize($context));

        return "alert_throttle:{$type->value}:{$contextHash}";
    }

    /**
     * Log the alert.
     */
    private function logAlert(AlertType $type, string $message, array $context, int $priority): void
    {
        $logLevel = match ($priority) {
            2       => 'critical',
            1       => 'error',
            0       => 'warning',
            -1      => 'info',
            -2      => 'debug',
            default => 'warning',
        };

        Log::log($logLevel, "ALERT: {$type->getDisplayName()}", [
            'type'     => $type->value,
            'message'  => $message,
            'priority' => $priority,
            'context'  => $context,
        ]);
    }

    /**
     * Send notification to configured channels.
     */
    private function sendNotification(SystemAlert $notification): void
    {
        if (!$this->alertingEnabledFor($notification)) {
            return;
        }

        try {
            $sent = $this->sendEmailIfConfigured($notification) || $this->sendPushoverIfConfigured($notification);

            if (!$sent) {
                Log::warning('No alert delivery channels configured');
            }
        } catch (\Exception $e) {
            $alertType = $notification->getAlertType();
            Log::error('Failed to send alert notification', [
                'error'        => $e->getMessage(),
                'notification' => get_class($notification),
                'alert_type'   => $alertType->value,
            ]);
        }
    }

    private function alertingEnabledFor(SystemAlert $notification): bool
    {
        if (!config('monitoring.alerts.enabled', true)) {
            Log::info('Alerts disabled globally');

            return false;
        }

        $alertType = $notification->getAlertType();
        if (!config("monitoring.alerts.enabled_types.{$alertType->value}", true)) {
            Log::info("Alert type {$alertType->value} is disabled");

            return false;
        }

        if (config('monitoring.alerts.development.log_only', false)) {
            Log::info('Alert (log only mode)', [
                'type'    => $alertType->value,
                'context' => $notification->getContext(),
            ]);

            return false;
        }

        return true;
    }

    private function sendEmailIfConfigured(SystemAlert $notification): bool
    {
        $emailConfig = config('monitoring.alerts.recipients.email', []);
        $emailTo = $emailConfig['to'] ?? null;

        if (empty($emailConfig['enabled']) || empty($emailTo)) {
            return false;
        }

        Notification::route('mail', $emailTo)->notify($notification);

        return true;
    }

    private function sendPushoverIfConfigured(SystemAlert $notification): bool
    {
        $pushoverConfig = config('monitoring.alerts.recipients.pushover', []);
        if (empty($pushoverConfig['token']) || empty($pushoverConfig['user'])) {
            Log::warning('Pushover not configured - missing token or user key');

            return false;
        }

        Notification::route('pushover', $pushoverConfig)->notify($notification);

        return true;
    }
}

