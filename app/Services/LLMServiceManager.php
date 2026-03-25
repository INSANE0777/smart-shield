<?php

namespace App\Services;

use App\Services\Providers\DeepSeekProvider;
use App\Services\Providers\GroqProvider;
use App\Services\Providers\OllamaProvider;
use App\Services\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Cache;

class LLMServiceManager
{
    private array $providers = [];
    private ?LLMProviderInterface $primaryProvider = null;
    private array $fallbackProviders = [];

    public function __construct()
    {
        $this->initializeProviders();
    }

    /**
     * Analyze reviews with automatic fallback.
     */
    public function analyzeReviews(array $reviews): array
    {
        $providers = $this->getAvailableProviders();

        foreach ($providers as $provider) {
            try {
                LoggingService::log("Attempting analysis with provider: {$provider->getProviderName()}");

                $startTime = microtime(true);
                $result = $provider->analyzeReviews($reviews);
                $duration = microtime(true) - $startTime;

                // Use actual cost from result if available, otherwise fallback to estimated cost
                $cost = $result['total_cost'] ?? $provider->getEstimatedCost(count($reviews));

                // Log success metrics
                LoggingService::log("Analysis successful with {$provider->getProviderName()}", [
                    'duration'     => round($duration, 2),
                    'cost'         => $cost,
                    'review_count' => count($reviews),
                ]);

                // Track provider performance
                $this->trackProviderSuccess($provider, $duration, $cost);

                return $result;
            } catch (\Exception $e) {
                LoggingService::log("Provider {$provider->getProviderName()} failed: {$e->getMessage()}");
                $this->trackProviderFailure($provider, $e->getMessage());

                // Continue to next provider
                continue;
            }
        }

        throw new \Exception('All LLM providers failed. Check logs for details.');
    }

    /**
     * Get the optimal provider based on current configuration and health.
     */
    public function getOptimalProvider(): ?LLMProviderInterface
    {
        $providers = $this->getAvailableProviders();

        if (empty($providers)) {
            return null;
        }

        // Return the first available provider (already sorted by preference)
        return $providers[0];
    }

    /**
     * Get cost estimates from all available providers.
     */
    public function getCostComparison(int $reviewCount): array
    {
        $comparison = [];

        foreach ($this->providers as $provider) {
            if ($provider->isAvailable()) {
                $comparison[$provider->getProviderName()] = [
                    'cost'         => $provider->getEstimatedCost($reviewCount),
                    'available'    => true,
                    'health_score' => $this->getProviderHealthScore($provider),
                ];
            } else {
                $comparison[$provider->getProviderName()] = [
                    'cost'         => null,
                    'available'    => false,
                    'health_score' => 0,
                ];
            }
        }

        return $comparison;
    }

    /**
     * Switch primary provider.
     */
    public function switchProvider(string $providerName): bool
    {
        $provider = $this->findProviderByName($providerName);

        if (!$provider || !$provider->isAvailable()) {
            return false;
        }

        $this->primaryProvider = $provider;

        // Update configuration cache
        Cache::put('llm.primary_provider', $providerName, now()->addHours(24));

        LoggingService::log("Switched primary LLM provider to: {$providerName}");

        return true;
    }

    /**
     * Get provider performance metrics.
     */
    public function getProviderMetrics(): array
    {
        $metrics = [];

        foreach ($this->providers as $provider) {
            $name = $provider->getProviderName();
            $metrics[$name] = [
                'available'         => $provider->isAvailable(),
                'success_rate'      => $this->getProviderSuccessRate($provider),
                'avg_response_time' => $this->getProviderAvgResponseTime($provider),
                'total_requests'    => $this->getProviderTotalRequests($provider),
                'last_success'      => $this->getProviderLastSuccess($provider),
                'health_score'      => $this->getProviderHealthScore($provider),
            ];
        }

        return $metrics;
    }

    private function initializeProviders(): void
    {
        // Initialize OpenAI provider
        $this->providers['openai'] = app(OpenAIProvider::class);

        // Initialize DeepSeek provider
        $this->providers['deepseek'] = app(DeepSeekProvider::class);

        // Initialize Groq provider
        $this->providers['groq'] = app(GroqProvider::class);

        // Initialize Ollama provider
        $this->providers['ollama'] = app(OllamaProvider::class);

        // Set primary provider based on config
        $primaryProviderName = config('services.llm.primary_provider', 'openai');
        $this->primaryProvider = $this->providers[$primaryProviderName] ?? $this->providers['openai'];

        // Set fallback order
        $fallbackOrder = config('services.llm.fallback_order', ['groq', 'deepseek', 'openai']);
        foreach ($fallbackOrder as $providerName) {
            if (isset($this->providers[$providerName]) && $this->providers[$providerName] !== $this->primaryProvider) {
                $this->fallbackProviders[] = $this->providers[$providerName];
            }
        }
    }

