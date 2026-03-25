<?php

namespace Tests\Feature;

use App\Jobs\TriggerBrightDataScraping;
use App\Services\Amazon\BrightDataScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobChainIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $mockHandler;
    private BrightDataScraperService $service;

    protected function setUp(): void
    {
        parent::setUp();

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
    public function brightdata_service_dispatches_async_job_chain_when_enabled()
    {
        Queue::fake();

        putenv('ANALYSIS_ASYNC_ENABLED=true');
        config(['analysis.async_enabled' => true]);

        // Ensure we're not detected as running in queue worker
        unset($_SERVER['argv']);

        // Mock successful BrightData trigger response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_job_12345',
        ])));

        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should create AsinData and dispatch job
        $this->assertEquals('B0TEST12345', $result->asin);

        // Should dispatch the trigger job
        Queue::assertPushed(TriggerBrightDataScraping::class, function ($job) {
            return $job->asin === 'B0TEST12345' && $job->country === 'us';
        });
    }

    #[Test]
    public function brightdata_service_uses_sync_mode_when_in_queue_worker()
    {
        Queue::fake();

        putenv('ANALYSIS_ASYNC_ENABLED=true'); // Enable async globally

        // Simulate being in a queue worker by setting argv to include queue:work
        $_SERVER['argv'] = ['artisan', 'queue:work'];

        // Mock successful BrightData responses for sync mode
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_job_12345',
        ])));
        // Add multiple progress responses in case of polling
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'running',
            'records' => 0,
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 5,
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            [
                'review_id'            => 'R123',
                'review_text'          => 'Great product!',
                'rating'               => 5,
                'is_verified'          => true,
                'product_name'         => 'Test Product',
                'product_rating_count' => 100,
                'product_image_url'    => 'https://example.com/image.jpg',
            ],
            [
                'review_id'   => 'R124',
                'review_text' => 'Good value',
                'rating'      => 4,
                'is_verified' => false,
            ],
        ])));

        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should have processed synchronously (no jobs dispatched)
        $this->assertEquals('B0TEST12345', $result->asin);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function brightdata_service_uses_sync_mode_when_async_disabled()
    {
        Queue::fake();

        // Set environment to ensure async is disabled
        config(['analysis.async_enabled' => false]);
        putenv('ANALYSIS_ASYNC_ENABLED=false');
        putenv('APP_ENV=local'); // Ensure not production

        // Mock successful BrightData responses for sync mode
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_job_12345',
        ])));
        // Add multiple progress responses in case of polling
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'running',
            'records' => 0,
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 5,
        ])));
        $this->mockHandler->append(new Response(200, [], json_encode([
            [
                'review_id'            => 'R123',
                'review_text'          => 'Great product!',
                'rating'               => 5,
                'is_verified'          => true,
                'product_name'         => 'Test Product',
                'product_rating_count' => 100,
                'product_image_url'    => 'https://example.com/image.jpg',
            ],
            [
                'review_id'   => 'R124',
                'review_text' => 'Good value',
                'rating'      => 4,
                'is_verified' => false,
            ],
        ])));

        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should have processed synchronously
        $this->assertEquals('B0TEST12345', $result->asin);
        Queue::assertNothingPushed();
    }
}
