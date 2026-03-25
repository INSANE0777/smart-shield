<?php

namespace App\Services\Amazon;

use App\Services\LoggingService;
use Illuminate\Support\Facades\Cache;

/**
 * Manages proxy rotation for Amazon scraping to avoid IP-based blocking.
 * Supports both third-party proxy services and custom proxy networks.
 */
class ProxyManager
{
    private array $proxyProviders = [];
    private array $customProxies = [];
    private string $activeProvider = '';

    public function __construct()
    {
        $this->initializeProxyProviders();
        $this->loadCustomProxies();
    }

    /**
     * Initialize third-party proxy providers.
     */
    private function initializeProxyProviders(): void
    {
        // Bright Data (formerly Luminati) - Premium residential proxies
        if (env('BRIGHTDATA_USERNAME') && env('BRIGHTDATA_PASSWORD')) {
            $this->proxyProviders['brightdata'] = [
                'type'             => 'residential',
                'endpoint'         => env('BRIGHTDATA_ENDPOINT', 'brd-customer-hl_USERNAME-zone-static.brd.superproxy.io:22225'),
                'username'         => env('BRIGHTDATA_USERNAME'),
                'password'         => env('BRIGHTDATA_PASSWORD'),
                'session_rotation' => true,
                'country'          => 'US',
                'cost_per_gb'      => 15.00, // Example pricing
                'reliability'      => 0.95,
            ];
        }

        // Oxylabs - Residential and datacenter proxies
        if (env('OXYLABS_USERNAME') && env('OXYLABS_PASSWORD')) {
            $this->proxyProviders['oxylabs'] = [
                'type'             => 'residential',
                'endpoint'         => env('OXYLABS_ENDPOINT', 'pr.oxylabs.io:7777'),
                'username'         => env('OXYLABS_USERNAME'),
                'password'         => env('OXYLABS_PASSWORD'),
                'session_rotation' => true,
                'country'          => 'US',
                'cost_per_gb'      => 12.00,
                'reliability'      => 0.92,
            ];
        }

        // Smartproxy - Affordable residential proxies
        if (env('SMARTPROXY_USERNAME') && env('SMARTPROXY_PASSWORD')) {
            $this->proxyProviders['smartproxy'] = [
                'type'             => 'residential',
                'endpoint'         => env('SMARTPROXY_ENDPOINT', 'gate.smartproxy.com:7000'),
                'username'         => env('SMARTPROXY_USERNAME'),
                'password'         => env('SMARTPROXY_PASSWORD'),
                'session_rotation' => true,
                'country'          => 'US',
                'cost_per_gb'      => 8.50,
                'reliability'      => 0.88,
            ];
        }

        // ProxyMesh - Datacenter proxies (cheaper but more detectable)
        if (env('PROXYMESH_USERNAME') && env('PROXYMESH_PASSWORD')) {
            $this->proxyProviders['proxymesh'] = [
                'type'             => 'datacenter',
                'endpoint'         => env('PROXYMESH_ENDPOINT', 'us-wa.proxymesh.com:31280'),
                'username'         => env('PROXYMESH_USERNAME'),
                'password'         => env('PROXYMESH_PASSWORD'),
                'session_rotation' => false,
                'country'          => 'US',
                'cost_per_gb'      => 2.00,
                'reliability'      => 0.85,
            ];
        }

        LoggingService::log('Initialized proxy providers', [
            'providers' => array_keys($this->proxyProviders),
            'count'     => count($this->proxyProviders),
        ]);
    }

    /**
     * Load custom proxy network configuration.
     */
    private function loadCustomProxies(): void
    {
        $customProxyConfig = env('CUSTOM_PROXIES', '');

        if (empty($customProxyConfig)) {
            return;
        }

        // Format: "ip1:port1:user1:pass1,ip2:port2:user2:pass2"
        $proxies = explode(',', $customProxyConfig);

        foreach ($proxies as $proxy) {
            $parts = explode(':', trim($proxy));
            if (count($parts) >= 2) {
                $this->customProxies[] = [
                    'ip'            => $parts[0],
                    'port'          => $parts[1],
                    'username'      => $parts[2] ?? null,
                    'password'      => $parts[3] ?? null,
                    'country'       => $parts[4] ?? 'US',
                    'last_used'     => 0,
                    'failure_count' => 0,
                    'success_count' => 0,
                ];
            }
        }

        LoggingService::log('Loaded custom proxies', [
            'count' => count($this->customProxies),
        ]);
    }