    private function getAvailableProviders(): array
    {
        $providers = [];

        // Add primary provider first if available
        if ($this->primaryProvider && $this->primaryProvider->isAvailable()) {
            $providers[] = $this->primaryProvider;
        }

        // Add fallback providers
        foreach ($this->fallbackProviders as $provider) {
            if ($provider->isAvailable() && !in_array($provider, $providers, true)) {
                $providers[] = $provider;
            }
        }

        // If primary provider is down, add it to fallbacks
        foreach ($this->providers as $provider) {
            if ($provider->isAvailable() && !in_array($provider, $providers, true)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    private function findProviderByName(string $name): ?LLMProviderInterface
    {
        foreach ($this->providers as $provider) {
            if (str_contains($provider->getProviderName(), $name)) {
                return $provider;
            }
        }

        return null;
    }

    private function trackProviderSuccess(LLMProviderInterface $provider, float $duration, float $cost): void
    {
        try {
            $key = 'llm.metrics.'.md5($provider->getProviderName());

            $metrics = Cache::get($key, [
                'success_count'  => 0,
                'failure_count'  => 0,
                'total_duration' => 0,
                'total_cost'     => 0,
                'last_success'   => null,
            ]);

            $metrics['success_count']++;
            $metrics['total_duration'] += $duration;
            $metrics['total_cost'] += $cost;
            $metrics['last_success'] = now()->toISOString();

            Cache::put($key, $metrics, now()->addDays(7));
        } catch (\Exception $e) {
            // Gracefully handle cache failures - don't let cache issues break LLM functionality
            LoggingService::log("Cache operation failed for provider success tracking: {$e->getMessage()}", [
                'provider' => $provider->getProviderName(),
                'duration' => $duration,
                'cost'     => $cost,
            ]);
        }
    }

    private function trackProviderFailure(LLMProviderInterface $provider, string $error): void
    {
        try {
            $key = 'llm.metrics.'.md5($provider->getProviderName());

            $metrics = Cache::get($key, [
                'success_count'  => 0,
                'failure_count'  => 0,
                'total_duration' => 0,
                'total_cost'     => 0,
                'last_failure'   => null,
                'last_error'     => null,
            ]);

            $metrics['failure_count']++;
            $metrics['last_failure'] = now()->toISOString();
            $metrics['last_error'] = $error;

            Cache::put($key, $metrics, now()->addDays(7));
        } catch (\Exception $e) {
            // Gracefully handle cache failures - don't let cache issues break LLM functionality
            LoggingService::log("Cache operation failed for provider failure tracking: {$e->getMessage()}", [
                'provider'       => $provider->getProviderName(),
                'original_error' => $error,
            ]);
        }
    }

    private function getProviderSuccessRate(LLMProviderInterface $provider): float
    {
        $key = 'llm.metrics.'.md5($provider->getProviderName());
        $metrics = Cache::get($key, ['success_count' => 0, 'failure_count' => 0]);

        $total = $metrics['success_count'] + $metrics['failure_count'];

        return $total > 0 ? ($metrics['success_count'] / $total) * 100 : 100;
    }

    private function getProviderAvgResponseTime(LLMProviderInterface $provider): float
    {
        $key = 'llm.metrics.'.md5($provider->getProviderName());
        $metrics = Cache::get($key, ['success_count' => 0, 'total_duration' => 0]);

        return $metrics['success_count'] > 0 ? $metrics['total_duration'] / $metrics['success_count'] : 0;
    }

    private function getProviderTotalRequests(LLMProviderInterface $provider): int
    {
        $key = 'llm.metrics.'.md5($provider->getProviderName());
        $metrics = Cache::get($key, ['success_count' => 0, 'failure_count' => 0]);

        return $metrics['success_count'] + $metrics['failure_count'];
    }

    private function getProviderLastSuccess(LLMProviderInterface $provider): ?string
    {
        $key = 'llm.metrics.'.md5($provider->getProviderName());
        $metrics = Cache::get($key, ['last_success' => null]);

        return $metrics['last_success'];
    }

    private function getProviderHealthScore(LLMProviderInterface $provider): float
    {
        if (!$provider->isAvailable()) {
            return 0;
        }

        $successRate = $this->getProviderSuccessRate($provider);
        $avgResponseTime = $this->getProviderAvgResponseTime($provider);

        // Health score based on success rate and response time
        $healthScore = $successRate;

        // Penalize slow response times
        if ($avgResponseTime > 30) {
            $healthScore *= 0.8;
        } elseif ($avgResponseTime > 60) {
            $healthScore *= 0.6;
        }

        return round($healthScore, 2);
    }
}

