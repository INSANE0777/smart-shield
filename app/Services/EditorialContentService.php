<?php

namespace App\Services;

use App\Models\AsinData;
use Illuminate\Support\Facades\Http;

/**
 * Service for generating editorial content using AI.
 *
 * This service generates unique, high-quality editorial content for product
 * analysis pages to satisfy Google AdSense content quality requirements.
 * The content includes:
 * - Expert buyer's guide insights
 * - Product category context
 * - Key considerations for purchase decisions
 * - Quality and authenticity guidance
 *
 * Respects LLM_PRIMARY_PROVIDER configuration from .env
 */
class EditorialContentService
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    private string $provider;

    public function __construct()
    {
        // Use the configured primary LLM provider (respects LLM_PRIMARY_PROVIDER)
        $this->provider = config('services.llm.primary_provider', 'openai');
        $this->initializeProvider();
    }

    /**
     * Initialize API credentials based on configured provider.
     */
    private function initializeProvider(): void
    {
        switch ($this->provider) {
            case 'deepseek':
                $this->apiKey = config('services.deepseek.api_key') ?? '';
                $this->model = config('services.deepseek.model', 'deepseek-chat');
                $this->baseUrl = config('services.deepseek.base_url', 'https://api.deepseek.com/v1');
                break;

            case 'ollama':
                $this->apiKey = ''; // Ollama doesn't need API key
                $this->model = config('services.ollama.model', 'phi4:14b');
                $this->baseUrl = config('services.ollama.base_url', 'http://localhost:11434');
                break;

            case 'openai':
            default:
                $this->apiKey = config('services.openai.api_key') ?? '';
                $this->model = config('services.openai.model', 'gpt-4o-mini');
                $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
                break;
        }
    }

    /**
     * Generate editorial content for a product.
     *
     * @param AsinData $asinData The product to generate content for
     *
     * @throws \Exception If generation fails
     *
     * @return array The editorial content results
     */
    public function generateEditorialContent(AsinData $asinData): array
    {
        // Validate that we have enough product data first
        if (empty($asinData->product_title)) {
            throw new \InvalidArgumentException('Product title is required for editorial content generation.');
        }

        // Validate API key for providers that require it (skip in testing with HTTP fakes)
        if (!$this->isAvailable() && !app()->environment('testing')) {
            throw new \InvalidArgumentException('LLM API key is not configured for editorial content generation.');
        }

        LoggingService::log('Starting editorial content generation', [
            'asin'     => $asinData->asin,
            'country'  => $asinData->country,
            'title'    => substr($asinData->product_title, 0, 50),
            'provider' => $this->provider,
        ]);

        // Mark as processing
        $asinData->update(['editorial_status' => 'processing']);

        try {
            $prompt = $this->buildEditorialPrompt($asinData);
            $result = $this->callLLM($prompt);

            // Parse and validate the response
            $contentData = $this->parseResponse($result);

            // Save the editorial content
            $asinData->update([
                'editorial_content'      => $contentData,
                'editorial_status'       => 'completed',
                'editorial_generated_at' => now(),
            ]);

            LoggingService::log('Editorial content generation completed', [
                'asin' => $asinData->asin,
            ]);

            return $contentData;
        } catch (\Exception $e) {
            // Mark as failed but don't break the main flow
            $asinData->update([
                'editorial_status' => 'failed',
            ]);

            LoggingService::log('Editorial content generation failed', [
                'asin'  => $asinData->asin,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the AI prompt for editorial content generation.
     * Designed to produce unique, substantive content that satisfies AdSense quality requirements.
     */
    private function buildEditorialPrompt(AsinData $asinData): string
    {
        $country = $this->getCountryName($asinData->country);
        $productTitle = $asinData->product_title;
        $productDescription = $asinData->product_description ?? '';
        $category = $asinData->category ?? 'General Products';
        $categoryPath = $asinData->category_path ?? '';
        $grade = $asinData->grade ?? 'N/A';
        $fakePercentage = $asinData->fake_percentage ?? 0;
        $amazonRating = $asinData->amazon_rating ?? 'N/A';
        $adjustedRating = $asinData->adjusted_rating ?? 'N/A';
        $reviewCount = $asinData->total_reviews_on_amazon ?? count($asinData->getReviewsArray());

        // Truncate description to save tokens but keep enough context
        if (strlen($productDescription) > 400) {
            $productDescription = substr($productDescription, 0, 400).'...';
        }

        // Get review highlights if available for context
        $reviewContext = $this->extractReviewContext($asinData);

        return <<<PROMPT
You are an expert consumer advisor writing editorial content for a product analysis page. Your content must be:
1. UNIQUE and ORIGINAL - not generic text that could apply to any product
2. SPECIFIC to this exact product and its characteristics
3. HELPFUL for consumers making purchasing decisions
4. SUBSTANTIVE with actionable insights (not vague platitudes)

PRODUCT INFORMATION:
- Title: {$productTitle}
- Description: {$productDescription}
- Category: {$categoryPath}
- Country: {$country}
- Amazon Rating: {$amazonRating}/5 ({$reviewCount} reviews)
- Adjusted Rating (excluding suspicious reviews): {$adjustedRating}/5
- Review Authenticity Grade: {$grade}
- Estimated Fake Review Percentage: {$fakePercentage}%
{$reviewContext}

Generate editorial content in this EXACT JSON structure:

{
  "buyers_guide": {
    "headline": "<Compelling 8-12 word headline specific to this product type>",
    "introduction": "<2-3 sentences introducing what buyers should know about this specific product category. Reference the actual product characteristics.>",
    "key_considerations": [
      "<Specific consideration #1 relevant to this product type - 1-2 sentences>",
      "<Specific consideration #2 relevant to this product type - 1-2 sentences>",
      "<Specific consideration #3 relevant to this product type - 1-2 sentences>"
    ],
    "what_to_look_for": "<2-3 sentences about quality indicators specific to this product category>"
  },
  "category_context": {
    "market_overview": "<2-3 sentences about the current state of this product category/market>",
    "common_issues": "<2-3 sentences about common problems or concerns in this product category>",
    "quality_indicators": "<2-3 sentences about how to identify quality products in this category>"
  },
  "authenticity_insights": {
    "grade_interpretation": "<2-3 sentences explaining what the Grade {$grade} and {$fakePercentage}% fake rate means for this specific product>",
    "trust_recommendation": "<2-3 sentences with specific advice based on the authenticity analysis>",
    "review_reading_tips": "<2-3 sentences advising how to read reviews for this type of product>"
  },
  "expert_perspective": {
    "overall_assessment": "<3-4 sentences providing expert perspective on this product based on the analysis data>",
    "purchase_considerations": "<2-3 sentences about factors to weigh when deciding whether to purchase>",
    "alternatives_note": "<1-2 sentences noting that shoppers should compare with similar products>"
  },
  "content_meta": {
    "word_count": <estimated total word count of all content>,
    "expertise_signals": ["<list 2-3 expertise indicators used in the content, e.g., 'category-specific terminology', 'market context'>"]
  }
}

CRITICAL REQUIREMENTS:
- Content MUST be specific to "{$productTitle}" - avoid generic statements
- Include specific details that show expertise in the {$category} category
- Reference the actual analysis data (grade, fake percentage, ratings) meaningfully
- Write in authoritative but accessible tone
- Each section should provide VALUE, not filler content
- Total content should be 400-600 words across all sections
PROMPT;
    }

    /**
     * Extract relevant context from reviews for the prompt.
     */
    private function extractReviewContext(AsinData $asinData): string
    {
        $reviews = $asinData->getReviewsArray();
        if (empty($reviews)) {
            return '';
        }

        // Get a sample of review themes if explanation exists
        $explanation = $asinData->explanation ?? '';
        if (!empty($explanation)) {
            // Truncate explanation for context
            $truncatedExplanation = strlen($explanation) > 300
                ? substr($explanation, 0, 300).'...'
                : $explanation;

            return "\n- Analysis Summary: {$truncatedExplanation}";
        }

        return '';
    }

    /**
     * Call the configured LLM provider with the prompt.
     */
    private function callLLM(string $prompt): string
    {
        if ($this->provider === 'ollama') {
            return $this->callOllama($prompt);
        }

        return $this->callChatCompletionsAPI($prompt);
    }

    /**
     * Call OpenAI-compatible chat completions API (OpenAI, DeepSeek).
     */
    private function callChatCompletionsAPI(string $prompt): string
    {
        $endpoint = $this->baseUrl;
        if (!str_ends_with($endpoint, '/chat/completions')) {
            $endpoint = rtrim($endpoint, '/').'/chat/completions';
        }

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($this->apiKey)) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        $response = Http::withHeaders($headers)->timeout(90)->post($endpoint, [
            'model'    => $this->model,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'You are an expert consumer advisor and editorial content writer. You create unique, substantive content that helps consumers make informed purchasing decisions. Your writing demonstrates expertise in product categories and provides actionable insights. Always respond with valid JSON.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.7, // Slightly higher for more varied, unique content
            'max_tokens'  => 1500, // More tokens for substantial content
        ]);

        if (!$response->successful()) {
            throw new \Exception('Editorial content API request failed: '.$response->status());
        }

        return $response->json('choices.0.message.content', '');
    }

    /**
     * Call Ollama API for local LLM processing.
     */
    private function callOllama(string $prompt): string
    {
        $endpoint = rtrim($this->baseUrl, '/').'/api/generate';

        $systemPrompt = 'You are an expert consumer advisor and editorial content writer. You create unique, substantive content that helps consumers make informed purchasing decisions. Your writing demonstrates expertise in product categories and provides actionable insights. Always respond with valid JSON.';

        $response = Http::timeout(180)->post($endpoint, [
            'model'   => $this->model,
            'prompt'  => $systemPrompt."\n\n".$prompt,
            'stream'  => false,
            'options' => [
                'temperature' => 0.7,
                'num_predict' => 1500,
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Editorial content API request failed: '.$response->status());
        }

        return $response->json('response', '');
    }

    /**
     * Parse the AI response into structured data.
     */
    private function parseResponse(string $content): array
    {
        // Clean markdown formatting if present
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            LoggingService::log('Editorial content JSON parse error', [
                'error'   => json_last_error_msg(),
                'content' => substr($content, 0, 300),
            ]);

            // Return a fallback structure
            return $this->getFallbackContent();
        }

        // Validate required sections exist
        $requiredSections = ['buyers_guide', 'category_context', 'authenticity_insights', 'expert_perspective'];
        foreach ($requiredSections as $section) {
            if (!isset($data[$section])) {
                $data[$section] = $this->getFallbackSection($section);
            }
        }

        return $data;
    }

    /**
     * Get fallback content when generation fails.
     */
    private function getFallbackContent(): array
    {
        return [
            'buyers_guide' => [
                'headline'           => 'What to Know Before You Buy',
                'introduction'       => 'Understanding product reviews is essential for making informed purchasing decisions. Our analysis helps you separate genuine feedback from potentially misleading reviews.',
                'key_considerations' => [
                    'Review authenticity varies significantly across products - always check the analysis grade.',
                    'Consider both the adjusted rating and the original Amazon rating when evaluating products.',
                    'Look for reviews that provide specific details about actual product usage.',
                ],
                'what_to_look_for' => 'Quality products typically have reviews that discuss specific features, include photos from real customers, and show a natural distribution of ratings over time.',
            ],
            'category_context' => [
                'market_overview'    => 'The online marketplace continues to grow, making review authenticity increasingly important for consumers.',
                'common_issues'      => 'Fake reviews, incentivized feedback, and competitor manipulation are common challenges shoppers face when evaluating products.',
                'quality_indicators' => 'Look for verified purchases, detailed descriptions of usage, and reviews that mention both positives and negatives.',
            ],
            'authenticity_insights' => [
                'grade_interpretation' => 'Our grading system analyzes multiple factors including review timing, language patterns, and verification status.',
                'trust_recommendation' => 'Use this analysis as one factor in your decision-making process alongside your own research.',
                'review_reading_tips'  => 'Focus on reviews that provide specific details and consider the overall pattern rather than individual outliers.',
            ],
            'expert_perspective' => [
                'overall_assessment'      => 'This analysis provides insight into the reliability of product reviews to help you make better purchasing decisions.',
                'purchase_considerations' => 'Consider the authenticity grade, read a sample of reviews yourself, and compare with similar products.',
                'alternatives_note'       => 'Always compare multiple options in the same category before making a final decision.',
            ],
            'content_meta' => [
                'word_count'        => 250,
                'expertise_signals' => ['general consumer guidance'],
                'generation_status' => 'fallback',
            ],
        ];
    }

    /**
     * Get fallback content for a specific section.
     */
    private function getFallbackSection(string $section): array
    {
        $fallback = $this->getFallbackContent();

        return $fallback[$section] ?? [];
    }

    /**
     * Get full country name from country code.
     */
    private function getCountryName(string $countryCode): string
    {
        $countries = [
            'us' => 'United States',
            'gb' => 'United Kingdom',
            'uk' => 'United Kingdom',
            'ca' => 'Canada',
            'de' => 'Germany',
            'fr' => 'France',
            'it' => 'Italy',
            'es' => 'Spain',
            'jp' => 'Japan',
            'au' => 'Australia',
            'mx' => 'Mexico',
            'in' => 'India',
            'br' => 'Brazil',
            'nl' => 'Netherlands',
        ];

        return $countries[strtolower($countryCode)] ?? $countryCode;
    }

    /**
     * Check if editorial content generation is available (provider configured).
     */
    public function isAvailable(): bool
    {
        // Ollama doesn't need an API key
        if ($this->provider === 'ollama') {
            return true;
        }

        return !empty($this->apiKey);
    }

    /**
     * Get the current provider name for logging.
     */
    public function getProviderName(): string
    {
        return $this->provider;
    }

    /**
     * Generate editorial content for multiple products concurrently.
     *
     * @param array $products Array of AsinData models
     *
     * @return array Results keyed by product ID with 'success' and 'error' keys
     */
    public function generateBatchConcurrently(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $productPrompts = $this->prepareProductPrompts($products);

        if (empty($productPrompts)) {
            return [];
        }

        $responses = $this->executeConcurrentRequests($productPrompts);

        return $this->processResponses($responses, $productPrompts);
    }

    /**
     * Prepare prompts for all products in batch.
     */
    private function prepareProductPrompts(array $products): array
    {
        $productPrompts = [];

        foreach ($products as $product) {
            if (empty($product->product_title)) {
                continue;
            }
            $product->update(['editorial_status' => 'processing']);
            $productPrompts[$product->id] = [
                'product' => $product,
                'prompt'  => $this->buildEditorialPrompt($product),
            ];
        }

        return $productPrompts;
    }

    /**
     * Process responses from concurrent requests.
     */
    private function processResponses(array $responses, array $productPrompts): array
    {
        $results = [];

        foreach ($responses as $productId => $response) {
            $product = $productPrompts[$productId]['product'];
            $results[$productId] = $this->processSingleResponse($response, $product);
        }

        return $results;
    }

    /**
     * Process a single response and update the product.
     */
    private function processSingleResponse($response, AsinData $product): array
    {
        try {
            if ($response instanceof \Exception) {
                throw $response;
            }

            if (!$response->successful()) {
                throw new \Exception('API request failed: '.$response->status());
            }

            $content = $this->extractResponseContent($response);
            $contentData = $this->parseResponse($content);

            $product->update([
                'editorial_content'      => $contentData,
                'editorial_status'       => 'completed',
                'editorial_generated_at' => now(),
            ]);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            $product->update(['editorial_status' => 'failed']);

            LoggingService::log('Editorial content generation failed in batch', [
                'asin'  => $product->asin,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute concurrent HTTP requests using Laravel's HTTP pool.
     */
    private function executeConcurrentRequests(array $productPrompts): array
    {
        if ($this->provider === 'ollama') {
            return $this->executeOllamaConcurrent($productPrompts);
        }

        return $this->executeChatCompletionsConcurrent($productPrompts);
    }

    /**
     * Execute concurrent requests for OpenAI/DeepSeek API.
     */
    private function executeChatCompletionsConcurrent(array $productPrompts): array
    {
        $endpoint = $this->baseUrl;
        if (!str_ends_with($endpoint, '/chat/completions')) {
            $endpoint = rtrim($endpoint, '/').'/chat/completions';
        }

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($this->apiKey)) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        $responses = Http::pool(function ($pool) use ($productPrompts, $endpoint, $headers) {
            foreach ($productPrompts as $productId => $data) {
                $pool->as($productId)
                    ->withHeaders($headers)
                    ->timeout(45)
                    ->connectTimeout(10)
                    ->post($endpoint, [
                        'model'    => $this->model,
                        'messages' => [
                            [
                                'role'    => 'system',
                                'content' => 'You are an expert consumer advisor and editorial content writer. You create unique, substantive content that helps consumers make informed purchasing decisions. Your writing demonstrates expertise in product categories and provides actionable insights. Always respond with valid JSON.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $data['prompt'],
                            ],
                        ],
                        'temperature' => 0.7,
                        'max_tokens'  => 1500,
                    ]);
            }
        });

        return $responses;
    }

    /**
     * Execute concurrent requests for Ollama API.
     */
    private function executeOllamaConcurrent(array $productPrompts): array
    {
        $endpoint = rtrim($this->baseUrl, '/').'/api/generate';
        $systemPrompt = 'You are an expert consumer advisor and editorial content writer. You create unique, substantive content that helps consumers make informed purchasing decisions. Your writing demonstrates expertise in product categories and provides actionable insights. Always respond with valid JSON.';

        $responses = Http::pool(function ($pool) use ($productPrompts, $endpoint, $systemPrompt) {
            foreach ($productPrompts as $productId => $data) {
                $pool->as($productId)
                    ->timeout(180)
                    ->post($endpoint, [
                        'model'   => $this->model,
                        'prompt'  => $systemPrompt."\n\n".$data['prompt'],
                        'stream'  => false,
                        'options' => [
                            'temperature' => 0.7,
                            'num_predict' => 1500,
                        ],
                    ]);
            }
        });

        return $responses;
    }

    /**
     * Extract content from response based on provider.
     */
    private function extractResponseContent($response): string
    {
        if ($this->provider === 'ollama') {
            return $response->json('response', '');
        }

        return $response->json('choices.0.message.content', '');
    }
}
