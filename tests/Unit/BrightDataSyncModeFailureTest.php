<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\Amazon\BrightDataScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CRITICAL: Tests for BrightData sync mode graceful handling of empty results.
 *
 * This test class verifies that empty BrightData results are handled gracefully
 * instead of throwing exceptions, which was the root cause of production failures.
 */
class BrightDataSyncModeFailureTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $mockHandler;
    private BrightDataScraperService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Set test environment variables
        putenv('BRIGHTDATA_SCRAPER_API=test_api_key_12345');
        putenv('BRIGHTDATA_DATASET_ID=gd_test_dataset');

        // Set up mock HTTP client
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Create service instance with proper dependency injection for testing
        $this->service = new BrightDataScraperService(
            httpClient: $mockClient,
            apiKey: 'test_api_key_12345',
            datasetId: 'gd_test_dataset',
            baseUrl: 'https://api.brightdata.com/datasets/v3',
            pollInterval: 0, // No sleep in tests
            maxAttempts: 3   // Fewer attempts in tests
        );
    }

    #[Test]
    public function it_handles_empty_brightdata_results_gracefully_in_sync_mode()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock BrightData API responses that return empty results
        // This simulates the exact scenario that was happening in production

        // 1. Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_empty_results',
        ])));

        // 2. Mock job polling - job completes but with 0 results
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,  // This is valid - product has no reviews
        ])));

        // 3. Mock data fetch - returns empty array (valid scenario)
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // This should now handle gracefully by creating AsinData with empty reviews
        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Verify AsinData was created with empty reviews (valid scenario)
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertEquals([], $result->getReviewsArray());
    }

    #[Test]
    public function it_handles_brightdata_job_failures_gracefully_in_sync_mode()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock BrightData API failure - job trigger fails
        $this->mockHandler->append(new Response(500, [], json_encode([
            'error' => 'Internal server error',
        ])));

        // This should handle the failure gracefully and return empty results
        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should create AsinData with empty reviews when BrightData fails
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertEquals([], $result->getReviewsArray());
    }

    #[Test]
    public function it_handles_brightdata_timeouts_gracefully_in_sync_mode()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock BrightData job trigger
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_timeout',
        ])));

        // Mock job polling - always returns "running" (simulates timeout)
        for ($i = 0; $i < 3; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'status'  => 'running',
                'records' => 0,
            ])));
        }

        // This should handle timeout gracefully and return empty results
        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should create AsinData with empty reviews when BrightData times out
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertEquals([], $result->getReviewsArray());
    }

    #[Test]
    public function it_creates_asin_data_even_with_empty_brightdata_results_in_sync_mode()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock BrightData returning empty results (valid scenario)
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_empty_data',
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // This should now create AsinData even with empty reviews
        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Verify AsinData record was created
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
    public function it_successfully_creates_asin_data_when_brightdata_returns_reviews_in_sync_mode()
    {
        // Simulate being in a queue worker (forces sync mode)
        $_SERVER['argv'] = ['artisan', 'queue:work', '--queue=analysis'];

        // Mock concurrent job check (getJobsByStatus call)
        $this->mockHandler->append(new Response(200, [], json_encode([
            // Return empty array to indicate no running jobs
        ])));

        // Mock successful BrightData responses
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_success',
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 25,
        ])));

        // Mock successful data fetch with actual reviews
        $mockReviewData = [
            [
                'review_id'         => 'R123',
                'rating'            => 5,
                'review_text'       => 'Great product!',
                'verified_purchase' => true,
                'product_name'      => 'Test Product',
                'product_image_url' => 'https://example.com/image.jpg',
            ],
        ];
        $this->mockHandler->append(new Response(200, [], json_encode($mockReviewData)));

        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should successfully create AsinData with reviews
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertGreaterThan(0, count($result->getReviewsArray()));

        // Verify database record was created
        $this->assertDatabaseHas('asin_data', [
            'asin'    => 'B0TEST12345',
            'country' => 'us',
            'status'  => 'pending_analysis',
        ]);
    }
}
