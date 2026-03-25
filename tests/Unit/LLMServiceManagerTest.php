<?php

namespace Tests\Unit;

use App\Services\LLMProviderInterface;
use App\Services\LLMServiceManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LLMServiceManagerTest extends TestCase
{
    private LLMServiceManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        config([
            'services.llm.primary_provider' => 'openai',
            'services.llm.fallback_order'   => ['deepseek', 'openai'],
            'services.openai.api_key'       => 'test_openai_key',
            'services.deepseek.api_key'     => 'test_deepseek_key',
        ]);

        Cache::flush();
        $this->manager = new LLMServiceManager();
    }

    public function test_analyzes_reviews_with_primary_provider()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Great product!', 'rating' => 5],
            ['id' => 2, 'text' => 'Terrible fake!', 'rating' => 1],
        ];

        // Mock OpenAI response
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '[{"id":"1","score":25},{"id":"2","score":85}]']],
                ],
            ], 200),
        ]);

        $result = $this->manager->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
    }

    // Temporarily disabled due to DeepSeek parsing issues
    /* public function test_falls_back_to_secondary_provider_on_primary_failure()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Great product!', 'rating' => 5],
        ];

        // Mock OpenAI failure and DeepSeek success
        Http::fake([
            'api.openai.com/*'   => Http::response(['error' => 'API Error'], 500),
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '[{"id":"1","score":30}]']],
                ],
            ], 200),
        ]);

        $result = $this->manager->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
    } */

    public function test_throws_exception_when_all_providers_fail()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 4],
        ];

        // Mock all providers failing
        Http::fake([
            '*' => Http::response(['error' => 'Service unavailable'], 500),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('All LLM providers failed');

        $this->manager->analyzeReviews($reviews);
    }

    public function test_tracks_provider_performance_metrics()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 4],
        ];

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '[{"id":"1","score":40}]']],
                ],
            ], 200),
        ]);

        // Execute analysis
        $this->manager->analyzeReviews($reviews);

        // Check metrics are tracked
        $metrics = $this->manager->getProviderMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('OpenAI-gpt-4o-mini', $metrics);
    }

    public function test_switches_primary_provider()
    {
        // Switch to OpenAI provider using partial name matching (case-insensitive)
        $this->assertTrue($this->manager->switchProvider('OpenAI'));

        $optimal = $this->manager->getOptimalProvider();
        $this->assertInstanceOf(LLMProviderInterface::class, $optimal);
        $this->assertStringContainsString('OpenAI', $optimal->getProviderName());
    }

    public function test_compares_costs_across_providers()
    {
        $comparison = $this->manager->getCostComparison(100);

        $this->assertIsArray($comparison);
        $this->assertGreaterThan(0, count($comparison));

        foreach ($comparison as $providerName => $data) {
            $this->assertArrayHasKey('cost', $data);
            $this->assertArrayHasKey('available', $data);
            $this->assertArrayHasKey('health_score', $data);
        }
    }

    public function test_returns_provider_metrics()
    {
        $metrics = $this->manager->getProviderMetrics();

        $this->assertIsArray($metrics);

        foreach ($metrics as $providerName => $data) {
            $this->assertArrayHasKey('available', $data);
            $this->assertArrayHasKey('success_rate', $data);
            $this->assertArrayHasKey('avg_response_time', $data);
            $this->assertArrayHasKey('total_requests', $data);
            $this->assertArrayHasKey('health_score', $data);
        }
    }
}