    /**
     * Get the best available proxy configuration.
     */
    public function getProxyConfig(): ?array
    {
        // Try custom proxies first (usually cheaper/faster)
        if (!empty($this->customProxies)) {
            $proxy = $this->selectBestCustomProxy();
            if ($proxy) {
                return $this->formatProxyConfig($proxy, 'custom');
            }
        }

        // Fall back to third-party providers
        if (!empty($this->proxyProviders)) {
            $provider = $this->selectBestProvider();
            if ($provider) {
                return $this->formatProxyConfig($provider, 'provider');
            }
        }

        LoggingService::log('No proxy configuration available');

        return null;
    }

    /**
     * Select the best custom proxy based on performance and usage.
     */
    private function selectBestCustomProxy(): ?array
    {
        if (empty($this->customProxies)) {
            return null;
        }

        // Sort by success rate and last used time
        usort($this->customProxies, function ($a, $b) {
            $aSuccessRate = $a['success_count'] / max(1, $a['success_count'] + $a['failure_count']);
            $bSuccessRate = $b['success_count'] / max(1, $b['success_count'] + $b['failure_count']);

            if ($aSuccessRate === $bSuccessRate) {
                return $a['last_used'] <=> $b['last_used']; // Prefer least recently used
            }

            return $bSuccessRate <=> $aSuccessRate; // Prefer higher success rate
        });

        // Skip recently failed proxies
        $now = time();
        foreach ($this->customProxies as &$proxy) {
            if ($proxy['failure_count'] > 3 && ($now - $proxy['last_used']) < 300) {
                continue; // Skip for 5 minutes after failures
            }

            $proxy['last_used'] = $now;

            return $proxy;
        }

        return null;
    }

    /**
     * Select the best third-party provider.
     */
    private function selectBestProvider(): ?array
    {
        if (empty($this->proxyProviders)) {
            return null;
        }

        // Choose provider based on reliability and cost
        $bestProvider = null;
        $bestScore = 0;

        foreach ($this->proxyProviders as $name => $provider) {
            // Score based on reliability (70%) and inverse cost (30%)
            $score = ($provider['reliability'] * 0.7) + ((20 - $provider['cost_per_gb']) / 20 * 0.3);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestProvider = $provider;
                $this->activeProvider = $name;
            }
        }

