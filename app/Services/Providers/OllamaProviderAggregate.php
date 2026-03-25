<?php

namespace App\Services\Providers;

use App\Services\LLMProviderInterface;
use App\Services\LoggingService;
use App\Services\PromptGenerationService;
use Illuminate\Support\Facades\Http;

class OllamaProviderAggregate implements LLMProviderInterface
{
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.base_url', 'http://localhost:11434');
        $this->model = config('services.ollama.model') ?: 'llama3.2:3b';
        $this->timeout = config('services.ollama.timeout', 120);
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
                'analysis_provider' => 'Ollama-'.$this->model,
                'total_cost'        => 0.0,
            ];
        }

        LoggingService::log('Sending '.count($reviews).' reviews to Ollama for aggregate analysis');

        // Use centralized prompt generation service
        $promptData = PromptGenerationService::generateReviewAnalysisPrompt(
            $reviews,
            'single', // Ollama uses single prompt format
            PromptGenerationService::getProviderTextLimit('ollama')
        );

        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/api/generate", [
                'model'   => $this->model,
                'prompt'  => $promptData['prompt'],
                'stream'  => false,
                'options' => [
                    'num_ctx'     => 8192,  // Increased context window for aggregate analysis
                    'num_predict' => 1024,  // Sufficient for aggregate response
                    'temperature' => 0.1,
                ],
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return $this->parseAggregateResponse($result);
            }

            $statusCode = $response->status();
            $body = $response->body();

            // Enhanced error detection for HTML responses (service down)
            if (str_starts_with(trim($body), '<html') || str_starts_with(trim($body), '<!DOCTYPE')) {
                throw new \Exception("Ollama service is returning HTML instead of JSON (HTTP {$statusCode}). This usually means Ollama is down or misconfigured. Check if Ollama is running on {$this->baseUrl}");
            }

            throw new \Exception("Ollama API request failed (HTTP {$statusCode}): ".substr($body, 0, 200));
        } catch (\Exception $e) {
            LoggingService::log('Ollama analysis failed: '.$e->getMessage());

            throw $e;
        }
    }

    private function parseAggregateResponse($response): array
    {
        $content = $response['response'] ?? '';

        LoggingService::log('Parsing Ollama response. Content length: '.strlen($content));

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
                LoggingService::log('Ollama: Successfully parsed aggregate analysis - '.$result['fake_percentage'].'% fake');

                return [
                    'fake_percentage'   => (float) $result['fake_percentage'],
                    'confidence'        => $result['confidence'] ?? 'medium',
                    'explanation'       => $result['explanation'] ?? '',
                    'fake_examples'     => $result['fake_examples'] ?? [],
                    'key_patterns'      => $result['key_patterns'] ?? [],
                    'analysis_provider' => 'Ollama-'.$this->model,
                    'total_cost'        => 0.0,
                ];
            }
        } catch (\Exception $e) {
            LoggingService::log('Exception during Ollama response parsing: '.$e->getMessage());
        }

        // Fallback for unexpected formats
        return [
            'fake_percentage'   => 0.0,
            'confidence'        => 'low',
            'explanation'       => 'Failed to parse model response.',
            'fake_examples'     => [],
            'key_patterns'      => [],
            'analysis_provider' => 'Ollama-'.$this->model,
            'total_cost'        => 0.0,
        ];
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/tags");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        return 'Ollama-'.$this->model;
    }

    public function getOptimizedMaxTokens(int $reviewCount): int
    {
        // For aggregate responses, we need much fewer tokens than individual scoring
        return 1024; // Fixed size for aggregate analysis
    }

    public function getEstimatedCost(int $reviewCount): float
    {
        return 0.0; // Ollama is free
    }
}

