<?php

namespace App\Services\Providers;

use App\Services\LLMProviderInterface;
use App\Services\OpenAIService;

class OpenAIProvider implements LLMProviderInterface
{
    private OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    public function analyzeReviews(array $reviews): array
    {
        return $this->openAIService->analyzeReviews($reviews);
    }

    public function getOptimizedMaxTokens(int $reviewCount): int
    {
        return $this->openAIService->getOptimizedMaxTokens($reviewCount);
    }

    public function isAvailable(): bool
    {
        try {
            // Test with minimal request
            return !empty(config('services.openai.api_key'));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'OpenAI-'.config('services.openai.model', 'gpt-4o-mini');
    }

    public function getEstimatedCost(int $reviewCount): float
    {
        // Estimate based on GPT-4o-mini pricing
        $avgInputTokens = $reviewCount * 50; // ~50 tokens per review
        $avgOutputTokens = $reviewCount * 8;  // ~8 tokens per review response

        $inputCost = ($avgInputTokens / 1000000) * 0.15;  // $0.15 per 1M tokens
        $outputCost = ($avgOutputTokens / 1000000) * 0.60; // $0.60 per 1M tokens

        return $inputCost + $outputCost;
    }
}