        return $bestProvider;
    }

    /**
     * Format proxy configuration for Guzzle HTTP client.
     */
    private function formatProxyConfig(array $proxy, string $type): array
    {
        if ($type === 'custom') {
            $proxyUrl = "http://{$proxy['ip']}:{$proxy['port']}";
            if ($proxy['username'] && $proxy['password']) {
                $proxyUrl = "http://{$proxy['username']}:{$proxy['password']}@{$proxy['ip']}:{$proxy['port']}";
            }

            return [
                'proxy'   => $proxyUrl,
                'type'    => 'custom',
                'country' => $proxy['country'],
                'verify'  => false,
                'timeout' => 30,
            ];
        } else {
            // Third-party provider with session rotation support
            $username = $proxy['username'];
            $password = $proxy['password'];

            // Implement session rotation for supported providers
            if ($proxy['session_rotation'] && $this->activeProvider === 'brightdata') {
                $sessionId = $this->getOrCreateSessionId($this->activeProvider);
                $username = $proxy['username'].'-session-'.$sessionId;

                LoggingService::log('Using Bright Data session rotation', [
                    'provider'          => $this->activeProvider,
                    'session_id'        => $sessionId,
                    'original_username' => $proxy['username'],
                ]);
            }

            $proxyUrl = "http://{$username}:{$password}@{$proxy['endpoint']}";

            return [
                'proxy'      => $proxyUrl,
                'type'       => $proxy['type'],
                'country'    => $proxy['country'],
                'verify'     => false,
                'timeout'    => 30,
                'provider'   => $this->activeProvider,
                'session_id' => $sessionId ?? null,
            ];
        }
    }

    /**
     * Report proxy success for performance tracking.
     */
    public function reportSuccess(array $proxyConfig): void
    {
        if ($proxyConfig['type'] === 'custom') {
            $this->updateCustomProxyStats($proxyConfig, true);
        }

        LoggingService::log('Proxy success reported', [
            'type'     => $proxyConfig['type'],
            'provider' => $proxyConfig['provider'] ?? 'custom',
        ]);
    }

    /**
     * Report proxy failure for performance tracking.
     */
    public function reportFailure(array $proxyConfig, string $error): void
    {
        if ($proxyConfig['type'] === 'custom') {
            $this->updateCustomProxyStats($proxyConfig, false);
        }

        // Auto-rotate session on failure for session-based providers
        if (isset($proxyConfig['provider']) &&
            isset($this->proxyProviders[$proxyConfig['provider']]['session_rotation']) &&
            $this->proxyProviders[$proxyConfig['provider']]['session_rotation']) {
            $this->rotateSession();

            LoggingService::log('Session rotated due to failure', [
                'provider' => $proxyConfig['provider'],
                'error'    => $error,
            ]);
        }

        LoggingService::log('Proxy failure reported', [
            'type'     => $proxyConfig['type'],
            'provider' => $proxyConfig['provider'] ?? 'custom',
            'error'    => $error,
        ]);
    }

    /**
     * Update custom proxy statistics.
     */
    private function updateCustomProxyStats(array $proxyConfig, bool $success): void
    {
        $proxyUrl = $proxyConfig['proxy'];

        foreach ($this->customProxies as &$proxy) {
            $expectedUrl = "http://{$proxy['ip']}:{$proxy['port']}";
            if (strpos($proxyUrl, $expectedUrl) !== false) {
                if ($success) {
                    $proxy['success_count']++;
                } else {
                    $proxy['failure_count']++;
                }
                break;
            }
        }
    }

    /**
     * Get proxy statistics for monitoring.
     */
    public function getProxyStats(): array
    {
        $stats = [
            'providers'       => count($this->proxyProviders),
            'custom_proxies'  => count($this->customProxies),
            'active_provider' => $this->activeProvider,
        ];

        if (!empty($this->customProxies)) {
            $totalSuccess = array_sum(array_column($this->customProxies, 'success_count'));
            $totalFailure = array_sum(array_column($this->customProxies, 'failure_count'));
            $stats['custom_success_rate'] = $totalSuccess / max(1, $totalSuccess + $totalFailure);
        }

        return $stats;
    }

    /**
     * Generate session ID for session-based proxies.
     */
    public function generateSessionId(): string
    {
        return 'amazon_scrape_'.time().'_'.substr(md5(uniqid()), 0, 8);
    }

    /**
     * Get or create a session ID for the given provider.
     */
    private function getOrCreateSessionId(string $provider): string
    {
        $cacheKey = 'proxy_session_'.$provider;

        $sessionId = Cache::get($cacheKey);
        if (!$sessionId) {
            $sessionId = $this->generateSessionId();
            // Cache session for 10 minutes (can be rotated manually)
            Cache::put($cacheKey, $sessionId, 600);

            LoggingService::log('Created new proxy session', [
                'provider'   => $provider,
                'session_id' => $sessionId,
            ]);
        }

        return $sessionId;
    }

    /**
     * Rotate proxy session (for providers that support it).
     */
    public function rotateSession(): void
    {
        if (!empty($this->proxyProviders[$this->activeProvider]['session_rotation'])) {
            // For session-based proxies, we can force rotation by changing session ID
            Cache::forget('proxy_session_'.$this->activeProvider);
            LoggingService::log('Proxy session rotated', ['provider' => $this->activeProvider]);
        }
    }
}
