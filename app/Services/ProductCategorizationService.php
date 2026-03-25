<?php

namespace App\Services;

use App\Models\AsinData;
use Illuminate\Support\Facades\Http;

/**
 * Service for categorizing products using LLM inference.
 *
 * Used for:
 * 1. Backfilling existing products without category data
 * 2. Fallback when direct scraping doesn't capture categories
 */
class ProductCategorizationService
{
    /**
     * Predefined category taxonomy.
     * Keep this consistent for meaningful "related products" queries.
     */
    public const CATEGORIES = [
        'Electronics',
        'Computers & Accessories',
        'Cell Phones & Accessories',
        'Camera & Photo',
        'Home & Kitchen',
        'Kitchen & Dining',
        'Home Improvement',
        'Tools & Home Improvement',
        'Garden & Outdoor',
        'Sports & Outdoors',
        'Toys & Games',
        'Baby Products',
        'Beauty & Personal Care',
        'Health & Household',
        'Clothing & Fashion',
        'Shoes',
        'Jewelry',
        'Watches',
        'Pet Supplies',
        'Automotive',
        'Office Products',
        'Musical Instruments',
        'Arts & Crafts',
        'Books',
        'Movies & TV',
        'Video Games',
        'Grocery & Gourmet Food',
        'Industrial & Scientific',
        'Software',
        'Other',
    ];

