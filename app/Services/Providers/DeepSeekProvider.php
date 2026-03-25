<?php

namespace App\Services\Providers;

use App\Services\ContextAwareChunkingService;
use App\Services\LLMProviderInterface;
use App\Services\LoggingService;
use App\Services\PromptGenerationService;
use Illuminate\Support\Facades\Http;

class DeepSeekProvider implements LLMProviderInterface
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
            return ['results' => []];
        }

        LoggingService::log('Sending '.count($reviews).' reviews to DeepSeek for analysis');

        // DeepSeek has max_tokens limit of 8192, so we need to chunk for large review sets
        // With aggregate format, chunk when we'd exceed token limits (around 80-100 reviews)
        $reviewCount = count($reviews);
        $chunkingThreshold = 80; // Reduced to handle DeepSeek's max_tokens limit

        if ($reviewCount > $chunkingThreshold) {
            LoggingService::log("Extremely large review set detected ({$reviewCount} reviews > {$chunkingThreshold}), using chunking for DeepSeek");

            return $this->analyzeReviewsInChunks($reviews);
        }

        // Use centralized prompt generation service
        $promptData = PromptGenerationService::generateReviewAnalysisPrompt(
            $reviews,
            'chat', // DeepSeek uses chat format
            PromptGenerationService::getProviderTextLimit('deepseek')
        );

        try {
            $endpoint = rtrim($this->baseUrl, '/').'/chat/completions';
            $maxTokens = $this->getOptimizedMaxTokens(count($reviews));

            LoggingService::log("Making DeepSeek API request to: {$endpoint}");

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'ReviewAnalyzer-DeepSeek/1.0',
            ])->timeout(120)->post($endpoint, [
                'model'    => $this->model,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => PromptGenerationService::getProviderSystemMessage('deepseek'),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $promptData['user'],
                    ],
                ],
                'temperature' => 0.0,
                'max_tokens'  => $maxTokens,
            ]);

            if ($response->successful()) {
                LoggingService::log('DeepSeek API request successful');
                $result = $response->json();

                return $this->parseResponse($result, $reviews);
            } else {
                throw new \Exception('DeepSeek API error: '.$response->status().' - '.$response->body());
            }
        } catch (\Exception $e) {
            LoggingService::log('DeepSeek analysis failed: '.$e->getMessage());

            throw $e;
        }
    }

    public function getOptimizedMaxTokens(int $reviewCount): int
    {
        // DeepSeek has a max_tokens limit of 8192
        $maxAllowed = 8192;

        // For aggregate responses with detailed explanations, we need more tokens
        // Especially for chunked analysis where explanations need to be comprehensive
        $baseTokens = min(3000, $reviewCount * 15); // Increased base for detailed explanations
        $buffer = min(2000, $reviewCount * 8); // Larger buffer for complex patterns

        // Minimum 2500 tokens for aggregate responses with detailed explanations
        $minTokens = 2500;

        $calculated = max($minTokens, $baseTokens + $buffer);

        // Ensure we never exceed DeepSeek's limit
        return min($calculated, $maxAllowed);
    }

    /**
     * Process large review sets using centralized context-aware chunking.
     */
    private function analyzeReviewsInChunks(array $reviews): array
    {
        $chunkingService = app(ContextAwareChunkingService::class);

        return $chunkingService->processWithContextAwareChunking(
            $reviews,
            50, // Optimal chunk size for DeepSeek
            [$this, 'processChunkWithContext'],
            [
                'delay_ms'         => 500, // Rate limiting delay
                'max_failure_rate' => 0.5,
                'provider_name'    => 'DeepSeek',
            ]
        );
    }

    /**
     * Process a single chunk with global context awareness.
     */
    public function processChunkWithContext(array $chunk, array $context): array
    {
        // Generate context-aware prompt
        $promptData = PromptGenerationService::generateReviewAnalysisPrompt(
            $chunk,
            'chat',
            PromptGenerationService::getProviderTextLimit('deepseek')
        );

        // Add global context to the prompt
        $contextHeader = app(ContextAwareChunkingService::class)->generateContextHeader($context);
        $promptData['user'] = $contextHeader."\n\n".$promptData['user'];

        try {
            $endpoint = rtrim($this->baseUrl, '/').'/chat/completions';
            $maxTokens = $this->getOptimizedMaxTokens(count($chunk));

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'ReviewAnalyzer-DeepSeek/1.0',
            ])->timeout(120)->post($endpoint, [
                'model'    => $this->model,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => PromptGenerationService::getProviderSystemMessage('deepseek'),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $promptData['user'],
                    ],
                ],
                'temperature' => 0.0,
                'max_tokens'  => $maxTokens,
            ]);

            if ($response->successful()) {
                $result = $response->json();

                return $this->parseResponse($result, $chunk);
            } else {
                throw new \Exception('DeepSeek API error: '.$response->body());
            }
        } catch (\Exception $e) {
            LoggingService::log('DeepSeek chunk processing failed: '.$e->getMessage());

            throw $e;
        }
    }

    public function isAvailable(): bool
    {
        try {
            if (empty($this->apiKey) && !$this->isLocalDeployment()) {
                return false;
            }

            // Quick health check
            $endpoint = rtrim($this->baseUrl, '/').'/models';
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
            ])->timeout(10)->get($endpoint);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getProviderName(): string
    {
        $deployment = $this->isLocalDeployment() ? 'Self-Hosted' : 'API';

        return "DeepSeek-{$deployment}-{$this->model}";
    }

    public function getEstimatedCost(int $reviewCount): float
    {
        if ($this->isLocalDeployment()) {
            // Self-hosted costs are infrastructure-based, not per-token
            return 0.0;
        }

        // DeepSeek API pricing (significantly cheaper than OpenAI)
        $avgInputTokens = $reviewCount * 50;
        $avgOutputTokens = $reviewCount * 8;

        $inputCost = ($avgInputTokens / 1000000) * 0.27;  // $0.27 per 1M tokens
        $outputCost = ($avgOutputTokens / 1000000) * 1.10; // $1.10 per 1M tokens

        return $inputCost + $outputCost;
    }

    private function isLocalDeployment(): bool
    {
        return str_contains($this->baseUrl, 'localhost') ||
               str_contains($this->baseUrl, '127.0.0.1') ||
               str_contains($this->baseUrl, '192.168.') ||
               str_contains($this->baseUrl, '10.0.');
    }

    private function parseResponse($response, $reviews): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        // Debug: Log the actual response content for troubleshooting
        LoggingService::log('DeepSeek raw response content: '.substr($content, 0, 1000).(strlen($content) > 1000 ? '...' : ''));
        LoggingService::log('DeepSeek response length: '.strlen($content).' characters');

        // Parse aggregate JSON response
        try {
            // Try direct JSON decode first
            $result = json_decode($content, true);

            // If direct decode fails, try extracting JSON from markdown or wrapped content
            if (!is_array($result)) {
                // Try extracting JSON from markdown code blocks (most common case)
                if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
                    $result = json_decode($matches[1], true);
                }
                // Handle truncated responses - try to extract partial JSON and fix it
                elseif (preg_match('/```(?:json)?\s*(\{.*)/s', $content, $matches)) {
                    $partialJson = $matches[1];
                    // If it ends incomplete, try to close it properly
                    if (!str_ends_with(trim($partialJson), '}')) {
                        $partialJson = rtrim($partialJson, ',').'}';
                    }
                    $result = json_decode($partialJson, true);
                    if (is_array($result)) {
                        LoggingService::log('DeepSeek: Recovered aggregate data from truncated response');
                    }
                }
                // Try extracting JSON object from anywhere in the content
                elseif (preg_match('/(\{(?:[^{}]+|(?1))*\})/', $content, $matches)) {
                    $result = json_decode($matches[1], true);
                }
            }

            if (!is_array($result)) {
                throw new \Exception('Invalid JSON response format - expected object, got: '.gettype($result));
            }

            // Validate required fields
            if (!isset($result['fake_percentage']) || !isset($result['confidence']) || !isset($result['explanation'])) {
                throw new \Exception('Invalid response format - missing required fields (fake_percentage, confidence, explanation)');
            }

            LoggingService::log('DeepSeek: Successfully parsed aggregate analysis - '.$result['fake_percentage'].'% fake, confidence: '.$result['confidence']);

            return [
                'fake_percentage'   => (float) $result['fake_percentage'],
                'confidence'        => $result['confidence'],
                'explanation'       => $result['explanation'],
                'fake_examples'     => $result['fake_examples'] ?? [],
                'key_patterns'      => $result['key_patterns'] ?? [],
                'analysis_provider' => 'DeepSeek-API-'.$this->model,
                'total_cost'        => $this->calculateCost($response),
            ];
        } catch (\Exception $e) {
            LoggingService::log('Failed to parse DeepSeek response: '.$e->getMessage());

            throw new \Exception('Failed to parse DeepSeek response');
        }
    }

    /**
     * Calculate the real-time cost of the DeepSeek API request.
     */
    public function calculateCost(array $response): float
    {
        if ($this->isLocalDeployment()) {
            return 0.0;
        }

        $usage = $response['usage'] ?? [];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        // DeepSeek API pricing (as of 2024): $0.27 per 1M input, $1.10 per 1M output
        return (($inputTokens / 1000000) * 0.27) + (($outputTokens / 1000000) * 1.10);
    }

    private function formatAnalysisResults(array $decoded): array
    {
        $results = [];
        foreach ($decoded as $item) {
            if (isset($item['id']) && isset($item['score'])) {
                $score = max(0, min(100, (int) $item['score'])); // Clamp to 0-100

                // Support new research-based format with additional metadata
                $label = $item['label'] ?? $this->generateLabel($score);
                $confidence = isset($item['confidence']) ? (float) $item['confidence'] : $this->calculateConfidenceFromScore($score);

                $results[$item['id']] = [
                    'score'       => $score,
                    'label'       => $label,
                    'confidence'  => $confidence,
                    'explanation' => $this->generateExplanationFromLabel($label, $score),
                ];
            }
        }

        return [
            'detailed_scores'   => $results,
            'analysis_provider' => $this->getProviderName(),
            'total_cost'        => $this->getEstimatedCost(count($decoded)),
        ];
    }

    private function generateLabel(int $score): string
    {
        if ($score >= 80) {
            return 'fake';
        } elseif ($score >= 60) {
            return 'suspicious';
        } elseif ($score >= 45) {
            return 'uncertain';
        } elseif ($score >= 25) {
            return 'likely_genuine';
        } else {
            return 'genuine';
        }
    }

    private function calculateConfidenceFromScore(int $score): float
    {
        // Higher confidence for extreme scores, lower for middle range
        if ($score >= 90 || $score <= 10) {
            return 0.95;
        } elseif ($score >= 80 || $score <= 20) {
            return 0.85;
        } elseif ($score >= 70 || $score <= 30) {
            return 0.75;
        } elseif ($score >= 60 || $score <= 40) {
            return 0.65;
        } else {
            return 0.55; // Lowest confidence for uncertain middle range
        }
    }

    private function generateExplanationFromLabel(string $label, int $score): string
    {
        switch ($label) {
            case 'fake':
                return $score >= 90 ? 'Likely inauthentic: Clear manipulation patterns detected' : 'Suspicious: Multiple concerning indicators present';
            case 'suspicious':
                return 'Concerning patterns: Some indicators suggest potential manipulation';
            case 'uncertain':
                return 'Mixed signals: Insufficient information to determine authenticity';
            case 'likely_genuine':
                return 'Likely authentic: Some genuine signals present';
            case 'genuine':
            default:
                return $score <= 15 ? 'Highly authentic: Strong genuine indicators - detailed experience, personal context' : 'Genuine: Natural language with specific details';
        }
    }
}
