<?php

namespace Tests\Unit;

use App\Enums\AlertType;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock Pushover configuration
        Config::set('services.pushover', [
            'token' => 'test-token',
            'user'  => 'test-user',
        ]);
    }

    #[Test]
    public function it_uses_pushover_channel()
    {
        $alert = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Test message'
        );

        $channels = $alert->via(new \stdClass());

        $this->assertEquals([PushoverChannel::class], $channels);
    }

    #[Test]
    public function it_creates_pushover_message_with_basic_data()
    {
        $alert = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Test message'
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $this->assertIsArray($pushoverData);
        $this->assertEquals('test-token', $pushoverData['token']);
        $this->assertEquals('test-user', $pushoverData['user']);
        $this->assertEquals('Test message', $pushoverData['message']);
        $this->assertEquals('Amazon Session Expired', $pushoverData['title']);
        $this->assertEquals(1, $pushoverData['priority']); // High priority for Amazon session expired
    }

    #[Test]
    public function it_sets_custom_priority()
    {
        $alert = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Test message',
            [],
            0 // Normal priority
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $this->assertEquals(0, $pushoverData['priority']);
    }

    #[Test]
    public function it_sets_url_and_url_title()
    {
        $alert = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Test message',
            [],
            null,
            'https://example.com',
            'Example Link'
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $this->assertEquals('https://example.com', $pushoverData['url']);
        $this->assertEquals('Example Link', $pushoverData['url_title']);
    }

    #[Test]
    public function it_sets_sound_for_alert_types()
    {
        $alert = new SystemAlert(
            AlertType::SECURITY_ALERT,
            'Security issue detected'
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $this->assertEquals('siren', $pushoverData['sound']);
    }

    #[Test]
    public function it_includes_context_in_message()
    {
        $context = [
            'asin'        => 'B0TEST1234',
            'status_code' => 429,
            'error_code'  => 'QUOTA_EXCEEDED',
        ];

        $alert = new SystemAlert(
            AlertType::OPENAI_QUOTA_EXCEEDED,
            'Quota exceeded',
            $context
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $message = $pushoverData['message'];
        $this->assertStringContainsString('Quota exceeded', $message);
        $this->assertStringContainsString('Asin: B0TEST1234', $message);
        $this->assertStringContainsString('Status code: 429', $message);
        $this->assertStringContainsString('Error code: QUOTA_EXCEEDED', $message);
    }

    #[Test]
    public function it_filters_sensitive_context_data()
    {
        $context = [
            'asin'     => 'B0TEST1234',
            'password' => 'secret123',
            'token'    => 'sensitive-token',
            'trace'    => 'very-long-stack-trace',
            'secret'   => 'another-secret',
        ];

        $alert = new SystemAlert(
            AlertType::SYSTEM_ERROR,
            'System error',
            $context
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $message = $pushoverData['message'];
        $this->assertStringContainsString('Asin: B0TEST1234', $message);
        $this->assertStringNotContainsString('password', $message);
        $this->assertStringNotContainsString('secret123', $message);
        $this->assertStringNotContainsString('sensitive-token', $message);
        $this->assertStringNotContainsString('very-long-stack-trace', $message);
    }

    #[Test]
    public function it_limits_context_items()
    {
        $context = [
            'item1' => 'value1',
            'item2' => 'value2',
            'item3' => 'value3',
            'item4' => 'value4',
            'item5' => 'value5',
            'item6' => 'value6', // Should be filtered out
            'item7' => 'value7', // Should be filtered out
        ];

        $alert = new SystemAlert(
            AlertType::SYSTEM_ERROR,
            'System error',
            $context
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $message = $pushoverData['message'];
        $this->assertStringContainsString('Item1: value1', $message);
        $this->assertStringContainsString('Item5: value5', $message);
        $this->assertStringNotContainsString('Item6: value6', $message);
        $this->assertStringNotContainsString('Item7: value7', $message);
    }

    #[Test]
    public function it_handles_boolean_context_values()
    {
        $context = [
            'verified' => true,
            'enabled'  => false,
        ];

        $alert = new SystemAlert(
            AlertType::SYSTEM_ERROR,
            'System error',
            $context
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $message = $pushoverData['message'];
        $this->assertStringContainsString('Verified: Yes', $message);
        $this->assertStringContainsString('Enabled: No', $message);
    }

    #[Test]
    public function it_handles_array_context_values()
    {
        $context = [
            'tags'        => ['urgent', 'api', 'error'],
            'large_array' => ['item1', 'item2', 'item3', 'item4'], // Should be filtered out
        ];

        $alert = new SystemAlert(
            AlertType::SYSTEM_ERROR,
            'System error',
            $context
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $message = $pushoverData['message'];
        $this->assertStringContainsString('Tags: urgent, api, error', $message);
        $this->assertStringNotContainsString('Large array:', $message);
    }

    #[Test]
    public function it_returns_alert_type()
    {
        $alert = new SystemAlert(
            AlertType::DATABASE_ERROR,
            'Database error'
        );

        $this->assertEquals(AlertType::DATABASE_ERROR, $alert->getAlertType());
    }

    #[Test]
    public function it_returns_context()
    {
        $context = ['test' => 'value'];

        $alert = new SystemAlert(
            AlertType::SYSTEM_ERROR,
            'System error',
            $context
        );

        $this->assertEquals($context, $alert->getContext());
    }

    #[Test]
    public function it_uses_default_priority_from_alert_type()
    {
        $alert = new SystemAlert(
            AlertType::SECURITY_ALERT, // Emergency priority (2)
            'Security issue'
        );

        $pushoverData = $alert->toPushover(new \stdClass());

        $this->assertEquals(2, $pushoverData['priority']);
    }
}
