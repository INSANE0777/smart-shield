<?php

namespace App\Services;

interface LLMProviderInterface
{
    /**
     * Analyze reviews using the provider's LLM.
     */
    public function analyzeReviews(array $reviews): array;

    /**
     * Get provider-specific optimized max tokens.
     */
    public function getOptimizedMaxTokens(int $reviewCount): int;

    /**
     * Check if provider is available/healthy.
     */
    public function isAvailable(): bool;

    /**
     * Get provider name for logging.
     */
    public function getProviderName(): string;

    /**
     * Get cost per analysis (for tracking).
     */
    public function getEstimatedCost(int $reviewCount): float;
}
