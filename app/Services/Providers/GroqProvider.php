<?php

namespace App\Services\Providers;

use App\Services\GroqService;
use App\Services\LLMProviderInterface;

class GroqProvider implements LLMProviderInterface
{
    private GroqService $groqService;

    public function __construct(GroqService $groqService)
    {
        $this->groqService = $groqService;
    }

    public function analyzeReviews(array $reviews): array
    {
        return $this->groqService->analyzeReviews($reviews);
    }

    public function getOptimizedMaxTokens(int $reviewCount): int
    {
        return $this->groqService->getOptimizedMaxTokens($reviewCount);
    }

    public function isAvailable(): bool
    {
        try {
            return !empty(config('services.groq.api_key'));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'Groq-'.config('services.groq.model', 'llama-3.3-70b-versatile');
    }

    public function getEstimatedCost(int $reviewCount): float
    {
        // Estimate based on Groq Llama 3.3 70B pricing (extremely low, $0.59 / 1M input tokens)
        $avgInputTokens = $reviewCount * 50; // ~50 tokens per review
        $avgOutputTokens = $reviewCount * 8;  // ~8 tokens per review response

        $inputCost = ($avgInputTokens / 1000000) * 0.59;
        $outputCost = ($avgOutputTokens / 1000000) * 0.79;

        return $inputCost + $outputCost;
    }
}
