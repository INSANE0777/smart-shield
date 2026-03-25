<?php

namespace App\Services;

use App\Enums\AlertType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Context-Aware Alert Manager.
 *
 * Implements enterprise-grade alerting strategies:
 * - Error rate thresholds instead of individual failures
 * - Business impact assessment
 * - Service criticality awareness
 * - Pattern-based alerting
 * - Recovery-aware suppression
 *
 * Based on strategies used by Netflix, Stripe, and GitHub.
 */
class AlertManager
{
    private AlertService $alertService;

    // Error rate thresholds (failures per time window)
    private const ERROR_RATE_THRESHOLDS = [
        'CRITICAL_P0' => ['failures' => 10, 'window_minutes' => 5],   // >10 failures in 5 min = critical
        'HIGH_P1'     => ['failures' => 5,  'window_minutes' => 10],  // >5 failures in 10 min = high
        'MEDIUM_P2'   => ['failures' => 3,  'window_minutes' => 15],  // >3 failures in 15 min = medium
        'LOW_P3'      => ['failures' => 1,  'window_minutes' => 60],  // Individual failures = low
    ];

    // Service criticality mapping
    private const SERVICE_CRITICALITY = [
        'BrightData Web Scraper' => 'PRIMARY',
        'BrightData API'         => 'PRIMARY',
        'OpenAI Service'         => 'CORE',
        'Groq Service'           => 'CORE',
        'DeepSeek Service'       => 'CORE',
        'Amazon Direct Scraping' => 'FALLBACK',
        'Amazon AJAX Service'    => 'FALLBACK',
        'Unwrangle API'          => 'FALLBACK',
        'Analysis Throughput'    => 'PRIMARY',
    ];

    // Business impact classifications
    private const BUSINESS_IMPACT = [
        'PRIMARY' => [
            'revenue_affecting' => true,
            'user_facing'       => true,
            'description'       => 'Affects core product functionality and user experience',
        ],
        'CORE' => [
            'revenue_affecting' => true,
            'user_facing'       => true,
            'description'       => 'Essential for analysis quality but has fallbacks',
        ],
        'FALLBACK' => [
            'revenue_affecting' => false,
            'user_facing'       => false,
            'description'       => 'Backup service, minimal impact when primary services work',
        ],
    ];

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Record a service failure and determine if alerting is needed.
     *
     * @param string          $service      Service name (e.g., 'BrightData Web Scraper')
     * @param string          $errorType    Error classification (e.g., 'SCRAPING_FAILED')
     * @param string          $errorMessage Human-readable error message
     * @param array           $context      Additional context including ASIN, job_id, etc.
     * @param \Throwable|null $exception    Original exception if available
     */
    public function recordFailure(
        string $service,
        string $errorType,
        string $errorMessage,
        array $context = [],
        ?\Throwable $exception = null
    ): void {
        // Always log the failure for debugging
        Log::warning("Service failure recorded: {$service}", [
            'service'       => $service,
            'error_type'    => $errorType,
            'error_message' => $errorMessage,
            'context'       => $context,
            'exception'     => $exception ? [
                'class'   => get_class($exception),
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
            ] : null,
        ]);

        // Record failure in cache for rate tracking
        $this->recordFailureForRateTracking($service, $errorType);

        // Get service criticality and business impact
        $criticality = $this->getServiceCriticality($service);
        $businessImpact = $this->getBusinessImpact($criticality);

        // Determine alert severity based on error rates and business impact
        $alertLevel = $this->determineAlertLevel($service, $errorType, $criticality);

        // Check if we should suppress this alert (recovery patterns, throttling)
        if ($this->shouldSuppressAlert($service, $errorType, $alertLevel)) {
            Log::info('Alert suppressed due to recovery pattern or throttling', [
                'service'     => $service,
                'error_type'  => $errorType,
                'alert_level' => $alertLevel,
            ]);

            return;
        }

        // Send alert if threshold is met
        if ($alertLevel !== 'SUPPRESS') {
            $this->sendContextualAlert($service, $errorType, $errorMessage, $alertLevel, $businessImpact, $context, $exception);
        }
    }

    /**
     * Record a service recovery to potentially suppress future alerts.
     */
    public function recordRecovery(string $service, string $errorType): void
    {
        $recoveryKey = "recovery:{$service}:{$errorType}";
        Cache::put($recoveryKey, now(), now()->addMinutes(30));

        Log::info('Service recovery recorded', [
            'service'    => $service,
            'error_type' => $errorType,
        ]);
    }

    /**
     * Get current error rate for a service.
     */
    public function getErrorRate(string $service, string $errorType, int $windowMinutes = 10): array
    {
        $failures = $this->getFailuresInWindow($service, $errorType, $windowMinutes);

        return [
            'failures'        => count($failures),
            'window_minutes'  => $windowMinutes,
            'rate_per_minute' => count($failures) / $windowMinutes,
            'timestamps'      => $failures,
        ];
    }

