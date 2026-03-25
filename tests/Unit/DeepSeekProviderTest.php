<?php

namespace Tests\Unit;

use App\Services\Providers\DeepSeekProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekProviderTest extends TestCase
{
    private DeepSeekProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.deepseek.api_key'  => 'test_deepseek_key',
            'services.deepseek.base_url' => 'https://api.deepseek.com/v1',
            'services.deepseek.model'    => 'deepseek-v3',
        ]);

        $this->provider = new DeepSeekProvider();
    }

    public function test_analyzes_reviews_successfully_with_aggregate_format()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Amazing product! Fast shipping!', 'rating' => 5],
            ['id' => 2, 'text' => 'Terrible quality. Broke immediately.', 'rating' => 1],
        ];

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '{"fake_percentage": 25, "confidence": "high", "explanation": "Mixed review set with one suspicious review", "fake_examples": [{"text": "Terrible quality", "reason": "Overly negative"}], "key_patterns": ["genuine feedback", "suspicious negativity"]}']],
                ],
            ], 200),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('fake_percentage', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('analysis_provider', $result);
        $this->assertArrayHasKey('total_cost', $result);

        $this->assertEquals(25, $result['fake_percentage']);
        $this->assertEquals('high', $result['confidence']);
        $this->assertStringContainsString('Mixed review set', $result['explanation']);
        $this->assertArrayHasKey('fake_examples', $result);
        $this->assertArrayHasKey('key_patterns', $result);
    }

    public function test_handles_api_errors_gracefully()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 4],
        ];

        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DeepSeek API error: 429');

        $this->provider->analyzeReviews($reviews);
    }

    public function test_returns_empty_results_for_empty_reviews()
    {
        $result = $this->provider->analyzeReviews([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertEmpty($result['results']);
    }

    public function test_calculates_optimized_max_tokens()
    {
        $tokens = $this->provider->getOptimizedMaxTokens(10);

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThanOrEqual(8192, $tokens); // Never exceed DeepSeek's limit

        // Test with large review count
        $largeTokens = $this->provider->getOptimizedMaxTokens(200);
        $this->assertLessThanOrEqual(8192, $largeTokens); // Should be capped at DeepSeek limit

        // Should have reasonable minimum for aggregate responses
        $this->assertGreaterThanOrEqual(1500, $tokens);
    }

    public function test_checks_availability_with_api_key()
    {
        Http::fake([
            'api.deepseek.com/v1/models' => Http::response(['data' => []], 200),
        ]);

        $this->assertTrue($this->provider->isAvailable());
    }

    public function test_checks_availability_without_api_key()
    {
        config(['services.deepseek.api_key' => '']);
        $provider = new DeepSeekProvider();

        $this->assertFalse($provider->isAvailable());
    }

    public function test_detects_local_deployment()
    {
        config(['services.deepseek.base_url' => 'http://localhost:8000/v1']);
        $provider = new DeepSeekProvider();

        $this->assertStringContainsString('Self-Hosted', $provider->getProviderName());
    }

    public function test_calculates_api_cost()
    {
        $cost = $this->provider->getEstimatedCost(100);

        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);

        // Should be significantly cheaper than OpenAI (less than $0.10 for 100 reviews)
        $this->assertLessThan(0.10, $cost);
    }

    public function test_returns_zero_cost_for_local_deployment()
    {
        config(['services.deepseek.base_url' => 'http://localhost:8000/v1']);
        $provider = new DeepSeekProvider();

        $cost = $provider->getEstimatedCost(100);
        $this->assertEquals(0.0, $cost);
    }

    public function test_handles_malformed_json_response()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 4],
        ];

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'invalid json response']],
                ],
            ], 200),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to parse DeepSeek response');

        $this->provider->analyzeReviews($reviews);
    }
}
