<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key') ?? '';
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
    }

    public function analyzeReviews(array $reviews): array
    {
        if (empty($this->apiKey)) {
            LoggingService::log('OpenAI API key is not configured. Skipping analysis.');

            return ['results' => []];
        }

        if (empty($reviews)) {
            return ['results' => []];
        }

        // Log the number of reviews being sent
        LoggingService::log('Sending '.count($reviews).' reviews to OpenAI for analysis');

        // OpenAI GPT-4o-mini has 128k token context window - can handle 99+ reviews without chunking
        // With format optimization, only chunk for extremely large datasets (300+ reviews)
        $parallelThreshold = config('services.openai.parallel_threshold', 300);
        if (count($reviews) > $parallelThreshold) {
            LoggingService::log('Extremely large dataset detected ('.count($reviews).' reviews), processing in parallel chunks');

            return $this->analyzeReviewsInParallelChunks($reviews);
        }

        // Use centralized prompt generation service
        $promptData = PromptGenerationService::generateReviewAnalysisPrompt(
            $reviews,
            'chat', // OpenAI uses chat format
            PromptGenerationService::getProviderTextLimit('openai')
        );

        // Log prompt size for debugging
        $promptSize = strlen($promptData['user']);
        LoggingService::log("Optimized prompt size: {$promptSize} characters");

        try {
            // Extract the endpoint from base_url if it includes the full path
            $endpoint = $this->baseUrl;
            if (!str_ends_with($endpoint, '/chat/completions')) {
                $endpoint = rtrim($endpoint, '/').'/chat/completions';
            }

            LoggingService::log("Making OpenAI API request to: {$endpoint}");

            // Use optimized parameters for faster processing
            $maxTokens = $this->getOptimizedMaxTokens(count($reviews));
            $timeout = config('services.openai.timeout', 120);
            LoggingService::log("Using optimized max_tokens: {$maxTokens} for {$this->model} with ".count($reviews)." reviews, timeout: {$timeout}s");

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type'  => 'application/json',
                'User-Agent'    => 'ReviewAnalyzer/1.0',
            ])->timeout($timeout)->connectTimeout(30)->retry(2, 1000)->post($endpoint, [
                'model'    => $this->model,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => PromptGenerationService::getProviderSystemMessage('openai'),
                    ],
                    [
                        'role'    => 'user',
                        'content' => $promptData['user'],
                    ],
                ],
                'temperature' => 0.0, // Deterministic for consistency and speed
                'max_tokens'  => $maxTokens,
                'top_p'       => 0.1, // More focused responses
            ]);

            if ($response->successful()) {
                LoggingService::log('OpenAI API request successful');
                $result = $response->json();

                return $this->parseOpenAIResponse($result, $reviews);
            } else {
                $statusCode = $response->status();
                $responseBody = $response->body();

                Log::error('OpenAI API error', [
                    'status' => $statusCode,
                    'body'   => $responseBody,
                ]);

                // Send alerts for specific error types
                if ($statusCode === 429) {
                    $errorData = json_decode($responseBody, true);
                    $errorMessage = $errorData['error']['message'] ?? 'Rate limit exceeded';

                    if (str_contains($errorMessage, 'quota')) {
                        app(AlertManager::class)->recordFailure(
                            'OpenAI Service',
                            'QUOTA_EXCEEDED',
                            $errorMessage,
                            [
                                'status_code'   => $statusCode,
                                'response_body' => substr($responseBody, 0, 500),
                            ]
                        );
                    }
                } elseif ($statusCode >= 500) {
                    $errorMessage = $this->getErrorMessage($statusCode, $responseBody);
                    app(AlertManager::class)->recordFailure(
                        'OpenAI Service',
                        'API_ERROR',
                        $errorMessage,
                        [
                            'status_code'   => $statusCode,
                            'response_body' => substr($responseBody, 0, 500),
                        ]
                    );
                }

                // Handle specific error types with user-friendly messages
                $errorMessage = $this->getErrorMessage($statusCode, $responseBody);

                throw new \Exception($errorMessage);
            }
        } catch (\Exception $e) {
            LoggingService::log('OpenAI service error', ['error' => $e->getMessage()]);

            // Check for quota exceeded errors in HTTP exceptions
            if (str_contains($e->getMessage(), 'HTTP request returned status code 429')) {
                $errorMessage = $e->getMessage();
                if (str_contains($errorMessage, 'quota')) {
                    app(AlertManager::class)->recordFailure(
                        'OpenAI Service',
                        'QUOTA_EXCEEDED',
                        $errorMessage,
                        [
                            'status_code' => 429,
                            'error_type'  => 'quota_exceeded',
                            'source'      => 'http_exception',
                        ],
                        $e
                    );
                }
            }

            // Check if it's a timeout with 0 bytes - this suggests connection issues
            if (str_contains($e->getMessage(), 'cURL error 28') && str_contains($e->getMessage(), '0 bytes received')) {
                throw new \Exception('Unable to connect to OpenAI service. Please check your internet connection and try again.');
            }

            // Don't re-wrap already formatted error messages
            if (str_contains($e->getMessage(), 'quota') ||
                str_contains($e->getMessage(), 'rate limit') ||
                str_contains($e->getMessage(), 'temporarily unavailable')) {
                throw $e;
            }

            throw new \Exception('Failed to analyze reviews: '.$e->getMessage());
        }
    }

    /**
     * Process reviews in parallel chunks for better performance with large datasets.
     */
    private function analyzeReviewsInParallelChunks(array $reviews): array
    {
        $chunkSize = config('services.openai.chunk_size', 25);
        $chunks = array_chunk($reviews, $chunkSize);
        $allDetailedScores = [];

        LoggingService::log('Processing '.count($chunks).' chunks in parallel for '.count($reviews).' reviews');

        // Process chunks concurrently using Laravel's HTTP pool
        $responses = Http::pool(function ($pool) use ($chunks) {
            $endpoint = $this->baseUrl;
            if (!str_ends_with($endpoint, '/chat/completions')) {
                $endpoint = rtrim($endpoint, '/').'/chat/completions';
            }

            foreach ($chunks as $index => $chunk) {
                $promptData = PromptGenerationService::generateReviewAnalysisPrompt(
                    $chunk,
                    'chat',
                    PromptGenerationService::getProviderTextLimit('openai')
                );
                $maxTokens = $this->getOptimizedMaxTokens(count($chunk));

                $pool->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'ReviewAnalyzer/1.0',
                ])->timeout(60)->connectTimeout(20)->post($endpoint, [
                    'model'    => $this->model,
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => PromptGenerationService::getProviderSystemMessage('openai'),
                        ],
                        [
                            'role'    => 'user',
                            'content' => $promptData['user'],
                        ],
                    ],
                    'temperature' => 0.0,
                    'max_tokens'  => $maxTokens,
                    'top_p'       => 0.1,
                ]);
            }
        });

        // Process all responses
        foreach ($responses as $chunkIndex => $response) {
            $chunk = $chunks[$chunkIndex];

            if ($response->successful()) {
                $result = $response->json();
                $chunkResult = $this->parseOpenAIResponse($result, $chunk);

                if (isset($chunkResult['detailed_scores'])) {
                    $chunkScores = $chunkResult['detailed_scores'];
                    $allDetailedScores = array_merge($allDetailedScores, $chunkScores);
                    LoggingService::log('Successfully processed chunk '.($chunkIndex + 1).' ('.count($chunk).' reviews) with '.count($chunkScores).' scores');
                } else {
                    LoggingService::log('Chunk '.($chunkIndex + 1).' returned no scores');
                }
            } else {
                LoggingService::log('Error processing chunk '.($chunkIndex + 1).': HTTP '.$response->status());
                // Continue with other chunks even if one fails
            }
        }

        LoggingService::log('Parallel processing completed with '.count($allDetailedScores).' total scores');

        return ['detailed_scores' => $allDetailedScores];
    }

    private function analyzeReviewsInChunks(array $reviews): array
    {
        $chunkSize = config('services.openai.chunk_size', 25);
        $chunks = array_chunk($reviews, $chunkSize);
        $allDetailedScores = [];

        foreach ($chunks as $index => $chunk) {
            LoggingService::log('Processing chunk '.($index + 1).' of '.count($chunks).' ('.count($chunk).' reviews)');

            try {
                $chunkResult = $this->analyzeReviews($chunk);

                if (isset($chunkResult['detailed_scores'])) {
                    $allDetailedScores = array_merge($allDetailedScores, $chunkResult['detailed_scores']);
                }

                // Small delay between chunks to avoid rate limiting
                usleep(200000); // Reduced to 0.2 seconds
            } catch (\Exception $e) {
                LoggingService::log('Error processing chunk '.($index + 1).': '.$e->getMessage());
                // Continue with other chunks even if one fails
            }
        }

        return ['detailed_scores' => $allDetailedScores];
    }

    /**
     * Build an optimized prompt that uses fewer tokens while maintaining accuracy.
     *
     * This prompt is designed to be BALANCED - recognizing genuine reviews as the norm.
     */
    private function buildOptimizedPrompt($reviews): string
    {
        $prompt = "Score each review 0-100 (0=genuine, 100=fake). Be ACCURATE and BALANCED. Most reviews are genuine. Return JSON: [{\"id\":\"X\",\"score\":Y}]\n\n";
        $prompt .= "CLEARLY GENUINE (0-25): Verified purchase, specific personal experience, detailed product knowledge, mentions pros AND cons, natural language with personal context\n";
        $prompt .= "LIKELY GENUINE (26-45): Some specific details, reasonable personal context, verified purchase, minor generic elements but overall authentic feel\n";
        $prompt .= "UNCERTAIN (46-60): Mixed signals, insufficient information to determine authenticity, could be genuine but lacks strong indicators\n";
        $prompt .= "SUSPICIOUS (61-80): Multiple concerning patterns: generic praise without specifics, promotional language, no personal context, unverified with marketing-style writing\n";
        $prompt .= "LIKELY FAKE (81-100): Clear manipulation: obvious promotional copy, repetitive phrases matching other reviews, no genuine experience indicators, AI-generated patterns\n\n";
        $prompt .= "IMPORTANT: High ratings for quality products are NORMAL. Default to genuine when uncertain.\n";
        $prompt .= "Key: V=Verified (strong genuine signal), U=Unverified (minor concern), Vine=Amazon Vine reviewer\n\n";

        foreach ($reviews as $review) {
            $verified = isset($review['meta_data']['verified_purchase']) && $review['meta_data']['verified_purchase'] ? 'V' : 'U';
            $vine = isset($review['meta_data']['is_vine_voice']) && $review['meta_data']['is_vine_voice'] ? 'Vine' : '';

            // Handle both old and new review data structures
            // New structure (bandwidth optimized): has 'text' field but no 'review_title'
            // Old structure: has 'review_title' and 'review_text' fields
            $title = '';
            $text = '';

            // Try to get title (optional - bandwidth optimization removed this)
            if (isset($review['review_title'])) {
                $title = $this->cleanUtf8Text(substr($review['review_title'], 0, 100));
            } else {
                // No title available in bandwidth-optimized structure - use first part of review text
                $reviewText = $review['text'] ?? $review['review_text'] ?? '';
                if (!empty($reviewText)) {
                    $title = $this->cleanUtf8Text(substr($reviewText, 0, 50).'...');
                }
            }

            // Get review text - try multiple field names for compatibility
            if (isset($review['review_text'])) {
                $text = $this->cleanUtf8Text(substr($review['review_text'], 0, 400));
            } elseif (isset($review['text'])) {
                $text = $this->cleanUtf8Text(substr($review['text'], 0, 400));
            }

            $prompt .= "ID:{$review['id']} {$review['rating']}/5 {$verified}{$vine}\n";
            $prompt .= "T: {$title}\n";
            $prompt .= "R: {$text}\n\n";
        }

        return $prompt;
    }

    /**
     * Clean text to ensure valid UTF-8 encoding for JSON serialization.
     */
    private function cleanUtf8Text(string $text): string
    {
        // Remove or replace invalid UTF-8 sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Remove null bytes and other problematic characters
        $text = str_replace(["\0", "\x1A"], '', $text);

        // Ensure string is valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        return trim($text);
    }

    /**
     * Get optimized max_tokens based on number of reviews for faster processing.
     */
    public function getOptimizedMaxTokens(int $reviewCount): int
    {
        // GPT-4o-mini needs more tokens than GPT-4-turbo for the same output
        // Calculate based on expected output size: roughly 25-30 chars per review for JSON response
        $baseTokens = $reviewCount * 12; // Increased from 8 to 12 for GPT-4o-mini

        // Add larger buffer for GPT-4o-mini to prevent truncation
        $buffer = min(1000, $reviewCount * 5); // Increased buffer

        return $baseTokens + $buffer;
    }

    /**
     * Calculate the cost of the OpenAI API request.
     */
    public function calculateCost(array $response): float
    {
        $usage = $response['usage'] ?? [];
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        // GPT-4o-mini pricing (as of 2024): $0.15/1M input, $0.60/1M output
        return (($inputTokens / 1000000) * 0.15) + (($outputTokens / 1000000) * 0.60);
    }

    private function buildPrompt($reviews): string
    {
        // Fallback to old method if needed
        return $this->buildOptimizedPrompt($reviews);
    }

    private function parseOpenAIResponse($response, $reviews): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';

        LoggingService::log('Raw OpenAI response content: '.json_encode(['content' => substr($content, 0, 200).'...']));

        // Clean the content to handle markdown formatting
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        // Try to extract JSON from the content
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $jsonString = $matches[0];

            if ($jsonString) {
                LoggingService::log('Extracted JSON string: '.json_encode(['json' => substr($jsonString, 0, 100).'...']));

                try {
                    // Try to parse the complete JSON first
                    $results = json_decode($jsonString, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($results)) {
                        $detailedScores = [];
                        foreach ($results as $result) {
                            if (isset($result['id']) && isset($result['score'])) {
                                $detailedScores[$result['id']] = (int) $result['score'];
                            }
                        }

                        LoggingService::log('Successfully parsed complete JSON with '.count($detailedScores).' scores');

                        return [
                            'detailed_scores' => $detailedScores,
                            'total_cost'      => $this->calculateCost($response),
                        ];
                    }
                } catch (\Exception $e) {
                    LoggingService::log('Failed to parse complete JSON: '.$e->getMessage());
                }

                // If complete JSON parsing fails, try to parse partial JSON
                LoggingService::log('Attempting to parse partial JSON response');

                $partialResult = $this->parsePartialJsonResponse($jsonString, $reviews);
                $partialResult['total_cost'] = $this->calculateCost($response);

                return $partialResult;
            }
        }

        LoggingService::log('Failed to parse OpenAI response, using fallback');

        // Fallback: return empty detailed scores
        return [
            'detailed_scores' => [],
            'total_cost'      => $this->calculateCost($response),
        ];
    }

    /**
     * Parse partial JSON responses that may be truncated due to max_tokens limit.
     */
    private function parsePartialJsonResponse(string $jsonString, array $reviews): array
    {
        $detailedScores = [];

        // Fix common truncation issues
        $jsonString = rtrim($jsonString, ',');

        // If the JSON doesn't end with ], try to close it
        if (!str_ends_with(trim($jsonString), ']')) {
            $jsonString = rtrim($jsonString, ',').']';
        }

        // Try parsing the fixed JSON
        try {
            $results = json_decode($jsonString, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($results)) {
                foreach ($results as $result) {
                    if (isset($result['id']) && isset($result['score'])) {
                        $detailedScores[$result['id']] = (int) $result['score'];
                    }
                }

                LoggingService::log('Successfully parsed partial JSON with '.count($detailedScores).' scores');

                return ['detailed_scores' => $detailedScores];
            }
        } catch (\Exception $e) {
            LoggingService::log('Failed to parse partial JSON: '.$e->getMessage());
        }

        // If still failing, try regex parsing individual entries
        preg_match_all('/\{"id":"([^"]+)","score":(\d+)\}/', $jsonString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $detailedScores[$match[1]] = (int) $match[2];
        }

        LoggingService::log('Regex parsing extracted '.count($detailedScores).' scores from partial response');

        return ['detailed_scores' => $detailedScores];
    }

    private function getMaxTokensForModel(string $model): int
    {
        // Map models to their max completion tokens
        $modelLimits = [
            'gpt-4'                  => 4096,
            'gpt-4-0613'             => 4096,
            'gpt-4-32k'              => 32768,
            'gpt-4-32k-0613'         => 32768,
            'gpt-4-turbo'            => 4096,
            'gpt-4-turbo-preview'    => 4096,
            'gpt-4-1106-preview'     => 4096,
            'gpt-4-0125-preview'     => 4096,
            'gpt-3.5-turbo'          => 4096,
            'gpt-3.5-turbo-16k'      => 16384,
            'gpt-3.5-turbo-0613'     => 4096,
            'gpt-3.5-turbo-16k-0613' => 16384,
        ];

        return $modelLimits[$model] ?? 4096; // Default to 4096 if model not found
    }

    /**
     * Get user-friendly error message based on API response.
     */
    private function getErrorMessage(int $statusCode, string $responseBody): string
    {
        // Try to parse error details from response
        $errorDetails = json_decode($responseBody, true);
        $errorMessage = $errorDetails['error']['message'] ?? '';

        switch ($statusCode) {
            case 429:
                if (str_contains($errorMessage, 'quota')) {
                    return 'OpenAI quota exceeded. Please check your billing and usage limits at https://platform.openai.com/usage';
                }

                return 'OpenAI rate limit exceeded. Please try again in a few moments.';

            case 401:
                return 'OpenAI authentication failed. Please check your API key configuration.';

            case 400:
                return 'Invalid request to OpenAI API. Please contact support if this persists.';

            case 503:
                return 'OpenAI service is temporarily unavailable. Please try again later.';

            case 500:
            case 502:
            case 504:
                return 'OpenAI service error. Please try again in a few moments.';

            default:
                return "OpenAI API error (HTTP {$statusCode}). Please try again or contact support if this persists.";
        }
    }

    private function generateExplanation(float $score): string
    {
        if ($score >= 80) {
            return 'Likely inauthentic: Clear manipulation patterns detected';
        } elseif ($score >= 60) {
            return 'Suspicious: Multiple concerning indicators present';
        } elseif ($score >= 45) {
            return 'Uncertain: Mixed signals, insufficient authenticity indicators';
        } elseif ($score >= 25) {
            return 'Likely genuine: Some authentic signals present';
        } else {
            return 'Genuine: Strong authenticity indicators - personal context, specific details, balanced perspective';
        }
    }

    private function calculateConfidence(float $score): string
    {
        if ($score >= 80 || $score <= 20) {
            return 'high';
        } elseif ($score >= 60 || $score <= 40) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
