<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\Amazon\BrightDataScraperService;
use App\Services\ReviewAnalysisService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for the full review analysis service chain.
 *
 * These tests exercise the complete flow:
 * ReviewAnalysisService -> ReviewFetchingService -> BrightDataScraperService
 *
 * This addresses the gap where individual unit tests passed but the integration failed.
 */
class ReviewAnalysisServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Set test environment variables
        putenv('BRIGHTDATA_SCRAPER_API=test_api_key_12345');
        putenv('BRIGHTDATA_DATASET_ID=gd_test_dataset');
        putenv('AMAZON_REVIEW_SERVICE=brightdata'); // Ensure BrightData is used

        // Set up mock HTTP client for BrightData service
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Replace the BrightData service with our mocked version
        $this->app->singleton(BrightDataScraperService::class, function () use ($mockClient) {
            return new BrightDataScraperService(
                httpClient: $mockClient,
                apiKey: 'test_api_key_12345',
                datasetId: 'gd_test_dataset',
                baseUrl: 'https://api.brightdata.com/datasets/v3',
                pollInterval: 0,
                maxAttempts: 3
            );
        });
    }

    #[Test]
    public function it_handles_empty_brightdata_results_gracefully_through_full_service_chain()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock BrightData returning empty results (valid scenario)
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_integration_empty',
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([])));

        $analysisService = app(ReviewAnalysisService::class);

        // This should now handle gracefully by creating AsinData with empty reviews
        $result = $analysisService->fetchReviews('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Verify AsinData was created with empty reviews (valid scenario)
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertEquals('pending_analysis', $result->status);
        $this->assertEquals([], $result->getReviewsArray());
    }

    #[Test]
    public function it_successfully_processes_reviews_through_full_service_chain()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock concurrent job check (getJobsByStatus call)
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful BrightData responses
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_integration_success',
        ])));

        // Add multiple progress responses in case of polling
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'running',
            'records' => 0,
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 25,
        ])));

        // Mock successful data fetch with actual reviews in raw BrightData format
        $mockReviewData = [
            [
                'review_id'            => 'R123',
                'rating'               => 5,
                'review_text'          => 'Great product! Highly recommend.',
                'is_verified'          => true,
                'product_name'         => 'Test Product',
                'product_rating_count' => 100,
                'product_image_url'    => 'https://example.com/image.jpg',
            ],
            [
                'review_id'   => 'R124',
                'rating'      => 4,
                'review_text' => 'Good quality, fast shipping.',
                'is_verified' => true,
            ],
            [
                'review_id'   => 'R125',
                'rating'      => 1,
                'review_text' => 'Terrible fake product, avoid!',
                'is_verified' => false,
            ],
        ];
        $this->mockHandler->append(new Response(200, [], json_encode($mockReviewData)));

        $analysisService = app(ReviewAnalysisService::class);
        $result = $analysisService->fetchReviews('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should successfully create AsinData through the full chain
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertCount(3, $result->getReviewsArray());

        // Verify the reviews were properly stored
        $reviews = $result->getReviewsArray();
        $this->assertEquals('R123', $reviews[0]['id']);
        $this->assertEquals('Great product! Highly recommend.', $reviews[0]['text']);
        $this->assertTrue($reviews[0]['meta_data']['verified_purchase']);
    }

    #[Test]
    public function it_handles_products_with_no_reviews_gracefully()
    {
        // Create an AsinData record with no reviews (simulates products without reviews)
        $asinData = AsinData::factory()->create([
            'country' => 'us',
            'reviews' => json_encode([]), // Empty reviews array
            'status'  => 'pending_analysis',
        ]);

        $analysisService = app(ReviewAnalysisService::class);

        // This should now handle gracefully by setting default analysis results
        $result = $analysisService->analyzeWithLLM($asinData);

        // Verify the product was marked as completed with default analysis using centralized policy
        $this->assertEquals('completed', $result->status);
        $this->assertNotNull($result->openai_result);

        $openaiResult = $result->openai_result;
        if (is_string($openaiResult)) {
            $openaiResult = json_decode($openaiResult, true);
        }

        // Verify default analysis structure from ProductAnalysisPolicy
        $this->assertEquals([], $openaiResult['detailed_scores']);
        $this->assertEquals('system', $openaiResult['analysis_provider']);
        $this->assertEquals(0.0, $openaiResult['total_cost']);

        // Verify default metrics were applied
        $this->assertEquals(0, $result->fake_percentage);
        $this->assertEquals('U', $result->grade);
        $this->assertEquals('No reviews could be extracted for analysis. This may occur when Amazon is actively blocking automated review collection, implementing anti-bot measures, or when the product genuinely has no customer reviews. Our system will automatically retry this analysis periodically to check if reviews become available.', $result->explanation);
        $this->assertEquals(0.0, $result->amazon_rating);
        $this->assertEquals(0.0, $result->adjusted_rating);

        $this->assertNotNull($result->first_analyzed_at);
        $this->assertNotNull($result->last_analyzed_at);
    }

    #[Test]
    public function it_creates_asin_data_even_with_empty_reviews_from_brightdata()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock BrightData returning empty results (valid scenario)
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_empty_creation',
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([])));

        $analysisService = app(ReviewAnalysisService::class);

        // This should now create AsinData even with empty reviews
        $result = $analysisService->fetchReviews('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Verify AsinData record was created by the ReviewFetchingService
        $this->assertDatabaseHas('asin_data', [
            'asin'    => 'B0TEST12345',
            'country' => 'us',
            'status'  => 'pending_analysis',
        ]);

        // Verify the returned object
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals([], $result->getReviewsArray());
    }

    #[Test]
    public function it_uses_brightdata_service_when_amazon_review_service_is_configured()
    {
        // Verify that the service factory creates BrightData service
        $factoryService = \App\Services\Amazon\AmazonReviewServiceFactory::create();
        $this->assertInstanceOf(BrightDataScraperService::class, $factoryService);

        // Verify that ReviewFetchingService uses the factory
        $reviewFetchingService = app(\App\Services\Amazon\ReviewFetchingService::class);

        // Use reflection to check the internal service
        $reflection = new \ReflectionClass($reviewFetchingService);
        $property = $reflection->getProperty('fetchService');
        $property->setAccessible(true);
        $internalService = $property->getValue($reviewFetchingService);

        $this->assertInstanceOf(BrightDataScraperService::class, $internalService);
    }
}
