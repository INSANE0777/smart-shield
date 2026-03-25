<?php

namespace App\Services\Amazon;

use App\Services\LoggingService;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing multiple Amazon cookie sessions with round-robin rotation.
 *
 * This service manages up to 10 Amazon cookie sessions (AMAZON_COOKIES_1 through AMAZON_COOKIES_10)
 * and rotates through them to distribute load and reduce CAPTCHA challenges.
 */
class CookieSessionManager
{
    private const MAX_COOKIE_SESSIONS = 10;
    private const ROTATION_CACHE_KEY = 'amazon_cookie_rotation_index';
    private const SESSION_HEALTH_CACHE_PREFIX = 'amazon_session_health_';

    private array $cookieSessions = [];
    private int $currentSessionIndex = 0;

    /**
     * Initialize the cookie session manager.
     */
    public function __construct()
    {
        $this->loadCookieSessions();
        $this->currentSessionIndex = $this->getRotationIndex();
    }

    /**
     * Load all available cookie sessions from environment variables.
     */
    private function loadCookieSessions(): void
    {
        $this->cookieSessions = [];

        for ($i = 1; $i <= self::MAX_COOKIE_SESSIONS; $i++) {
            $cookieString = $this->getEnvironmentVariable("AMAZON_COOKIES_{$i}");

            if (!empty($cookieString)) {
                $this->cookieSessions[$i] = [
                    'env_var' => "AMAZON_COOKIES_{$i}",
                    'cookies' => $cookieString,
                    'name'    => "Session {$i}",
                    'index'   => $i,
                ];
            }
        }

        LoggingService::log('Loaded Amazon cookie sessions', [
            'total_sessions'  => count($this->cookieSessions),
            'session_indexes' => array_keys($this->cookieSessions),
        ]);
    }

    /**
     * Get environment variable value (can be overridden for testing).
     */
    protected function getEnvironmentVariable(string $key, $default = ''): string
    {
        return env($key, $default);
    }

    /**
     * Get the next cookie session using round-robin rotation.
     *
     * @return array|null Cookie session data or null if no sessions available
     */
    public function getNextCookieSession(): ?array
    {
        if (empty($this->cookieSessions)) {
            LoggingService::log('No Amazon cookie sessions available');

            return null;
        }

        $sessionIndexes = array_keys($this->cookieSessions);
        $totalSessions = count($sessionIndexes);

        // Find the next available session starting from current index
        $attempts = 0;
        while ($attempts < $totalSessions) {
            $currentIndex = $sessionIndexes[$this->currentSessionIndex % $totalSessions];
            $session = $this->cookieSessions[$currentIndex];

            // Check if this session is healthy (not recently flagged for CAPTCHA)
            if ($this->isSessionHealthy($currentIndex)) {
                $this->advanceRotationIndex();
                LoggingService::log('Selected Amazon cookie session', [
                    'session_index' => $currentIndex,
                    'session_name'  => $session['name'],
                    'env_var'       => $session['env_var'],
                ]);

                return $session;
            }

            // Session is unhealthy, try next one
            $this->advanceRotationIndex();
            $attempts++;
        }

        // All sessions are unhealthy, return the current one anyway but log warning
        $currentIndex = $sessionIndexes[$this->currentSessionIndex % $totalSessions];
        $session = $this->cookieSessions[$currentIndex];

        LoggingService::log('All Amazon cookie sessions are unhealthy, using current session anyway', [
            'session_index' => $currentIndex,
            'session_name'  => $session['name'],
        ]);

        $this->advanceRotationIndex();

        return $session;
    }

    /**
     * Get current cookie session without advancing rotation.
     *
     * @return array|null Cookie session data or null if no sessions available
     */
    public function getCurrentCookieSession(): ?array
    {
        if (empty($this->cookieSessions)) {
            return null;
        }

        $sessionIndexes = array_keys($this->cookieSessions);
        $currentIndex = $sessionIndexes[$this->currentSessionIndex % count($sessionIndexes)];

        return $this->cookieSessions[$currentIndex];
    }