    private string $provider;
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->provider = strtolower(config('services.llm.primary_provider', env('LLM_PRIMARY_PROVIDER', 'deepseek')));
        $this->initializeProvider();
    }

    /**
     * Initialize the appropriate LLM provider.
     */
    private function initializeProvider(): void
    {
        switch ($this->provider) {
            case 'openai':
                $this->apiKey = config('services.openai.key', env('OPENAI_API_KEY', ''));
                $this->model = config('services.openai.model', env('OPENAI_MODEL', 'gpt-4o-mini'));
                $this->baseUrl = 'https://api.openai.com/v1';
                break;

            case 'deepseek':
                $this->apiKey = config('services.deepseek.key', env('DEEPSEEK_API_KEY', ''));
                $this->model = config('services.deepseek.model', env('DEEPSEEK_MODEL', 'deepseek-chat'));
                $this->baseUrl = config('services.deepseek.base_url', env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'));
                break;

            case 'ollama':
                $this->apiKey = ''; // Ollama doesn't need API key
                $this->model = config('services.ollama.model', env('OLLAMA_MODEL', 'llama3.2'));
                $this->baseUrl = config('services.ollama.base_url', env('OLLAMA_BASE_URL', 'http://localhost:11434'));
                break;

            default:
                $this->apiKey = config('services.deepseek.key', env('DEEPSEEK_API_KEY', ''));
                $this->model = 'deepseek-chat';
                $this->baseUrl = 'https://api.deepseek.com/v1';
        }
    }

    /**
     * Categorize a product based on its title.
     *
     * For LLM inference, we can only determine a single category (no hierarchy).
     * The category_tags array will contain just this single category.
     *
     * For products with breadcrumb-extracted categories, category_tags will
     * contain the full hierarchy for more precise related product matching.
     *
     * @param string $productTitle The product title to categorize
     *
     * @return array{category_tags: array}|null Only returns category_tags (single source of truth)
     */
    public function categorize(string $productTitle): ?array
    {
        if (empty($productTitle)) {
            return null;
        }

        $categoryList = implode(', ', self::CATEGORIES);
        $prompt = $this->buildPrompt($productTitle, $categoryList);

        try {
            $category = $this->callLLM($prompt);

            if (empty($category)) {
                return null;
            }

            // Validate the category is in our list
            $category = $this->normalizeCategory($category);

            // LLM inference only returns leaf category, so single-element array
            return [
                'category_tags' => [$category],
            ];
        } catch (\Exception $e) {
            LoggingService::log('Product categorization failed', [
                'product_title' => substr($productTitle, 0, 100),
                'error'         => $e->getMessage(),
                'provider'      => $this->provider,
            ]);

            return null;
        }
    }

    /**
     * Batch categorize multiple products efficiently (sequential).
     *
     * @param array<int, string> $productTitles Array of product titles indexed by ID
     *
     * @return array<int, array{category_tags: array}>
     */
    public function categorizeBatch(array $productTitles): array
    {
        $results = [];

        foreach ($productTitles as $id => $title) {
            $result = $this->categorize($title);
            if ($result) {
                $results[$id] = $result;
            }

            // Small delay between requests to avoid rate limiting
            if (count($productTitles) > 10) {
                usleep(100000); // 100ms
            }
        }

        return $results;
    }

    /**
     * Batch categorize products in parallel using HTTP pool.
     *
     * DeepSeek rate limits:
     * - 500 requests/minute (~8/sec) for deepseek-chat
     * - Safe to run 5-10 parallel requests with small delays
     *
     * @param array<AsinData> $products Array of AsinData models
     *
     * @return array<int, bool> Map of product ID to success status
     */
    public function categorizeBatchParallel(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        // Ollama doesn't benefit from parallelization (single-threaded)
        if ($this->provider === 'ollama') {
            return $this->categorizeBatchSequential($products);
        }

        $categoryList = implode(', ', self::CATEGORIES);
        $results = [];

        // Build prompts for all products
        $prompts = [];
        foreach ($products as $product) {
            if (empty($product->product_title)) {
                $results[$product->id] = false;
                continue;
            }

            $prompts[$product->id] = $this->buildPrompt($product->product_title, $categoryList);
        }

        if (empty($prompts)) {
            return $results;
        }

        // Make parallel HTTP requests
        $responses = Http::pool(fn ($pool) => collect($prompts)->map(
            fn ($prompt, $productId) => $pool
                ->as((string) $productId)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model'       => $this->model,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'  => 50,
                    'temperature' => 0.1,
                ])
        )->toArray());

        // Process responses and update products
        foreach ($products as $product) {
            $productId = (string) $product->id;

            $response = $responses[$productId] ?? null;

            // Check if response exists and is a valid Response object (not ConnectionException)
            if (!$response || $response instanceof \Illuminate\Http\Client\ConnectionException) {
                $results[$product->id] = false;

                continue;
            }

            if (!$response->successful()) {
                $results[$product->id] = false;

                continue;
            }

            $data = $response->json();
            $category = trim($data['choices'][0]['message']['content'] ?? '');

            if (empty($category)) {
                $results[$product->id] = false;
                continue;
            }

            $normalizedCategory = $this->normalizeCategory($category);

            $product->update([
                'category_tags'   => [$normalizedCategory],
                'category_source' => 'llm_inference',
            ]);

            $results[$product->id] = true;
        }

        return $results;
    }

    /**
     * Sequential batch processing (fallback for Ollama or when parallel fails).
     *
     * @param array<AsinData> $products
     *
     * @return array<int, bool>
     */
    private function categorizeBatchSequential(array $products): array
    {
        $results = [];

        foreach ($products as $product) {
            $results[$product->id] = $this->categorizeAndSave($product);
        }

        return $results;
    }

    /**
     * Build categorization prompt.
     */
    private function buildPrompt(string $productTitle, string $categoryList): string
    {
        return <<<PROMPT
Categorize this Amazon product into ONE category from this list:
{$categoryList}

Product: {$productTitle}

Rules:
- Reply with ONLY the exact category name from the list
- Choose the most specific category that applies
- If unsure, use "Other"

Category:
PROMPT;
    }

    /**
     * Categorize and update an AsinData model.
     */
    public function categorizeAndSave(AsinData $product): bool
    {
        if (empty($product->product_title)) {
            return false;
        }

        $result = $this->categorize($product->product_title);

        if (!$result || empty($result['category_tags'])) {
            return false;
        }

        $product->update([
            'category_tags'   => $result['category_tags'],
            'category_source' => 'llm_inference',
        ]);

        return true;
    }

    /**
     * Call the LLM API.
     */
    private function callLLM(string $prompt): ?string
    {
        if ($this->provider === 'ollama') {
            return $this->callOllama($prompt);
        }

        return $this->callChatCompletionAPI($prompt);
    }

    /**
     * Call OpenAI/DeepSeek compatible chat completion API.
     */
    private function callChatCompletionAPI(string $prompt): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post("{$this->baseUrl}/chat/completions", [
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens'  => 50,
            'temperature' => 0.1, // Low temperature for consistent categorization
        ]);

        if (!$response->successful()) {
            LoggingService::log('LLM API call failed', [
                'provider' => $this->provider,
                'status'   => $response->status(),
                'body'     => substr($response->body(), 0, 200),
            ]);

            return null;
        }

        $data = $response->json();

        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Call Ollama API.
     */
    private function callOllama(string $prompt): ?string
    {
        $response = Http::timeout(60)->post("{$this->baseUrl}/api/generate", [
            'model'   => $this->model,
            'prompt'  => $prompt,
            'stream'  => false,
            'options' => [
                'temperature' => 0.1,
                'num_predict' => 50,
            ],
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return trim($data['response'] ?? '');
    }

    /**
     * Normalize and validate category against our taxonomy.
     */
    private function normalizeCategory(string $category): string
    {
        $category = trim($category);

        // Remove common prefixes/suffixes LLMs might add
        $category = preg_replace('/^(Category:|The category is:?)\s*/i', '', $category);
        $category = preg_replace('/\.+$/', '', $category);
        $category = trim($category);

        // Check for exact match
        if (in_array($category, self::CATEGORIES)) {
            return $category;
        }

        // Check for case-insensitive match
        foreach (self::CATEGORIES as $validCategory) {
            if (strtolower($category) === strtolower($validCategory)) {
                return $validCategory;
            }
        }

        // Check for partial/fuzzy match
        foreach (self::CATEGORIES as $validCategory) {
            if (str_contains(strtolower($validCategory), strtolower($category)) ||
                str_contains(strtolower($category), strtolower($validCategory))) {
                return $validCategory;
            }
        }

        // Default to "Other" if no match
        return 'Other';
    }

    /**
     * Get all valid categories.
     *
     * @return array<string>
     */
    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }
}

