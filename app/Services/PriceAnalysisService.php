<?php

namespace App\Services;

use App\Models\AsinData;
use Illuminate\Support\Facades\Http;

/**
 * Service for analyzing product pricing using AI.
 *
 * This service provides price analysis functionality that runs independently
 * of the main review analysis flow. It uses AI to provide insights on:
 * - MSRP vs Amazon price comparison
 * - Price positioning analysis
 * - Value assessment based on product category
 *
 * Respects LLM_PRIMARY_PROVIDER configuration from .env
 */
class PriceAnalysisService
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
     * Analyze pricing for a product.
     *
     * @param AsinData $asinData The product to analyze
     *
     * @throws \Exception If analysis fails
     *
     * @return array The price analysis results
     */
    public function analyzePricing(AsinData $asinData): array
    {
        // Validate that we have enough product data first
        if (empty($asinData->product_title)) {
            throw new \InvalidArgumentException('Product title is required for price analysis.');
        }

        // Validate API key for providers that require it (skip in testing with HTTP fakes)
        if (!$this->isAvailable() && !app()->environment('testing')) {
            throw new \InvalidArgumentException('LLM API key is not configured for price analysis.');
        }

        LoggingService::log('Starting price analysis', [
            'asin'     => $asinData->asin,
            'country'  => $asinData->country,
            'title'    => substr($asinData->product_title, 0, 50),
            'provider' => $this->provider,
        ]);

        // Mark as processing
        $asinData->update(['price_analysis_status' => 'processing']);

        try {
            $prompt = $this->buildPriceAnalysisPrompt($asinData);
            $result = $this->callLLM($prompt);

            // Parse and validate the response
            $analysisData = $this->parseResponse($result);

            // Save the analysis results
            $asinData->update([
                'price_analysis'        => $analysisData,
                'price_analysis_status' => 'completed',
                'price_analyzed_at'     => now(),
            ]);

            LoggingService::log('Price analysis completed', [
                'asin' => $asinData->asin,
            ]);

            return $analysisData;
        } catch (\Exception $e) {
            // Mark as failed but don't break the main flow
            $asinData->update([
                'price_analysis_status' => 'failed',
            ]);

            LoggingService::log('Price analysis failed', [
                'asin'  => $asinData->asin,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the AI prompt for price analysis.
     * Designed for cost efficiency with focused, minimal token usage.
     */
    private function buildPriceAnalysisPrompt(AsinData $asinData): string
    {
        $country = $this->getCountryName($asinData->country);
        $productTitle = $asinData->product_title;
        $productDescription = $asinData->product_description ?? '';
        $amazonRating = $asinData->amazon_rating ?? 'N/A';
        $reviewCount = $asinData->total_reviews_on_amazon ?? count($asinData->getReviewsArray());

        // Truncate description to save tokens
        if (strlen($productDescription) > 300) {
            $productDescription = substr($productDescription, 0, 300).'...';
        }

        // Include actual price if available
        $priceInfo = $this->formatPriceInfo($asinData);

        return <<<PROMPT
Analyze this Amazon product's pricing for a {$country} consumer.

Product: {$productTitle}
Description: {$productDescription}
{$priceInfo}Amazon Rating: {$amazonRating}/5 ({$reviewCount} reviews)
Country: {$country}

Provide a JSON response with this exact structure:
{
  "msrp_analysis": {
    "estimated_msrp": "<estimated MSRP in local currency, or 'Unknown' if cannot determine>",
    "msrp_source": "<how you determined this: 'Product category average', 'Brand typical pricing', 'Market research', or 'Unable to determine'>",
    "amazon_price_assessment": "<'Below MSRP', 'At MSRP', 'Above MSRP', or 'Unable to compare'>"
  },
  "market_comparison": {
    "price_positioning": "<'Budget', 'Mid-range', 'Premium', or 'Luxury' based on product category>",
    "typical_alternatives_range": "<price range of similar products, e.g., '$20-$50'>",
    "value_proposition": "<one sentence on value vs alternatives>"
  },
  "price_insights": {
    "seasonal_consideration": "<brief note on best time to buy, if applicable>",
    "deal_indicators": "<what to look for in a good deal on this product>",
    "caution_flags": "<any pricing red flags to watch for>"
  },
  "summary": "<2-3 sentence overall price assessment and buying recommendation>"
}

Be practical and helpful. If you cannot determine specific values, provide useful general guidance based on the product category. Keep responses concise.
PROMPT;
    }

    /**
     * Format price information for the prompt.
     */
    private function formatPriceInfo(AsinData $asinData): string
    {
        if (empty($asinData->price)) {
            return "Current Amazon Price: Unknown\n";
        }

        $currencySymbol = $this->getCurrencySymbol($asinData->currency ?? 'USD');
        $formattedPrice = $currencySymbol.number_format($asinData->price, 2);

        return "Current Amazon Price: {$formattedPrice}\n";
    }

    /**
     * Get currency symbol from currency code.
     */
    private function getCurrencySymbol(string $currencyCode): string
    {
        $symbols = [
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'JPY' => '¥',
            'INR' => '₹',
            'MXN' => 'MX$',
        ];

        return $symbols[$currencyCode] ?? '$';
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

        $response = Http::withHeaders($headers)->timeout(60)->post($endpoint, [
            'model'       => $this->model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are a pricing analyst helping consumers understand product pricing. Provide practical, actionable insights in JSON format. Be concise and helpful.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => 0.3,
            'max_tokens'  => 800,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Price analysis API request failed: '.$response->status());
        }

        return $response->json('choices.0.message.content', '');
    }

    /**
     * Call Ollama API for local LLM processing.
     */
    private function callOllama(string $prompt): string
    {
        $endpoint = rtrim($this->baseUrl, '/').'/api/generate';

        $systemPrompt = 'You are a pricing analyst helping consumers understand product pricing. Provide practical, actionable insights in JSON format. Be concise and helpful.';

        $response = Http::timeout(120)->post($endpoint, [
            'model'   => $this->model,
            'prompt'  => $systemPrompt."\n\n".$prompt,
            'stream'  => false,
            'options' => [
                'temperature' => 0.3,
                'num_predict' => 800,
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Price analysis API request failed: '.$response->status());
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
            LoggingService::log('Price analysis JSON parse error', [
                'error'   => json_last_error_msg(),
                'content' => substr($content, 0, 200),
            ]);

            // Return a fallback structure
            return [
                'msrp_analysis'     => [
                    'estimated_msrp'          => 'Unable to determine',
                    'msrp_source'             => 'Analysis incomplete',
                    'amazon_price_assessment' => 'Unable to compare',
                ],
                'market_comparison' => [
                    'price_positioning'          => 'Unknown',
                    'typical_alternatives_range' => 'N/A',
                    'value_proposition'          => 'Analysis could not be completed.',
                ],
                'price_insights'    => [
                    'seasonal_consideration' => 'N/A',
                    'deal_indicators'        => 'N/A',
                    'caution_flags'          => 'N/A',
                ],
                'summary'           => 'Price analysis could not be completed. Please try again later.',
                'analysis_error'    => true,
            ];
        }

        // Validate required fields exist
        $requiredSections = ['msrp_analysis', 'market_comparison', 'price_insights', 'summary'];
        foreach ($requiredSections as $section) {
            if (!isset($data[$section])) {
                $data[$section] = [];
            }
        }

        return $data;
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
     * Check if price analysis is available (provider configured).
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
     * Analyze multiple products concurrently using HTTP pool.
     *
     * @param array $products Array of AsinData models
     *
     * @return array Results keyed by product ID with 'success' and 'error' keys
     */
    public function analyzeBatchConcurrently(array $products): array
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
            $product->update(['price_analysis_status' => 'processing']);
            $productPrompts[$product->id] = [
                'product' => $product,
                'prompt'  => $this->buildPriceAnalysisPrompt($product),
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
            $analysisData = $this->parseResponse($content);

            $product->update([
                'price_analysis'        => $analysisData,
                'price_analysis_status' => 'completed',
                'price_analyzed_at'     => now(),
            ]);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            $product->update(['price_analysis_status' => 'failed']);

            LoggingService::log('Price analysis failed in batch', [
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
                    ->timeout(60)
                    ->post($endpoint, [
                        'model'       => $this->model,
                        'messages'    => [
                            [
                                'role'    => 'system',
                                'content' => 'You are a pricing analyst helping consumers understand product pricing. Provide practical, actionable insights in JSON format. Be concise and helpful.',
                            ],
                            [
                                'role'    => 'user',
                                'content' => $data['prompt'],
                            ],
                        ],
                        'temperature' => 0.3,
                        'max_tokens'  => 800,
                    ]);
            }
        });

        return $responses;
    }

    /**
     * Execute concurrent requests for Ollama API.
     * Note: Ollama may not handle concurrent requests as efficiently as cloud APIs.
     */
    private function executeOllamaConcurrent(array $productPrompts): array
    {
        $endpoint = rtrim($this->baseUrl, '/').'/api/generate';
        $systemPrompt = 'You are a pricing analyst helping consumers understand product pricing. Provide practical, actionable insights in JSON format. Be concise and helpful.';

        $responses = Http::pool(function ($pool) use ($productPrompts, $endpoint, $systemPrompt) {
            foreach ($productPrompts as $productId => $data) {
                $pool->as($productId)
                    ->timeout(120)
                    ->post($endpoint, [
                        'model'   => $this->model,
                        'prompt'  => $systemPrompt."\n\n".$data['prompt'],
                        'stream'  => false,
                        'options' => [
                            'temperature' => 0.3,
                            'num_predict' => 800,
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