    /**
     * Record failure timestamp for rate tracking.
     */
    private function recordFailureForRateTracking(string $service, string $errorType): void
    {
        $key = "failures:{$service}:{$errorType}";
        $failures = Cache::get($key, []);

        // Add current timestamp
        $failures[] = now()->timestamp;

        // Keep only last 24 hours of data
        $cutoff = now()->subHours(24)->timestamp;
        $failures = array_filter($failures, fn ($timestamp) => $timestamp > $cutoff);

        // Store back in cache
        Cache::put($key, array_values($failures), now()->addHours(24));
    }

    /**
     * Get failures within a time window.
     */
    private function getFailuresInWindow(string $service, string $errorType, int $windowMinutes): array
    {
        $key = "failures:{$service}:{$errorType}";
        $failures = Cache::get($key, []);

        $cutoff = now()->subMinutes($windowMinutes)->timestamp;

        return array_filter($failures, fn ($timestamp) => $timestamp > $cutoff);
    }

    /**
     * Determine alert level based on error rates and service criticality.
     */
    private function determineAlertLevel(string $service, string $errorType, string $criticality): string
    {
        // Check error rates against thresholds
        foreach (self::ERROR_RATE_THRESHOLDS as $level => $threshold) {
            $failures = $this->getFailuresInWindow($service, $errorType, $threshold['window_minutes']);

            if (count($failures) >= $threshold['failures']) {
                // Adjust severity based on service criticality
                return $this->adjustSeverityForCriticality($level, $criticality);
            }
        }

        return 'SUPPRESS'; // Below all thresholds
    }

    /**
     * Adjust severity based on service criticality.
     */
    private function adjustSeverityForCriticality(string $baseSeverity, string $criticality): string
    {
        if ($criticality === 'PRIMARY') {
            return $baseSeverity; // Keep original severity for primary services
        }

        if ($criticality === 'CORE') {
            // Slightly reduce severity for core services
            return match ($baseSeverity) {
                'CRITICAL_P0' => 'HIGH_P1',
                'HIGH_P1'     => 'MEDIUM_P2',
                'MEDIUM_P2'   => 'LOW_P3',
                'LOW_P3'      => 'SUPPRESS',
                default       => $baseSeverity,
            };
        }

        if ($criticality === 'FALLBACK') {
            // Significantly reduce severity for fallback services
            return match ($baseSeverity) {
                'CRITICAL_P0' => 'MEDIUM_P2',
                'HIGH_P1'     => 'LOW_P3',
                'MEDIUM_P2'   => 'SUPPRESS',
                'LOW_P3'      => 'SUPPRESS',
                default       => 'SUPPRESS',
            };
        }

        return $baseSeverity;
    }

    /**
     * Check if alert should be suppressed due to recovery patterns or throttling.
     */
    private function shouldSuppressAlert(string $service, string $errorType, string $alertLevel): bool
    {
        // Check for recent recovery
        $recoveryKey = "recovery:{$service}:{$errorType}";
        if (Cache::has($recoveryKey)) {
            return true;
        }

        // Check alert throttling
        $throttleKey = "alert_throttle:{$service}:{$errorType}:{$alertLevel}";
        if (Cache::has($throttleKey)) {
            return true;
        }

        return false;
    }

    /**
     * Send contextual alert with business impact information.
     */
    private function sendContextualAlert(
        string $service,
        string $errorType,
        string $errorMessage,
        string $alertLevel,
        array $businessImpact,
        array $context,
        ?\Throwable $exception
    ): void {
        // Set throttle for this alert type
        $throttleKey = "alert_throttle:{$service}:{$errorType}:{$alertLevel}";
        $throttleDuration = match ($alertLevel) {
            'CRITICAL_P0' => 5,   // 5 minutes for critical
            'HIGH_P1'     => 15,      // 15 minutes for high
            'MEDIUM_P2'   => 60,    // 1 hour for medium
            'LOW_P3'      => 240,      // 4 hours for low
            default       => 60,
        };
        Cache::put($throttleKey, true, now()->addMinutes($throttleDuration));

        // Get error rate information
        $errorRate = $this->getErrorRate($service, $errorType, 15);

        // Build enhanced context
        $enhancedContext = array_merge($context, [
            'alert_level'         => $alertLevel,
            'service_criticality' => $this->getServiceCriticality($service),
            'business_impact'     => $businessImpact,
            'error_rate'          => $errorRate,
            'recommended_action'  => $this->getRecommendedAction($service, $errorType, $alertLevel),
            'escalation_required' => in_array($alertLevel, ['CRITICAL_P0', 'HIGH_P1']),
        ]);

        // Add exception details if available
        if ($exception) {
            $enhancedContext['exception_details'] = [
                'class' => get_class($exception),
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine(),
            ];
        }

        // Build contextual message
        $contextualMessage = $this->buildContextualMessage($service, $errorType, $errorMessage, $alertLevel, $businessImpact, $errorRate);

        // Map to AlertService method based on alert type
        $this->sendToAlertService($service, $errorType, $contextualMessage, $enhancedContext, $alertLevel);
    }

