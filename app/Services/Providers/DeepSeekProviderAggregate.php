<?php

namespace App\Services\Providers;

use App\Services\LLMProviderInterface;
use App\Services\LoggingService;
use App\Services\PromptGenerationService;
use Illuminate\Support\Facades\Http;

class DeepSeekProviderAggregate implements LLMProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.deepseek.api_key', '');
        $this->baseUrl = config('services.deepseek.base_url', 'https://api.deepseek.com/v1');
        $this->model = config('services.deepseek.model', 'deepseek-v3');
    }

    public function analyzeReviews(array $reviews): array
    {
        if (empty($reviews)) {
            return [
                'fake_percentage'   => 0.0,
                'confidence'        => 'high',
                'explanation'       => 'No reviews to analyze',
                'fake_examples'     => [],
                'key_patterns'      => [],
                'analysis_provider' => 'DeepSeek-API-'.$this->model,
                'total_cost'        => 0.0,
            ];
        }

        LoggingService::log('Sending '.count($reviews).' reviews to DeepSeek for aggregate analysis');

        // Use centralized prompt generation service
        $promptData = PromptGenerationService::generateReviewAnalysisPrompt(
            $reviews,
            'chat', // DeepSeek uses chat format
            PromptGenerationService::getProviderTextLimit('deepseek')
        );

        try {
            $endpoint = rtrim($this->baseUrl, '/').'/chat/completions';
            $maxTokens = $this->getOptimizedMaxTokens(count($reviews));

            LoggingService::log('Making DeepSeek API request to: '.$endpoint);

            $response = Http::timeout(config('services.deepseek.timeout', 60))
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post($endpoint, [
                    'model'      => $this->model,
                    'messages'   => [
                        ['role' => 'system', 'content' => $promptData['system']],
                        ['role' => 'user', 'content' => $promptData['user']],
                    ],
                    'max_tokens'  => $maxTokens,
                    'temperature' => 0.1,
                ]);

            if ($response->successful()) {
                LoggingService::log('DeepSeek API request successful');
                $result = $response->json();

                return $this->parseAggregateResponse($result);
            } else {
                throw new \Exception('DeepSeek API error: '.$response->status().' - '.$response->body());
            }
        } catch (\Exception $e) {
            LoggingService::log('DeepSeek analysis failed: '.$e->getMessage());

            throw $e;
        }
    }

    private function parseAggregateResponse($response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        LoggingService::log('Parsing DeepSeek response. Content length: '.strlen($content));

        // Attempt to extract JSON if it's wrapped in text or markdown
        $jsonString = $content;

        // 1. Try to find content between ```json and ```
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonString = $matches[1];
        }
        // 2. Or just between ``` and ```
        elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonString = $matches[1];
        }
        // 3. Or find the first { and last }
        else {
            $firstBrace = strpos($content, '{');
            $lastBrace = strrpos($content, '}');
            if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                $jsonString = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);
            }
        }

        try {
            // Direct decode of the extracted string
            $result = json_decode($jsonString, true);

            // If it fails, it might be due to unescaped newlines in the "explanation" or other fields
            if (json_last_error() !== JSON_ERROR_NONE) {
                LoggingService::log('Initial JSON decode failed: '.json_last_error_msg());

                // Try a more aggressive cleanup for common LLM JSON errors
                // Replace literal newlines within string values with \n
                $cleanedJson = preg_replace_callback('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s', function ($matches) {
                    return str_replace(["\n", "\r"], ['\n', ''], $matches[0]);
                }, $jsonString);

                $result = json_decode($cleanedJson, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    LoggingService::log('Cleaned JSON decode also failed: '.json_last_error_msg());
                }
            }

            if (is_array($result) && isset($result['fake_percentage'])) {
                LoggingService::log('DeepSeek: Successfully parsed aggregate analysis - '.$result['fake_percentage'].'% fake');

                return [
                    'fake_percentage'   => (float) $result['fake_percentage'],
                    'confidence'        => $result['confidence'] ?? 'medium',
                    'explanation'       => $result['explanation'] ?? '',
                    'fake_examples'     => $result['fake_examples'] ?? [],
                    'key_patterns'      => $result['key_patterns'] ?? [],
                    'analysis_provider' => 'DeepSeek-API-'.$this->model,
                    'total_cost'        => $this->calculateCost($response),
                ];
            }
        } catch (\Exception $e) {
            LoggingService::log('Exception during DeepSeek response parsing: '.$e->getMessage());
        }

        // Fallback for unexpected formats
        return [
            'fake_percentage'   => 0.0,
            'confidence'        => 'low',
            'explanation'       => 'Failed to parse model response.',
            'fake_examples'     => [],
            'key_patterns'      => [],
            'analysis_provider' => 'DeepSeek-API-'.$this->model,
            'total_cost'        => $this->calculateCost($response),
        ];
    }

    /**
     * Calculate the real-time cost of the DeepSeek API request.
     */
    public function calculateCost(array $response): float
    {
        if ($this->isLocalhost()) {
            return 0.0;
        }

        $usage = $response['usage'] ?? [];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        // DeepSeek API pricing (as of 2024): $0.27 per 1M input, $1.10 per 1M output
        return (($inputTokens / 1000000) * 0.27) + (($outputTokens / 1000000) * 1.10);
    }

    public function getOptimizedMaxTokens(int $reviewCount): int
    {
        // For aggregate responses, we need much fewer tokens than individual scoring
        // Aggregate response: ~200-500 tokens vs individual: ~30 tokens per review
        $baseTokens = 500; // Base for aggregate response structure
        $buffer = min(1000, $reviewCount * 2); // Small buffer for examples and patterns

        // Minimum 800 tokens for meaningful aggregate analysis
        $minTokens = 800;

        return max($minTokens, $baseTokens + $buffer);
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) &&
               !$this->isLocalhost() &&
               !empty($this->baseUrl);
    }

    public function getProviderName(): string
    {
        return 'DeepSeek-API-'.$this->model;
    }

    public function getEstimatedCost(int $reviewCount): float
    {
        // DeepSeek pricing estimate for aggregate analysis
        return 0.0001; // Placeholder cost
    }

    private function isLocalhost(): bool
    {
        return str_contains($this->baseUrl, 'localhost') ||
               str_contains($this->baseUrl, '127.0.0.1') ||
               str_contains($this->baseUrl, '192.168.') ||
               str_contains($this->baseUrl, '10.0.');
    }
}

