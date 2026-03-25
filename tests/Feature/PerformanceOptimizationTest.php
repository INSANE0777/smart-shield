<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\Amazon\AmazonFetchService;
use App\Services\OpenAIService;
use App\Services\ReviewAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_optimized_analysis_performance()
    {
        // Mock the Amazon fetch service to avoid external API calls
        $this->mock(AmazonFetchService::class, function ($mock) {
            $asinData = AsinData::create([
                'asin'          => 'B08TX7Q9JT',
                'country'       => 'us',
                'reviews'       => json_encode($this->generateMockReviews(100)),
                'openai_result' => json_encode(['detailed_scores' => []]),
                'status'        => 'completed',
            ]);

            $mock->shouldReceive('fetchReviewsAndSave')
                 ->andReturn($asinData);
        });

        // Mock OpenAI API responses for optimized processing
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $this->generateMockOpenAIResponse(100),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analysisService = app(ReviewAnalysisService::class);

        // Measure analysis time
        $startTime = microtime(true);

        $result = $analysisService->analyzeProduct('https://www.amazon.com/dp/B08TX7Q9JT');

        $endTime = microtime(true);
        $duration = ($endTime - $startTime);

        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('fake_percentage', $result);
        $this->assertArrayHasKey('amazon_rating', $result);
        $this->assertArrayHasKey('adjusted_rating', $result);
        $this->assertArrayHasKey('grade', $result);
        $this->assertArrayHasKey('explanation', $result);

        // Performance assertion - should complete in under 10 seconds with mocked responses
        $this->assertLessThan(10, $duration, 'Analysis should complete in under 10 seconds with optimizations');

        // Verify the result makes sense
        $this->assertGreaterThanOrEqual(0, $result['fake_percentage']);
        $this->assertLessThanOrEqual(100, $result['fake_percentage']);
        $this->assertGreaterThanOrEqual(0, $result['amazon_rating']);
        $this->assertLessThanOrEqual(5, $result['amazon_rating']);
    }

    public function test_parallel_processing_with_large_dataset()
    {
        // Test that parallel processing is triggered for large datasets
        $reviews = $this->generateMockReviews(60); // Above the parallel threshold

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $this->generateMockOpenAIResponse(25), // Chunk size
                        ],
                    ],
                ],
            ], 200),
        ]);

        $openAIService = app(OpenAIService::class);

        $startTime = microtime(true);
        $result = $openAIService->analyzeReviews($reviews);
        $endTime = microtime(true);

        $duration = ($endTime - $startTime);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);

        // With parallel processing, this should be faster than sequential processing
        $this->assertLessThan(30, $duration, 'Parallel processing should complete faster');
    }

    public function test_optimized_prompt_reduces_token_usage()
    {
        $reviews = $this->generateMockReviews(10);

        $openAIService = app(OpenAIService::class);

        // Use reflection to test the optimized prompt method
        $reflection = new \ReflectionClass($openAIService);
        $method = $reflection->getMethod('buildOptimizedPrompt');
        $method->setAccessible(true);

        $optimizedPrompt = $method->invoke($openAIService, $reviews);

        // Verify prompt is concise but contains essential information
        $this->assertLessThan(5000, strlen($optimizedPrompt), 'Optimized prompt should be under 5000 characters for 10 reviews');
        $this->assertStringContainsString('Score each review 0-100', $optimizedPrompt);
        $this->assertStringContainsString('JSON:', $optimizedPrompt);

        // Verify all review IDs are present
        foreach ($reviews as $review) {
            $this->assertStringContainsString("ID:{$review['id']}", $optimizedPrompt);
        }
    }

    public function test_asin_format_validation()
    {
        // Simple test to verify ASIN format validation is fast
        // without Amazon server-side validation, processing should be quick

        $reflection = new \ReflectionClass(AmazonFetchService::class);
        $method = $reflection->getMethod('isValidAsinFormat');
        $method->setAccessible(true);

        $service = new AmazonFetchService();

        $startTime = microtime(true);

        // Test valid ASIN format
        $validResult = $method->invoke($service, 'B08TX7Q9JT');
        $this->assertTrue($validResult);

        // Test invalid ASIN format (not 10 characters)
        $invalidResult = $method->invoke($service, 'INVALID');
        $this->assertFalse($invalidResult);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime);

        // Format validation should be instant
        $this->assertLessThan(0.1, $duration, 'ASIN format validation should be instant');
    }

    public function test_calculation_optimization()
    {
        // Create test data with many reviews
        $reviews = $this->generateMockReviews(100);
        $detailedScores = [];

        foreach ($reviews as $review) {
            $detailedScores[$review['id']] = rand(0, 100);
        }

        $asinData = AsinData::create([
            'asin'          => 'B08TX7Q9JT',
            'country'       => 'us',
            'reviews'       => $reviews,
            'openai_result' => ['detailed_scores' => $detailedScores],
        ]);

        $analysisService = app(ReviewAnalysisService::class);

        $startTime = microtime(true);
        $result = $analysisService->calculateFinalMetrics($asinData);
        $endTime = microtime(true);

        $duration = ($endTime - $startTime);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('fake_percentage', $result);
        $this->assertLessThan(2, $duration, 'Calculation should complete in under 2 seconds');
    }

    private function generateMockReviews(int $count): array
    {
        $reviews = [];

        for ($i = 0; $i < $count; $i++) {
            $reviews[] = [
                'id'           => "R{$i}TESTID",
                'rating'       => rand(1, 5),
                'review_title' => "Test Review Title {$i}",
                'review_text'  => "This is a test review text for review {$i}. It contains some details about the product.",
                'author_name'  => "Test Author {$i}",
                'meta_data'    => [
                    'verified_purchase' => rand(0, 1) === 1,
                    'is_vine_voice'     => rand(0, 1) === 1,
                ],
            ];
        }

        return $reviews;
    }

    private function generateMockOpenAIResponse(int $count): string
    {
        $scores = [];

        for ($i = 0; $i < $count; $i++) {
            $scores[] = [
                'id'    => "R{$i}TESTID",
                'score' => rand(0, 100),
            ];
        }

        return json_encode($scores);
    }
}
