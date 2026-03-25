<?php

namespace Tests\Unit;

use App\Enums\AlertType;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\SystemAlert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushoverChannelTest extends TestCase
{
    private PushoverChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = new PushoverChannel();

        // Set up test configuration
        config([
            'services.pushover.token' => 'test-token',
            'services.pushover.user'  => 'test-user',
        ]);
    }

    #[Test]
    public function it_formats_message_data_correctly()
    {
        // Test that the notification can create proper message data
        $notification = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Test message',
            ['test' => true]
        );

        $notifiable = new \stdClass();
        $message = $notification->toPushover($notifiable);

        // Test the message structure without sending
        $this->assertIsArray($message);
        $this->assertEquals('test-token', $message['token']);
        $this->assertEquals('test-user', $message['user']);
        $this->assertEquals('Amazon Session Expired', $message['title']);
        $this->assertStringContainsString('Test message', $message['message']);
        $this->assertEquals(1, $message['priority']);
        $this->assertEquals('pushover', $message['sound']);
    }

    #[Test]
    public function it_handles_missing_pushover_config()
    {
        // Override configuration to simulate missing config
        config(['services.pushover.token' => null, 'services.pushover.user' => null]);

        $notification = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Test message',
            ['test' => true]
        );

        $notifiable = new \stdClass();

        // Test that notification returns message with null credentials
        $message = $notification->toPushover($notifiable);

        // When pushover config is missing, token and user should be null
        $this->assertNull($message['token']);
        $this->assertNull($message['user']);

        // And the channel should return null when trying to send
        $result = $this->channel->send($notifiable, $notification);
        $this->assertNull($result);
    }

    #[Test]
    public function it_handles_emergency_priority_parameters()
    {
        $notification = new SystemAlert(
            AlertType::DATABASE_ERROR, // Priority 2 (emergency)
            'Database connection failed'
        );

        $notifiable = new \stdClass();
        $message = $notification->toPushover($notifiable);

        // Test emergency priority includes retry and expire
        $this->assertEquals(2, $message['priority']);
        $this->assertEquals(30, $message['retry']);
        $this->assertEquals(3600, $message['expire']);
    }

    #[Test]
    public function it_includes_sound_for_alert_types()
    {
        $notification = new SystemAlert(
            AlertType::SECURITY_ALERT, // Has 'siren' sound
            'Security breach detected'
        );

        $notifiable = new \stdClass();
        $message = $notification->toPushover($notifiable);

        $this->assertEquals('siren', $message['sound']);
    }

    #[Test]
    public function it_includes_url_when_provided()
    {
        $notification = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Session expired',
            [],
            null,
            'https://example.com/fix',
            'Fix Issue'
        );

        $notifiable = new \stdClass();
        $message = $notification->toPushover($notifiable);

        $this->assertEquals('https://example.com/fix', $message['url']);
        $this->assertEquals('Fix Issue', $message['url_title']);
    }

    #[Test]
    public function it_prevents_sending_in_testing_environment()
    {
        // This test verifies our safety mechanism works
        $notification = new SystemAlert(
            AlertType::AMAZON_SESSION_EXPIRED,
            'Test message'
        );

        $notifiable = new \stdClass();
        $result = $this->channel->send($notifiable, $notification);

        // In testing environment, we should get a fake response
        $this->assertNotNull($result);
        $this->assertTrue($result->successful());
        $this->assertEquals('test-fake', $result->json()['request']);
    }
}