    /**
     * Create a configured CookieJar for the given session.
     *
     * @param array $session Cookie session data
     *
     * @return CookieJar Configured cookie jar
     */
    public function createCookieJar(array $session): CookieJar
    {
        $cookieJar = new CookieJar();
        $cookieString = $session['cookies'];

        if (empty($cookieString)) {
            LoggingService::log('Empty cookie string for session', ['session' => $session['name']]);

            return $cookieJar;
        }

        // Parse cookie string format: "name1=value1; name2=value2; name3=value3"
        $cookies = explode(';', $cookieString);
        $loadedCount = 0;

        foreach ($cookies as $cookie) {
            $cookie = trim($cookie);
            if (empty($cookie)) {
                continue;
            }

            $parts = explode('=', $cookie, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = trim($parts[1]);

            $cookieJar->setCookie(new SetCookie([
                'Name'     => $name,
                'Value'    => $value,
                'Domain'   => '.amazon.com',
                'Path'     => '/',
                'Secure'   => true,
                'HttpOnly' => true,
            ]));

            $loadedCount++;
        }

        LoggingService::log('Created cookie jar for session', [
            'session_name'   => $session['name'],
            'cookies_loaded' => $loadedCount,
        ]);

        return $cookieJar;
    }

    /**
     * Mark a session as unhealthy (CAPTCHA detected, session expired, etc.).
     *
     * @param int    $sessionIndex    Session index that's unhealthy
     * @param string $reason          Reason for marking unhealthy
     * @param int    $cooldownMinutes How long to mark as unhealthy (default 30 minutes)
     */
    public function markSessionUnhealthy(int $sessionIndex, string $reason, int $cooldownMinutes = 30): void
    {
        $cacheKey = self::SESSION_HEALTH_CACHE_PREFIX.$sessionIndex;
        $expiresAt = now()->addMinutes($cooldownMinutes);

        Cache::put($cacheKey, [
            'reason'     => $reason,
            'marked_at'  => now()->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ], $expiresAt);

        LoggingService::log('Marked Amazon session as unhealthy', [
            'session_index'    => $sessionIndex,
            'session_name'     => $this->cookieSessions[$sessionIndex]['name'] ?? "Session {$sessionIndex}",
            'reason'           => $reason,
            'cooldown_minutes' => $cooldownMinutes,
            'expires_at'       => $expiresAt->toISOString(),
        ]);
    }

    /**
     * Check if a session is healthy (not recently flagged).
     *
     * @param int $sessionIndex Session index to check
     *
     * @return bool True if healthy, false if on cooldown
     */
    private function isSessionHealthy(int $sessionIndex): bool
    {
        $cacheKey = self::SESSION_HEALTH_CACHE_PREFIX.$sessionIndex;

        return !Cache::has($cacheKey);
    }

    /**
     * Get the current rotation index from cache.
     *
     * @return int Current rotation index
     */
    private function getRotationIndex(): int
    {
        return Cache::get(self::ROTATION_CACHE_KEY, 0);
    }

    /**
     * Advance the rotation index and store in cache.
     */
    private function advanceRotationIndex(): void
    {
        $this->currentSessionIndex = ($this->currentSessionIndex + 1) % count($this->cookieSessions);
        Cache::put(self::ROTATION_CACHE_KEY, $this->currentSessionIndex, now()->addHours(24));
    }

    /**
     * Get information about all cookie sessions.
     *
     * @return array Session information including health status
     */
    public function getSessionInfo(): array
    {
        $info = [];

        foreach ($this->cookieSessions as $index => $session) {
            $healthCacheKey = self::SESSION_HEALTH_CACHE_PREFIX.$index;
            $healthInfo = Cache::get($healthCacheKey);

            $info[] = [
                'index'        => $index,
                'name'         => $session['name'],
                'env_var'      => $session['env_var'],
                'has_cookies'  => !empty($session['cookies']),
                'cookie_count' => count(explode(';', $session['cookies'])),
                'is_healthy'   => !$healthInfo,
                'health_info'  => $healthInfo,
                'is_current'   => $index === $this->getCurrentSessionIndex(),
            ];
        }

        return $info;
    }

    /**
     * Get the current session index.
     *
     * @return int Current session index
     */
    private function getCurrentSessionIndex(): int
    {
        if (empty($this->cookieSessions)) {
            return 0;
        }

        $sessionIndexes = array_keys($this->cookieSessions);

        return $sessionIndexes[$this->currentSessionIndex % count($sessionIndexes)];
    }

    /**
     * Reset session health (clear all cooldowns).
     */
    public function resetAllSessionHealth(): void
    {
        for ($i = 1; $i <= self::MAX_COOKIE_SESSIONS; $i++) {
            Cache::forget(self::SESSION_HEALTH_CACHE_PREFIX.$i);
        }

        LoggingService::log('Reset all Amazon session health status');
    }

    /**
     * Get total number of available sessions.
     *
     * @return int Number of available cookie sessions
     */
    public function getSessionCount(): int
    {
        return count($this->cookieSessions);
    }

    /**
     * Check if any sessions are available.
     *
     * @return bool True if at least one session is available
     */
    public function hasAnySessions(): bool
    {
        return !empty($this->cookieSessions);
    }

    /**
     * Get session by index.
     *
     * @param int $index Session index
     *
     * @return array|null Session data or null if not found
     */
    public function getSessionByIndex(int $index): ?array
    {
        return $this->cookieSessions[$index] ?? null;
    }
}
