<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key') ?? '';
        $this->model = config('services.groq.model', 'llama-3.3-70b-versatile');
        $this->baseUrl = config('services.groq.base_url', 'https://api.groq.com/openai/v1');
    }

    public function analyzeReviews(array $reviews): array
    {
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Groq API key is not configured. Please set GROQ_API_KEY in your .env file.');
        }

        if (empty($reviews)) {
            return [
                'fake_percentage'   => 0.0,
                'confidence'        => 'high',
                'explanation'       => 'No reviews to analyze',
                'fake_examples'     => [],
                'key_patterns'      => [],
                'analysis_provider' => 'Groq-'.$this->model,
            ];
        }

        LoggingService::log('Sending '.count($reviews).' reviews to Groq for aggregate analysis');

        $promptData = PromptGenerationService::generateReviewAnalysisPrompt(
            $reviews,
            'chat',
            PromptGenerationService::getProviderTextLimit('groq')
        );

        try {
            $endpoint = rtrim($this->baseUrl, '/').'/chat/completions';
            $maxTokens = $this->getOptimizedMaxTokens(count($reviews));
            $timeout = config('services.groq.timeout', 60);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout($timeout)->post($endpoint, [
                'model'    => $this->model,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an expert Amazon review authenticity analyst. Return JSON as instructed.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $promptData['user'],
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens'  => $maxTokens,
                'top_p'       => 0.1,
                'stream'      => false,
            ]);

            if ($response->successful()) {
                LoggingService::log('Groq API request successful');
                $result = $response->json();

                $finishReason = $result['choices'][0]['finish_reason'] ?? 'unknown';
                $contentLength = strlen($result['choices'][0]['message']['content'] ?? '');
                LoggingService::log("Groq Response: finish_reason={$finishReason}, content_length={$contentLength}");

                return $this->parseGroqResponse($result, $reviews);
            } else {
                $statusCode = $response->status();
                $responseBody = $response->body();

                Log::error('Groq API error', [
                    'status' => $statusCode,
                    'body'   => $responseBody,
                ]);

                throw new \Exception("Groq API error (HTTP {$statusCode})");
            }
        } catch (\Exception $e) {
            LoggingService::log('Groq service error', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to analyze reviews with Groq: '.$e->getMessage());
        }
    }

    private function parseGroqResponse($response, $reviews): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        LoggingService::log('Parsing Groq response. Content length: '.strlen($content));

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
                // This is a bit risky but can help with wordy explanations
                $cleanedJson = preg_replace_callback('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s', function ($matches) {
                    return str_replace(["\n", "\r"], ['\n', ''], $matches[0]);
                }, $jsonString);

                $result = json_decode($cleanedJson, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    LoggingService::log('Cleaned JSON decode also failed: '.json_last_error_msg());
                }
            }

            if (is_array($result) && isset($result['fake_percentage'])) {
                LoggingService::log('Groq: Successfully parsed aggregate analysis - '.$result['fake_percentage'].'% fake');

                return [
                    'fake_percentage'   => (float) $result['fake_percentage'],
                    'confidence'        => $result['confidence'] ?? 'medium',
                    'explanation'       => $result['explanation'] ?? '',
                    'fake_examples'     => $result['fake_examples'] ?? [],
                    'key_patterns'      => $result['key_patterns'] ?? [],
                    'analysis_provider' => 'Groq-'.$this->model,
                    'total_cost'        => $this->calculateCost($response),
                ];
            }
        } catch (\Exception $e) {
            LoggingService::log('Exception during Groq response parsing: '.$e->getMessage());
        }

        // Fallback for unexpected formats
        return [
            'fake_percentage'   => 0.0,
            'confidence'        => 'low',
            'explanation'       => 'Failed to parse model response.',
            'fake_examples'     => [],
            'key_patterns'      => [],
            'analysis_provider' => 'Groq-'.$this->model,
            'total_cost'        => $this->calculateCost($response),
        ];
    }

    private function calculateCost(array $response): float
    {
        $usage = $response['usage'] ?? [];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        // Groq Llama 3.3 70B: $0.59/1M input, $0.79/1M output
        return (($inputTokens / 1000000) * 0.59) + (($outputTokens / 1000000) * 0.79);
    }

    public function getOptimizedMaxTokens(int $reviewCount): int
    {
        // Aggregate analysis needs more tokens for the explanation and patterns
        // We'll use a higher base and a larger buffer
        $baseTokens = 1024;
        $perReviewTokens = $reviewCount * 20;

        return min(4096, $baseTokens + $perReviewTokens);
    }
}

