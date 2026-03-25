<?php

namespace Tests\Unit;

use App\Services\Amazon\CookieSessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CookieSessionManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached rotation index
        Cache::forget('amazon_cookie_rotation_index');

        // Clear all session health cache
        for ($i = 1; $i <= 10; $i++) {
            Cache::forget('amazon_session_health_'.$i);
        }
    }

    #[Test]
    public function it_loads_available_cookie_sessions_from_environment()
    {
        // Create a mock manager that overrides environment variable access
        $manager = new class() extends CookieSessionManager {
            protected function getEnvironmentVariable(string $key, $default = ''): string
            {
                switch ($key) {
                    case 'AMAZON_COOKIES_1':
                        return 'session_id=test1; user_id=user1';
                    case 'AMAZON_COOKIES_3':
                        return 'session_id=test3; user_id=user3';
                    default:
                        return $default;
                }
            }
        };

        $this->assertEquals(2, $manager->getSessionCount());
        $this->assertTrue($manager->hasAnySessions());

        $sessionInfo = $manager->getSessionInfo();
        $this->assertCount(2, $sessionInfo);

        // Check first session
        $session1 = collect($sessionInfo)->firstWhere('index', 1);
        $this->assertEquals('Session 1', $session1['name']);
        $this->assertEquals('AMAZON_COOKIES_1', $session1['env_var']);
        $this->assertTrue($session1['has_cookies']);
        $this->assertTrue($session1['is_healthy']);
    }

    #[Test]
    public function it_returns_null_when_no_sessions_available()
    {
        // Create a manager that explicitly returns empty environment variables
        $manager = new class() extends CookieSessionManager {
            protected function getEnvironmentVariable(string $key, $default = ''): string
            {
                // Always return empty for this test
                return $default;
            }
        };

        $this->assertEquals(0, $manager->getSessionCount());
        $this->assertFalse($manager->hasAnySessions());
        $this->assertNull($manager->getNextCookieSession());
        $this->assertNull($manager->getCurrentCookieSession());
    }

    #[Test]
    public function it_rotates_through_sessions_in_round_robin()
    {
        $manager = new class() extends CookieSessionManager {
            protected function getEnvironmentVariable(string $key, $default = ''): string
            {
                switch ($key) {
                    case 'AMAZON_COOKIES_1':
                        return 'session_id=test1';
                    case 'AMAZON_COOKIES_2':
                        return 'session_id=test2';
                    case 'AMAZON_COOKIES_3':
                        return 'session_id=test3';
                    default:
                        return $default;
                }
            }
        };

        // Get sessions and verify rotation
        $session1 = $manager->getNextCookieSession();
        $session2 = $manager->getNextCookieSession();
        $session3 = $manager->getNextCookieSession();
        $session4 = $manager->getNextCookieSession(); // Should wrap back to first

        $this->assertEquals(1, $session1['index']);
        $this->assertEquals(2, $session2['index']);
        $this->assertEquals(3, $session3['index']);
        $this->assertEquals(1, $session4['index']); // Round-robin back to first
    }

    #[Test]
    public function it_creates_cookie_jar_with_proper_cookies()
    {
        $session = [
            'name'    => 'Test Session',
            'env_var' => 'AMAZON_COOKIES_1',
            'cookies' => 'session_id=abc123; user_pref=dark_mode; csrf_token=xyz789',
            'index'   => 1,
        ];

        $manager = new CookieSessionManager();
        $cookieJar = $manager->createCookieJar($session);

        $this->assertInstanceOf(\GuzzleHttp\Cookie\CookieJar::class, $cookieJar);

        // Check that cookies were added (this is a bit tricky to test directly)
        // We'll verify by checking the cookie jar isn't empty
        $cookies = [];
        foreach ($cookieJar as $cookie) {
            $cookies[] = $cookie->getName();
        }

        $this->assertContains('session_id', $cookies);
        $this->assertContains('user_pref', $cookies);
        $this->assertContains('csrf_token', $cookies);
    }

    #[Test]
    public function it_marks_sessions_as_unhealthy_and_respects_cooldown()
    {
        $manager = new class() extends CookieSessionManager {
            protected function getEnvironmentVariable(string $key, $default = ''): string
            {
                switch ($key) {
                    case 'AMAZON_COOKIES_1':
                        return 'session_id=test1';
                    case 'AMAZON_COOKIES_2':
                        return 'session_id=test2';
                    default:
                        return $default;
                }
            }
        };

        // Mark session 1 as unhealthy
        $manager->markSessionUnhealthy(1, 'Test reason', 1); // 1 minute cooldown

        // Next session should skip session 1 and return session 2
        $session = $manager->getNextCookieSession();
        $this->assertEquals(2, $session['index']);

        // Session info should reflect unhealthy status
        $sessionInfo = $manager->getSessionInfo();
        $session1Info = collect($sessionInfo)->firstWhere('index', 1);
        $this->assertFalse($session1Info['is_healthy']);
        $this->assertEquals('Test reason', $session1Info['health_info']['reason']);
    }

    #[Test]
    public function it_resets_all_session_health()
    {
        $manager = new class() extends CookieSessionManager {
            protected function getEnvironmentVariable(string $key, $default = ''): string
            {
                switch ($key) {
                    case 'AMAZON_COOKIES_1':
                        return 'session_id=test1';
                    case 'AMAZON_COOKIES_2':
                        return 'session_id=test2';
                    default:
                        return $default;
                }
            }
        };

        // Mark both sessions as unhealthy
        $manager->markSessionUnhealthy(1, 'Test reason 1', 60);
        $manager->markSessionUnhealthy(2, 'Test reason 2', 60);

        // Verify they're unhealthy
        $sessionInfo = $manager->getSessionInfo();
        $this->assertFalse(collect($sessionInfo)->firstWhere('index', 1)['is_healthy']);
        $this->assertFalse(collect($sessionInfo)->firstWhere('index', 2)['is_healthy']);

        // Reset all health
        $manager->resetAllSessionHealth();

        // Verify they're healthy again
        $sessionInfo = $manager->getSessionInfo();
        $this->assertTrue(collect($sessionInfo)->firstWhere('index', 1)['is_healthy']);
        $this->assertTrue(collect($sessionInfo)->firstWhere('index', 2)['is_healthy']);
    }

    #[Test]
    public function it_handles_empty_cookie_strings_gracefully()
    {
        $session = [
            'name'    => 'Empty Session',
            'env_var' => 'AMAZON_COOKIES_1',
            'cookies' => '',
            'index'   => 1,
        ];

        $manager = new CookieSessionManager();
        $cookieJar = $manager->createCookieJar($session);

        $this->assertInstanceOf(\GuzzleHttp\Cookie\CookieJar::class, $cookieJar);

        // Should have no cookies
        $cookieCount = 0;
        foreach ($cookieJar as $cookie) {
            $cookieCount++;
        }
        $this->assertEquals(0, $cookieCount);
    }

    #[Test]
    public function it_skips_malformed_cookie_entries()
    {
        $session = [
            'name'    => 'Mixed Session',
            'env_var' => 'AMAZON_COOKIES_1',
            'cookies' => 'valid_cookie=value; malformed_cookie; another_valid=value2; =no_name; no_value=',
            'index'   => 1,
        ];

        $manager = new CookieSessionManager();
        $cookieJar = $manager->createCookieJar($session);

        // Should only have the valid cookies
        $cookieNames = [];
        foreach ($cookieJar as $cookie) {
            $cookieNames[] = $cookie->getName();
        }

        $this->assertContains('valid_cookie', $cookieNames);
        $this->assertContains('another_valid', $cookieNames);
        $this->assertNotContains('malformed_cookie', $cookieNames);
        // Note: no_value= is actually valid (cookie with empty value), so we expect 3 cookies
        $this->assertCount(3, $cookieNames);
    }

    #[Test]
    public function it_falls_back_to_any_session_when_all_are_unhealthy()
    {
        $manager = new class() extends CookieSessionManager {
            protected function getEnvironmentVariable(string $key, $default = ''): string
            {
                return $key === 'AMAZON_COOKIES_1' ? 'session_id=test1' : $default;
            }
        };

        // Mark the only session as unhealthy
        $manager->markSessionUnhealthy(1, 'Test reason', 60);

        // Should still return the session (with warning logged)
        $session = $manager->getNextCookieSession();
        $this->assertNotNull($session);
        $this->assertEquals(1, $session['index']);
    }

    #[Test]
    public function it_gets_session_by_index()
    {
        $manager = new class() extends CookieSessionManager {
            protected function getEnvironmentVariable(string $key, $default = ''): string
            {
                switch ($key) {
                    case 'AMAZON_COOKIES_2':
                        return 'session_id=test2';
                    case 'AMAZON_COOKIES_5':
                        return 'session_id=test5';
                    default:
                        return $default;
                }
            }
        };

        $session2 = $manager->getSessionByIndex(2);
        $this->assertNotNull($session2);
        $this->assertEquals(2, $session2['index']);
        $this->assertEquals('Session 2', $session2['name']);

        $session3 = $manager->getSessionByIndex(3);
        $this->assertNull($session3); // Doesn't exist

        $session5 = $manager->getSessionByIndex(5);
        $this->assertNotNull($session5);
        $this->assertEquals(5, $session5['index']);
    }
}