    /**
     * Build contextual alert message with business impact.
     */
    private function buildContextualMessage(
        string $service,
        string $errorType,
        string $errorMessage,
        string $alertLevel,
        array $businessImpact,
        array $errorRate
    ): string {
        $impact = $businessImpact['revenue_affecting'] ? 'REVENUE IMPACT' : 'OPERATIONAL IMPACT';
        $facing = $businessImpact['user_facing'] ? 'USER-FACING' : 'INTERNAL';

        return sprintf(
            '[%s] %s %s Service Issue: %s failed with %d errors in %d minutes. %s',
            $alertLevel,
            $impact,
            $facing,
            $service,
            $errorRate['failures'],
            $errorRate['window_minutes'],
            $errorMessage
        );
    }

    /**
     * Get recommended action based on service and error type.
     */
    private function getRecommendedAction(string $service, string $errorType, string $alertLevel): string
    {
        if ($service === 'Analysis Throughput') {
            return 'Check queue worker health, queue backlog, and upstream BrightData/LLM service status. Investigate /livewire/update and /api/analysis/progress request patterns during spikes.';
        }

        if (str_contains($service, 'BrightData')) {
            return match ($errorType) {
                'JOB_TRIGGER_FAILED'      => 'Check BrightData API status and authentication credentials',
                'POLLING_PATTERN_FAILURE' => 'Monitor job completion manually, check for service degradation',
                'SCRAPING_FAILED'         => 'Verify Amazon target accessibility and BrightData dataset configuration',
                default                   => 'Check BrightData service status and API connectivity',
            };
        }

        if (str_contains($service, 'OpenAI') || str_contains($service, 'Groq') || str_contains($service, 'DeepSeek')) {
            return match ($errorType) {
                'QUOTA_EXCEEDED' => 'URGENT: Check provider billing and increase quota limits',
                'API_ERROR'      => 'Check provider status page and API key validity',
                default          => 'Monitor service status and retry failed requests',
            };
        }

        if (str_contains($service, 'Amazon')) {
            return 'Expected for fallback services - verify primary BrightData is functioning';
        }

        return 'Investigate service logs and check external dependencies';
    }

    /**
     * Send to appropriate AlertService method.
     */
    private function sendToAlertService(string $service, string $errorType, string $message, array $context, string $alertLevel): void
    {
        // Map severity to AlertService priority
        $priority = match ($alertLevel) {
            'CRITICAL_P0' => 2,  // Emergency
            'HIGH_P1'     => 1,      // High
            'MEDIUM_P2'   => 0,    // Warning
            'LOW_P3'      => -1,      // Info
            default       => 0,
        };

        if ($errorType === 'PERFORMANCE_ALERT') {
            $this->sendPerformanceAlert($message, $context, $priority);

            return;
        }

        if (str_contains($errorType, 'TIMEOUT')) {
            $this->sendTimeoutAlert($service, $context);

            return;
        }

        if (str_contains($errorType, 'SESSION_EXPIRED')) {
            $this->alertService->amazonSessionExpired($message, $context);

            return;
        }

        if (str_contains($errorType, 'QUOTA_EXCEEDED')) {
            $this->alertService->openaiQuotaExceeded($message, $context);

            return;
        }

        // Default to connectivity issue
        $this->alertService->connectivityIssue(
            $service,
            $errorType,
            $message,
            $context
        );
    }

    private function sendPerformanceAlert(string $message, array $context, int $priority): void
    {
        $this->alertService->alert(AlertType::PERFORMANCE_ALERT, $message, $context, $priority);
    }

    private function sendTimeoutAlert(string $service, array $context): void
    {
        $this->alertService->apiTimeout(
            $service,
            $context['asin'] ?? 'N/A',
            $context['timeout_duration'] ?? 0,
            $context
        );
    }

    /**
     * Get service criticality level.
     */
    private function getServiceCriticality(string $service): string
    {
        return self::SERVICE_CRITICALITY[$service] ?? 'FALLBACK';
    }

    /**
     * Get business impact for service criticality.
     */
    private function getBusinessImpact(string $criticality): array
    {
        return self::BUSINESS_IMPACT[$criticality] ?? self::BUSINESS_IMPACT['FALLBACK'];
    }
}

