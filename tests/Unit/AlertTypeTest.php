<?php

namespace Tests\Unit;

use App\Enums\AlertType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertTypeTest extends TestCase
{
    #[Test]
    public function it_has_correct_display_names()
    {
        $this->assertEquals('Amazon Session Expired', AlertType::AMAZON_SESSION_EXPIRED->getDisplayName());
        $this->assertEquals('OpenAI Quota Exceeded', AlertType::OPENAI_QUOTA_EXCEEDED->getDisplayName());
        $this->assertEquals('OpenAI API Error', AlertType::OPENAI_API_ERROR->getDisplayName());
        $this->assertEquals('System Error', AlertType::SYSTEM_ERROR->getDisplayName());
        $this->assertEquals('Database Error', AlertType::DATABASE_ERROR->getDisplayName());
        $this->assertEquals('Security Alert', AlertType::SECURITY_ALERT->getDisplayName());
    }

    #[Test]
    public function it_has_correct_default_priorities()
    {
        // High priority alerts
        $this->assertEquals(1, AlertType::AMAZON_SESSION_EXPIRED->getDefaultPriority());
        $this->assertEquals(1, AlertType::OPENAI_QUOTA_EXCEEDED->getDefaultPriority());
        $this->assertEquals(1, AlertType::SYSTEM_ERROR->getDefaultPriority());

        // Emergency priority alerts
        $this->assertEquals(2, AlertType::SECURITY_ALERT->getDefaultPriority());
        $this->assertEquals(2, AlertType::DATABASE_ERROR->getDefaultPriority());

        // Normal priority alerts
        $this->assertEquals(0, AlertType::OPENAI_API_ERROR->getDefaultPriority());
        $this->assertEquals(0, AlertType::AMAZON_API_ERROR->getDefaultPriority());
        $this->assertEquals(0, AlertType::RATE_LIMIT_EXCEEDED->getDefaultPriority());
        $this->assertEquals(0, AlertType::EXTERNAL_API_ERROR->getDefaultPriority());
        $this->assertEquals(0, AlertType::PERFORMANCE_ALERT->getDefaultPriority());
    }

    #[Test]
    public function it_has_correct_default_sounds()
    {
        $this->assertEquals('siren', AlertType::SECURITY_ALERT->getDefaultSound());
        $this->assertEquals('alien', AlertType::DATABASE_ERROR->getDefaultSound());
        $this->assertEquals('pushover', AlertType::AMAZON_SESSION_EXPIRED->getDefaultSound());
        $this->assertEquals('cashregister', AlertType::OPENAI_QUOTA_EXCEEDED->getDefaultSound());

        // Others should return null (use default sound)
        $this->assertNull(AlertType::OPENAI_API_ERROR->getDefaultSound());
        $this->assertNull(AlertType::SYSTEM_ERROR->getDefaultSound());
    }

    #[Test]
    public function it_identifies_throttled_alert_types()
    {
        // These should be throttled
        $this->assertTrue(AlertType::AMAZON_SESSION_EXPIRED->shouldThrottle());
        $this->assertTrue(AlertType::OPENAI_QUOTA_EXCEEDED->shouldThrottle());
        $this->assertTrue(AlertType::RATE_LIMIT_EXCEEDED->shouldThrottle());
        $this->assertTrue(AlertType::PERFORMANCE_ALERT->shouldThrottle());

        // These should not be throttled
        $this->assertFalse(AlertType::SECURITY_ALERT->shouldThrottle());
        $this->assertFalse(AlertType::DATABASE_ERROR->shouldThrottle());
        $this->assertFalse(AlertType::SYSTEM_ERROR->shouldThrottle());
        $this->assertFalse(AlertType::OPENAI_API_ERROR->shouldThrottle());
    }

    #[Test]
    public function it_has_correct_throttle_durations()
    {
        $this->assertEquals(60, AlertType::AMAZON_SESSION_EXPIRED->getThrottleDuration());
        $this->assertEquals(60, AlertType::OPENAI_QUOTA_EXCEEDED->getThrottleDuration());
        $this->assertEquals(30, AlertType::RATE_LIMIT_EXCEEDED->getThrottleDuration());
        $this->assertEquals(15, AlertType::PERFORMANCE_ALERT->getThrottleDuration());

        // Non-throttled types should return 0
        $this->assertEquals(0, AlertType::SECURITY_ALERT->getThrottleDuration());
        $this->assertEquals(0, AlertType::DATABASE_ERROR->getThrottleDuration());
    }

    #[Test]
    public function it_has_correct_enum_values()
    {
        $this->assertEquals('amazon_session_expired', AlertType::AMAZON_SESSION_EXPIRED->value);
        $this->assertEquals('openai_quota_exceeded', AlertType::OPENAI_QUOTA_EXCEEDED->value);
        $this->assertEquals('openai_api_error', AlertType::OPENAI_API_ERROR->value);
        $this->assertEquals('amazon_api_error', AlertType::AMAZON_API_ERROR->value);
        $this->assertEquals('system_error', AlertType::SYSTEM_ERROR->value);
        $this->assertEquals('rate_limit_exceeded', AlertType::RATE_LIMIT_EXCEEDED->value);
        $this->assertEquals('database_error', AlertType::DATABASE_ERROR->value);
        $this->assertEquals('external_api_error', AlertType::EXTERNAL_API_ERROR->value);
        $this->assertEquals('security_alert', AlertType::SECURITY_ALERT->value);
        $this->assertEquals('performance_alert', AlertType::PERFORMANCE_ALERT->value);
    }

    #[Test]
    public function all_alert_types_can_be_instantiated()
    {
        $alertTypes = [
            AlertType::AMAZON_SESSION_EXPIRED,
            AlertType::OPENAI_QUOTA_EXCEEDED,
            AlertType::OPENAI_API_ERROR,
            AlertType::AMAZON_API_ERROR,
            AlertType::SYSTEM_ERROR,
            AlertType::RATE_LIMIT_EXCEEDED,
            AlertType::DATABASE_ERROR,
            AlertType::EXTERNAL_API_ERROR,
            AlertType::SECURITY_ALERT,
            AlertType::PERFORMANCE_ALERT,
        ];

        foreach ($alertTypes as $alertType) {
            $this->assertInstanceOf(AlertType::class, $alertType);
            $this->assertIsString($alertType->getDisplayName());
            $this->assertIsInt($alertType->getDefaultPriority());
            $this->assertIsBool($alertType->shouldThrottle());
            $this->assertIsInt($alertType->getThrottleDuration());
        }
    }
}
