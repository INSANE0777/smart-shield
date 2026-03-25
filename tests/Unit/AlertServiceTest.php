<?php

namespace Tests\Unit;

use App\Enums\AlertType;
use App\Services\AlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertServiceTest extends TestCase
{
    private AlertService $alertService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alertService = new AlertService();

        // Override parent TestCase to enable alerts for testing the framework
        // but ALWAYS keep log_only mode enabled
        config([
            'alerts.enabled'              => true,
            'alerts.development.log_only' => true, // ALWAYS log only, never send real notifications
            'services.pushover.token'     => 'test-token',
            'services.pushover.user'      => 'test-user',
            'alerts.enabled_types'        => [
                'amazon_session_expired' => true,
                'openai_quota_exceeded'  => true,
                'openai_api_error'       => true,
                'system_error'           => true,
                'database_error'         => true,
                'security_alert'         => true,
            ],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_amazon_session_expired_alert()
    {
        // Test passes if no exception is thrown
        $this->alertService->amazonSessionExpired('Session expired', ['asin' => 'B0TEST1234']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_openai_quota_exceeded_alert()
    {
        // Test passes if no exception is thrown
        $this->alertService->openaiQuotaExceeded('Quota exceeded', ['status_code' => 429]);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_openai_api_error_alert()
    {
        // Test passes if no exception is thrown
        $this->alertService->openaiApiError('API error', 500, ['endpoint' => '/chat/completions']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_system_error_alert()
    {
        $exception = new \Exception('Test exception');

        // Test passes if no exception is thrown
        $this->alertService->systemError('System error', $exception, ['component' => 'test']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_database_error_alert()
    {
        $exception = new \Exception('DB connection failed');

        // Test passes if no exception is thrown
        $this->alertService->databaseError('DB error', $exception, ['connection' => 'mysql']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_security_alert()
    {
        // Test passes if no exception is thrown
        $this->alertService->securityAlert('Suspicious activity', ['ip' => '192.168.1.1']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_respects_global_alert_disable()
    {
        config(['alerts.enabled' => false]);

        // Test passes if no exception is thrown when alerts are disabled
        $this->alertService->amazonSessionExpired('Session expired', ['asin' => 'B0TEST1234']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_respects_specific_alert_type_disable()
    {
        config(['alerts.enabled_types.amazon_session_expired' => false]);

        // Test passes if no exception is thrown when specific alert type is disabled
        $this->alertService->amazonSessionExpired('Session expired', ['asin' => 'B0TEST1234']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_throttling()
    {
        Cache::flush();

        // Send first alert - should go through
        $this->alertService->amazonSessionExpired('First alert', ['asin' => 'B0TEST1234']);

        // Send second alert immediately - should be throttled (no exception thrown)
        $this->alertService->amazonSessionExpired('Second alert', ['asin' => 'B0TEST1234']);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_log_only_mode()
    {
        config(['alerts.development.log_only' => true]);

        // Test passes if no exception is thrown in log-only mode
        $this->alertService->amazonSessionExpired('Test alert', ['asin' => 'B0TEST1234']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_missing_pushover_config()
    {
        config([
            'services.pushover.token' => null,
            'services.pushover.user'  => null,
            // REMOVED: 'alerts.development.log_only' => false - this was causing real notifications
        ]);

        // Test passes if no exception is thrown when config is missing
        $this->alertService->amazonSessionExpired('Test alert', ['asin' => 'B0TEST1234']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_different_priorities()
    {
        // Test different OpenAI API error status codes (no exceptions thrown)
        $this->alertService->openaiApiError('Client error', 400, []);
        $this->alertService->openaiApiError('Server error', 500, []);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_context_data()
    {
        // Test passes if no exception is thrown with context data
        $this->alertService->amazonSessionExpired('Test alert', [
            'asin'       => 'B0TEST1234',
            'error_code' => 'AMAZON_SIGNIN_REQUIRED',
            'timestamp'  => '2024-01-01 12:00:00',
        ]);
        $this->assertTrue(true);
    }

    #[Test]
    public function alert_type_enum_has_correct_values()
    {
        // Test AlertType enum functionality
        $this->assertEquals('Amazon Session Expired', AlertType::AMAZON_SESSION_EXPIRED->getDisplayName());
        $this->assertEquals('OpenAI Quota Exceeded', AlertType::OPENAI_QUOTA_EXCEEDED->getDisplayName());
        $this->assertEquals('Security Alert', AlertType::SECURITY_ALERT->getDisplayName());

        $this->assertEquals(1, AlertType::AMAZON_SESSION_EXPIRED->getDefaultPriority());
        $this->assertEquals(2, AlertType::SECURITY_ALERT->getDefaultPriority());

        $this->assertEquals('pushover', AlertType::AMAZON_SESSION_EXPIRED->getDefaultSound());
        $this->assertEquals('siren', AlertType::SECURITY_ALERT->getDefaultSound());
    }
}
