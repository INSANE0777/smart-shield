<?php

namespace Tests\Unit;

use App\Services\OpenAIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIServiceTest extends TestCase
{
    private OpenAIService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up environment variables for testing
        config([
            'services.openai.api_key'  => 'test_api_key',
            'services.openai.model'    => 'gpt-4',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        // Create service instance
        $this->service = new OpenAIService();
    }

    public function test_analyze_reviews_success()
    {
        $reviews = [
            ['id' => 0, 'rating' => 5, 'review_title' => 'Great!', 'review_text' => 'Great product, highly recommend!', 'author' => 'John'],
            ['id' => 1, 'rating' => 1, 'review_title' => 'Bad', 'review_text' => 'Terrible fake product, avoid at all costs!', 'author' => 'Jane'],
        ];

        // Mock OpenAI API response
        $openaiResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => '[{"id":"0","score":25},{"id":"1","score":85}]',
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($openaiResponse, 200),
        ]);

        $result = $this->service->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertCount(2, $result['detailed_scores']);

        // Check specific scores
        $this->assertEquals(25, $result['detailed_scores'][0]);
        $this->assertEquals(85, $result['detailed_scores'][1]);
    }

    public function test_analyze_reviews_empty_array()
    {
        $result = $this->service->analyzeReviews([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertEmpty($result['results']);
    }

    public function test_analyze_reviews_openai_api_error()
    {
        $reviews = [
            ['id' => 0, 'rating' => 5, 'review_title' => 'Great', 'review_text' => 'Great product', 'author' => 'John'],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Internal server error',
                    'type'    => 'server_error',
                ],
            ], 500),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to analyze reviews');

        $this->service->analyzeReviews($reviews);
    }

    public function test_analyze_reviews_invalid_json_response()
    {
        $reviews = [
            ['id' => 0, 'rating' => 5, 'review_title' => 'Great', 'review_text' => 'Great product', 'author' => 'John'],
        ];

        // Mock OpenAI API with invalid JSON in content
        $openaiResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'invalid json content',
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($openaiResponse, 200),
        ]);

        $result = $this->service->analyzeReviews($reviews);

        // Should return empty detailed scores as fallback
        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertEmpty($result['detailed_scores']);
    }

    public function test_analyze_reviews_missing_choices()
    {
        $reviews = [
            ['id' => 0, 'rating' => 5, 'review_title' => 'Great', 'review_text' => 'Great product', 'author' => 'John'],
        ];

        // Mock OpenAI API response without choices
        $openaiResponse = [
            'usage' => [
                'total_tokens' => 100,
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($openaiResponse, 200),
        ]);

        $result = $this->service->analyzeReviews($reviews);

        // Should return empty detailed scores as fallback
        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertEmpty($result['detailed_scores']);
    }

    public function test_analyze_reviews_rate_limit_error()
    {
        $reviews = [
            ['id' => 0, 'rating' => 5, 'review_title' => 'Great', 'review_text' => 'Great product', 'author' => 'John'],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded',
                    'type'    => 'rate_limit_error',
                ],
            ], 429),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to analyze reviews');

        $this->service->analyzeReviews($reviews);
    }

    public function test_analyze_reviews_authentication_error()
    {
        $reviews = [
            ['id' => 0, 'rating' => 5, 'review_title' => 'Great', 'review_text' => 'Great product', 'author' => 'John'],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                    'type'    => 'authentication_error',
                ],
            ], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to analyze reviews');

        $this->service->analyzeReviews($reviews);
    }

    public function test_analyze_reviews_with_special_characters()
    {
        $reviews = [
            ['id' => 0, 'rating' => 5, 'review_title' => 'Great!', 'review_text' => 'Great product! 😊 100% recommend', 'author' => 'John'],
            ['id' => 1, 'rating' => 1, 'review_title' => 'Bad', 'review_text' => 'Terrible... "worst" purchase ever!!!', 'author' => 'Jane'],
        ];

        // Mock OpenAI API response
        $openaiResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => '[{"id":"0","score":20},{"id":"1","score":75}]',
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($openaiResponse, 200),
        ]);

        $result = $this->service->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertCount(2, $result['detailed_scores']);
        $this->assertEquals(20, $result['detailed_scores'][0]);
        $this->assertEquals(75, $result['detailed_scores'][1]);
    }

    public function test_analyze_reviews_with_bandwidth_optimized_structure()
    {
        // Mock new bandwidth-optimized review structure (no review_title)
        $reviews = [
            [
                'id'     => 'scrape_abc123',
                'rating' => 5,
                'text'   => 'This product is amazing! Highly recommend it.',
            ],
            [
                'id'     => 'scrape_def456',
                'rating' => 3,
                'text'   => 'It\'s okay, but not what I expected.',
            ],
        ];

        // Mock successful OpenAI response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => '[{"id":"scrape_abc123","score":25}, {"id":"scrape_def456","score":60}]',
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->service->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertCount(2, $result['detailed_scores']);
        $this->assertEquals(25, $result['detailed_scores']['scrape_abc123']);
        $this->assertEquals(60, $result['detailed_scores']['scrape_def456']);
    }

    public function test_analyze_reviews_with_legacy_structure()
    {
        // Mock old review structure (with review_title and review_text)
        $reviews = [
            [
                'id'           => 'legacy_123',
                'rating'       => 4,
                'review_title' => 'Great Product!',
                'review_text'  => 'I really love this product. Works perfectly.',
                'meta_data'    => [
                    'verified_purchase' => true,
                    'is_vine_voice'     => false,
                ],
            ],
            [
                'id'           => 'legacy_456',
                'rating'       => 2,
                'review_title' => 'Not as expected',
                'review_text'  => 'The quality is poor and it broke after a week.',
                'meta_data'    => [
                    'verified_purchase' => false,
                    'is_vine_voice'     => true,
                ],
            ],
        ];

        // Mock successful OpenAI response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => '[{"id":"legacy_123","score":15}, {"id":"legacy_456","score":75}]',
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->service->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertCount(2, $result['detailed_scores']);
        $this->assertEquals(15, $result['detailed_scores']['legacy_123']);
        $this->assertEquals(75, $result['detailed_scores']['legacy_456']);
    }

    public function test_analyze_reviews_with_mixed_structures()
    {
        // Test mixture of old and new review structures
        $reviews = [
            [
                'id'     => 'new_format',
                'rating' => 5,
                'text'   => 'Excellent product, highly recommended!',
            ],
            [
                'id'           => 'old_format',
                'rating'       => 3,
                'review_title' => 'Decent product',
                'review_text'  => 'Works as expected, nothing special.',
                'meta_data'    => [
                    'verified_purchase' => true,
                ],
            ],
        ];

        // Mock successful OpenAI response
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => '[{"id":"new_format","score":30}, {"id":"old_format","score":45}]',
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response($mockResponse, 200),
        ]);

        $result = $this->service->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertCount(2, $result['detailed_scores']);
        $this->assertEquals(30, $result['detailed_scores']['new_format']);
        $this->assertEquals(45, $result['detailed_scores']['old_format']);
    }

    public function test_constructor_throws_exception_without_api_key()
    {
        config(['services.openai.api_key' => '']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OpenAI API key is not configured');

        new OpenAIService();
    }

    protected function tearDown(): void
    {
        Http::fake(); // Reset HTTP fakes
        parent::tearDown();
    }
}
