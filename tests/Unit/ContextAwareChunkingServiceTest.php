<?php

namespace Tests\Unit;

use App\Services\ContextAwareChunkingService;
use Tests\TestCase;

class ContextAwareChunkingServiceTest extends TestCase
{
    private ContextAwareChunkingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContextAwareChunkingService();
    }

    public function test_it_extracts_global_context_correctly()
    {
        $reviews = [
            ['id' => 1, 'rating' => 5, 'text' => 'Great product!', 'meta_data' => ['verified_purchase' => true]],
            ['id' => 2, 'rating' => 5, 'text' => 'Amazing quality', 'meta_data' => ['verified_purchase' => false]],
            ['id' => 3, 'rating' => 4, 'text' => 'Good but expensive', 'meta_data' => ['verified_purchase' => true]],
            ['id' => 4, 'rating' => 5, 'text' => 'Perfect!', 'meta_data' => ['verified_purchase' => true, 'is_vine_voice' => true]],
        ];

        $context = $this->service->extractGlobalContext($reviews);

        $this->assertEquals(4, $context['total_reviews']);
        $this->assertEquals(75.0, $context['five_star_percentage']); // 3/4 = 75%
        $this->assertEquals(75.0, $context['verified_percentage']); // 3/4 = 75%
        $this->assertEquals(25.0, $context['vine_percentage']); // 1/4 = 25%
        $this->assertArrayHasKey('rating_distribution', $context);
        $this->assertArrayHasKey('suspicious_patterns', $context);
        $this->assertArrayHasKey('context_summary', $context);
    }

    public function test_it_detects_suspicious_patterns()
    {
        // Create reviews with suspicious patterns
        $suspiciousReviews = [];
        for ($i = 0; $i < 100; $i++) {
            $suspiciousReviews[] = [
                'id'        => $i,
                'rating'    => 5, // All 5-star reviews
                'text'      => 'Great!', // Very short text
                'meta_data' => ['verified_purchase' => false], // All unverified
            ];
        }

        $context = $this->service->extractGlobalContext($suspiciousReviews);

        $this->assertNotEmpty($context['suspicious_patterns']);
        $this->assertStringContainsString('Extremely high 5-star concentration', implode(' ', $context['suspicious_patterns']));
        $this->assertStringContainsString('Low verified purchase rate', implode(' ', $context['suspicious_patterns']));
        $this->assertStringContainsString('Unusually short reviews', implode(' ', $context['suspicious_patterns']));
    }

    public function test_it_generates_compact_context_summary()
    {
        $summary = $this->service->generateContextSummary(85.5, 45.2, 5.0, ['High fake concentration']);

        $this->assertStringContainsString('85.5% 5-star', $summary);
        $this->assertStringContainsString('45.2% verified', $summary);
        $this->assertStringContainsString('5% Vine', $summary);
        $this->assertStringContainsString('ALERTS: High fake concentration', $summary);

        // Should be compact (roughly 100 tokens or less)
        $this->assertLessThan(500, strlen($summary));
    }

    public function test_it_processes_chunks_with_context_awareness()
    {
        $reviews = $this->createTestReviews(10);

        $processedChunks = [];
        $chunkProcessor = function ($chunk, $context) use (&$processedChunks) {
            $processedChunks[] = [
                'chunk_size'       => count($chunk),
                'context_received' => $context,
                'fake_percentage'  => 25.0,
                'confidence'       => 'medium',
                'explanation'      => 'Test explanation for chunk',
            ];

            return [
                'fake_percentage' => 25.0,
                'confidence'      => 'medium',
                'explanation'     => 'Test explanation for chunk',
            ];
        };

        $result = $this->service->processWithContextAwareChunking(
            $reviews,
            5, // Chunk size
            $chunkProcessor
        );

        $this->assertEquals(2, count($processedChunks)); // 10 reviews / 5 chunk size = 2 chunks
        $this->assertArrayHasKey('fake_percentage', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('global_context', $result);

        // Verify context was passed to chunks
        foreach ($processedChunks as $chunk) {
            $this->assertArrayHasKey('total_reviews', $chunk['context_received']);
            $this->assertArrayHasKey('five_star_percentage', $chunk['context_received']);
            $this->assertArrayHasKey('chunk_number', $chunk['context_received']);
        }
    }

    public function test_it_handles_chunk_failures_gracefully()
    {
        $reviews = $this->createTestReviews(10);

        $chunkProcessor = function ($chunk, $context) {
            static $callCount = 0;
            $callCount++;

            if ($callCount === 1) {
                throw new \Exception('Simulated chunk failure');
            }

            return [
                'fake_percentage' => 30.0,
                'confidence'      => 'high',
                'explanation'     => 'Successful chunk analysis',
            ];
        };

        $result = $this->service->processWithContextAwareChunking(
            $reviews,
            5,
            $chunkProcessor,
            ['max_failure_rate' => 0.6] // Allow up to 60% failures
        );

        $this->assertArrayHasKey('fake_percentage', $result);
        $this->assertEquals(1, $result['chunks_processed']); // Only 1 successful chunk
    }

    public function test_it_fails_when_too_many_chunks_fail()
    {
        $reviews = $this->createTestReviews(10);

        $chunkProcessor = function ($chunk, $context) {
            throw new \Exception('All chunks fail');
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Too many chunks failed');

        $this->service->processWithContextAwareChunking(
            $reviews,
            5,
            $chunkProcessor,
            ['max_failure_rate' => 0.3] // Allow only 30% failures
        );
    }

    public function test_it_aggregates_chunk_results_with_context()
    {
        $chunkResults = [
            [
                'fake_percentage' => 20.0,
                'confidence'      => 'high',
                'explanation'     => 'First chunk shows low fake percentage',
                'review_count'    => 5,
                'fake_examples'   => [
                    ['text' => 'Great product!', 'reason' => 'Too generic'],
                    ['text' => 'Love it!', 'reason' => 'Very short'],
                ],
                'key_patterns' => ['pattern1', 'short reviews'],
            ],
            [
                'fake_percentage' => 40.0,
                'confidence'      => 'medium',
                'explanation'     => 'Second chunk shows higher fake percentage',
                'review_count'    => 5,
                'fake_examples'   => [
                    ['text' => 'Great product!', 'reason' => 'Too generic'], // Duplicate
                    ['text' => 'Amazing quality', 'reason' => 'Generic praise'],
                ],
                'key_patterns' => ['pattern2', 'short reviews'], // Duplicate pattern
            ],
        ];

        $globalContext = [
            'total_reviews'        => 10,
            'five_star_percentage' => 80.0,
            'suspicious_patterns'  => ['High 5-star concentration'],
        ];

        $result = $this->service->aggregateChunkResults($chunkResults, $globalContext);

        $this->assertEquals(30.0, $result['fake_percentage']); // Weighted average: (20*5 + 40*5) / 10 = 30
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertStringContainsString('Analysis of 10 reviews across 2 chunks', $result['explanation']);
        $this->assertEquals(2, $result['chunks_processed']);
        $this->assertArrayHasKey('global_context', $result);

        // Test deduplication of fake_examples (should have 3 unique examples, not 4)
        $this->assertCount(3, $result['fake_examples']);
        $this->assertEquals('Great product!', $result['fake_examples'][0]['text']); // First occurrence kept
        $this->assertEquals('Love it!', $result['fake_examples'][1]['text']);
        $this->assertEquals('Amazing quality', $result['fake_examples'][2]['text']);

        // Test deduplication of key_patterns (should have 3 unique patterns, not 4)
        $this->assertCount(3, $result['key_patterns']);
        $this->assertContains('pattern1', $result['key_patterns']);
        $this->assertContains('short reviews', $result['key_patterns']); // Should appear only once
        $this->assertContains('pattern2', $result['key_patterns']);
    }

    public function test_it_extracts_unique_insights_avoiding_repetition()
    {
        $explanations = [
            'The review set shows a very high concentration of 5-star ratings. This is suspicious.',
            'High concentration of 5-star ratings detected. The pattern suggests manipulation.',
            'Reviews show balanced criticism and specific complaints. This indicates authenticity.',
        ];

        $method = new \ReflectionMethod($this->service, 'extractUniqueInsights');
        $method->setAccessible(true);

        $insights = $method->invoke($this->service, $explanations);

        // Should extract unique insights and avoid repetition
        $this->assertLessThanOrEqual(2, count($insights)); // Limited to prevent bloat

        // Should not repeat the same concept about 5-star concentration
        $insightsText = implode(' ', $insights);
        $this->assertLessThanOrEqual(1, substr_count(strtolower($insightsText), '5-star'));
    }

    public function test_it_generates_context_header()
    {
        $globalContext = [
            'context_summary' => 'GLOBAL CONTEXT: 85.0% 5-star, 60.0% verified, 5.0% Vine. ALERTS: High concentration detected.',
        ];

        $header = $this->service->generateContextHeader($globalContext);

        $this->assertEquals('CONTEXT: GLOBAL CONTEXT: 85.0% 5-star, 60.0% verified, 5.0% Vine. ALERTS: High concentration detected.', $header);
    }

    public function test_it_handles_empty_reviews_array()
    {
        $chunkProcessor = function ($chunk, $context) {
            return ['fake_percentage' => 0];
        };

        $result = $this->service->processWithContextAwareChunking([], 5, $chunkProcessor);

        $this->assertEquals(['results' => []], $result);
    }

    public function test_it_respects_rate_limiting_options()
    {
        $reviews = $this->createTestReviews(4);
        $startTime = microtime(true);

        $chunkProcessor = function ($chunk, $context) {
            return [
                'fake_percentage' => 25.0,
                'confidence'      => 'medium',
                'explanation'     => 'Test chunk',
            ];
        };

        $this->service->processWithContextAwareChunking(
            $reviews,
            2, // 2 chunks
            $chunkProcessor,
            ['delay_ms' => 100] // 100ms delay between chunks
        );

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should take at least 100ms due to delay (allowing some tolerance)
        $this->assertGreaterThan(80, $duration);
    }

    private function createTestReviews(int $count): array
    {
        $reviews = [];
        for ($i = 1; $i <= $count; $i++) {
            $reviews[] = [
                'id'        => $i,
                'rating'    => ($i % 5) + 1, // Ratings 1-5
                'text'      => "This is review number {$i} with some content.",
                'meta_data' => [
                    'verified_purchase' => $i % 2 === 0, // Alternate verified/unverified
                    'is_vine_voice'     => $i % 10 === 0, // Every 10th is Vine
                ],
            ];
        }

        return $reviews;
    }
}
