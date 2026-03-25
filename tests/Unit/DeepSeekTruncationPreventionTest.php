<?php

namespace Tests\Unit;

use App\Services\ContextAwareChunkingService;
use App\Services\Providers\DeepSeekProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeepSeekTruncationPreventionTest extends TestCase
{
    /**
     * Test that DeepSeek max_tokens calculation provides sufficient tokens for explanations.
     */
    public function test_deepseek_max_tokens_prevents_truncation()
    {
        $provider = new DeepSeekProvider();

        // Test various review counts that trigger chunking
        $testCases = [
            ['reviews' => 50, 'expected_min_tokens' => 2500],
            ['reviews' => 100, 'expected_min_tokens' => 2500],
            ['reviews' => 215, 'expected_min_tokens' => 2500], // B005G14BIU case
        ];

        foreach ($testCases as $case) {
            $maxTokens = $provider->getOptimizedMaxTokens($case['reviews']);

            $this->assertGreaterThanOrEqual(
                $case['expected_min_tokens'],
                $maxTokens,
                "Max tokens for {$case['reviews']} reviews should be at least {$case['expected_min_tokens']}, got {$maxTokens}"
            );

            // Ensure we don't exceed DeepSeek's API limit
            $this->assertLessThanOrEqual(8192, $maxTokens, "Max tokens should not exceed DeepSeek's limit");
        }
    }

    /**
     * Test that chunking service properly handles explanation synthesis without truncation.
     */
    public function test_chunking_service_explanation_synthesis()
    {
        $chunkingService = new ContextAwareChunkingService();

        // Mock chunk results with realistic explanations
        $chunkResults = [
            [
                'fake_percentage' => 75,
                'confidence'      => 'high',
                'explanation'     => 'This chunk shows extremely high suspicion due to multiple severe red flags: 0% verified purchases (all reviews unverified), 0% Vine reviews, unusually short reviews (avg 0 characters suggests many may be empty or minimal), and a polarized rating distribution (45% 5-star, 30% 1-star) indicating potential manipulation.',
                'review_count'    => 43,
                'fake_examples'   => [],
                'key_patterns'    => [],
            ],
            [
                'fake_percentage' => 85,
                'confidence'      => 'high',
                'explanation'     => 'Second chunk analysis reveals consistent patterns of fake reviews with generic language and suspicious timing.',
                'review_count'    => 43,
                'fake_examples'   => [],
                'key_patterns'    => [],
            ],
        ];

        $globalContext = [
            'total_reviews'        => 215,
            'five_star_percentage' => 45,
            'verified_percentage'  => 0,
            'vine_percentage'      => 0,
            'suspicious_patterns'  => ['Low verified purchase rate (0%)'],
        ];

        $result = $chunkingService->aggregateChunkResults($chunkResults, $globalContext);

        // Verify explanation is complete and properly formatted
        $this->assertArrayHasKey('explanation', $result);
        $this->assertNotEmpty($result['explanation']);

        $explanation = $result['explanation'];

        // Check that explanation doesn't end mid-sentence (common truncation pattern)
        $this->assertDoesNotMatchRegularExpression(
            '/\([0-9]+\.?\s*$/',
            $explanation,
            'Explanation appears to be truncated mid-sentence (ends with incomplete parenthetical)'
        );

        // Check that explanation ends with proper punctuation
        $this->assertMatchesRegularExpression(
            '/[.!?]\s*$/',
            $explanation,
            'Explanation should end with proper punctuation'
        );

        // Verify explanation contains key components
        $this->assertStringContainsString('Analysis of 215 reviews', $explanation);
        $this->assertStringContainsString('chunks', $explanation);
        $this->assertStringContainsString('fake percentage', $explanation);

        // Ensure explanation is substantial (not just a stub)
        $this->assertGreaterThan(200, strlen($explanation), 'Explanation should be substantial');
    }

    /**
     * Test that DeepSeek response parsing handles truncated JSON gracefully.
     */
    public function test_deepseek_handles_truncated_responses()
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"fake_percentage": 81, "confidence": "high", "explanation": "Analysis shows high fake percentage due to multiple red flags: unverified purchases and polarized rating distribution (45',
                        ],
                    ],
                ],
            ]),
        ]);

        $provider = new DeepSeekProvider();
        $reviews = [['id' => 'test', 'text' => 'test review', 'rating' => 5]];

        try {
            $result = $provider->analyzeReviews($reviews);

            // Should either recover the partial data or throw a clear exception
            if (isset($result['explanation'])) {
                // If recovered, explanation should end properly
                $this->assertMatchesRegularExpression(
                    '/[.!?]\s*$/',
                    $result['explanation'],
                    'Recovered explanation should end with proper punctuation'
                );
            }
        } catch (\Exception $e) {
            // Should throw a clear exception about parsing failure
            $this->assertStringContainsString('response', strtolower($e->getMessage()));
        }
    }

    /**
     * Test that max_tokens calculation scales appropriately with review count.
     */
    public function test_max_tokens_scales_with_review_count()
    {
        $provider = new DeepSeekProvider();

        $tokens10 = $provider->getOptimizedMaxTokens(10);
        $tokens50 = $provider->getOptimizedMaxTokens(50);
        $tokens100 = $provider->getOptimizedMaxTokens(100);

        // Tokens should increase with review count (up to the limit)
        $this->assertGreaterThanOrEqual($tokens10, $tokens50);
        $this->assertGreaterThanOrEqual($tokens50, $tokens100);

        // But should have reasonable minimums for explanations
        $this->assertGreaterThanOrEqual(2500, $tokens10);
        $this->assertGreaterThanOrEqual(2500, $tokens50);
        $this->assertGreaterThanOrEqual(2500, $tokens100);
    }
}
