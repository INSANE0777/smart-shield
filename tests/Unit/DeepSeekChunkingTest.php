<?php

namespace Tests\Unit;

use App\Services\Providers\DeepSeekProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeepSeekChunkingTest extends TestCase
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

    #[Test]
    public function it_triggers_chunking_for_large_review_sets()
    {
        // Create 100 reviews to trigger chunking (threshold is 80)
        $reviews = [];
        for ($i = 1; $i <= 100; $i++) {
            $reviews[] = [
                'id'     => "R{$i}",
                'text'   => "Test review number {$i}. This is a sample review.",
                'rating' => rand(3, 5),
            ];
        }

        // Mock successful responses for each chunk
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        ['message' => ['content' => json_encode([
                            'fake_percentage' => 15,
                            'confidence'      => 'high',
                            'explanation'     => 'Chunk 1 analysis',
                            'fake_examples'   => [],
                            'key_patterns'    => [],
                        ])]],
                    ],
                ], 200)
                ->push([
                    'choices' => [
                        ['message' => ['content' => json_encode([
                            'fake_percentage' => 20,
                            'confidence'      => 'medium',
                            'explanation'     => 'Chunk 2 analysis',
                            'fake_examples'   => [],
                            'key_patterns'    => [],
                        ])]],
                    ],
                ], 200),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('fake_percentage', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('chunks_processed', $result);
        $this->assertStringContainsString('chunks', $result['explanation']);
        $this->assertGreaterThan(1, $result['chunks_processed']); // Should have processed multiple chunks
    }

    #[Test]
    public function it_does_not_chunk_small_review_sets()
    {
        // Create 50 reviews (below 80 threshold)
        $reviews = [];
        for ($i = 1; $i <= 50; $i++) {
            $reviews[] = [
                'id'     => "R{$i}",
                'text'   => "Test review number {$i}. This is a sample review.",
                'rating' => rand(3, 5),
            ];
        }

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'fake_percentage' => 12,
                        'confidence'      => 'high',
                        'explanation'     => 'Single analysis',
                        'fake_examples'   => [],
                        'key_patterns'    => [],
                    ])]],
                ],
            ], 200),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('fake_percentage', $result);
        $this->assertEquals(12, $result['fake_percentage']);
        $this->assertStringNotContainsString('Chunked', $result['analysis_provider']);
    }

    #[Test]
    public function it_respects_max_tokens_limit()
    {
        // Test that max_tokens never exceeds DeepSeek's 8192 limit
        $smallSet = $this->provider->getOptimizedMaxTokens(10);
        $mediumSet = $this->provider->getOptimizedMaxTokens(50);
        $largeSet = $this->provider->getOptimizedMaxTokens(200);

        $this->assertLessThanOrEqual(8192, $smallSet);
        $this->assertLessThanOrEqual(8192, $mediumSet);
        $this->assertLessThanOrEqual(8192, $largeSet);

        // Should be reasonable for aggregate responses
        $this->assertGreaterThanOrEqual(1500, $smallSet); // Minimum
        $this->assertLessThanOrEqual(4000, $mediumSet); // Reasonable for medium sets
    }

    #[Test]
    public function it_handles_chunk_failures_gracefully()
    {
        $reviews = [];
        for ($i = 1; $i <= 100; $i++) {
            $reviews[] = [
                'id'     => "R{$i}",
                'text'   => "Test review number {$i}.",
                'rating' => rand(3, 5),
            ];
        }

        // First chunk succeeds, second fails
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push([
                    'choices' => [
                        ['message' => ['content' => json_encode([
                            'fake_percentage' => 15,
                            'confidence'      => 'high',
                            'explanation'     => 'Chunk 1',
                            'fake_examples'   => [],
                            'key_patterns'    => [],
                        ])]],
                    ],
                ], 200)
                ->push(['error' => 'Rate limit exceeded'], 429),
        ]);

        // Should still succeed with partial results (50% failure threshold)
        $result = $this->provider->analyzeReviews($reviews);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('fake_percentage', $result);
    }

    #[Test]
    public function it_fails_when_too_many_chunks_fail()
    {
        $reviews = [];
        for ($i = 1; $i <= 150; $i++) {
            $reviews[] = [
                'id'     => "R{$i}",
                'text'   => "Test review number {$i}.",
                'rating' => rand(3, 5),
            ];
        }

        // All chunks fail
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Too many chunks failed');

        $this->provider->analyzeReviews($reviews);
    }
}
