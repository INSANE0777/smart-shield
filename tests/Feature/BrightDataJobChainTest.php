<?php

namespace Tests\Feature;

use App\Jobs\CheckBrightDataProgress;
use App\Jobs\ProcessBrightDataResults;
use App\Jobs\TriggerBrightDataScraping;
use App\Models\AsinData;
use App\Services\Amazon\BrightDataScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrightDataJobChainTest extends TestCase
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
        putenv('ANALYSIS_ASYNC_ENABLED=false'); // Force sync mode for testing

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
    public function it_dispatches_job_chain_when_async_enabled()
    {
        Queue::fake();

        // Enable async mode
        putenv('ANALYSIS_ASYNC_ENABLED=true');
        config(['analysis.async_enabled' => true]);

        // Ensure we're not detected as running in queue worker
        unset($_SERVER['argv']);

        // Mock successful BrightData trigger response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_job_12345',
        ])));

        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        // Should create AsinData record
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('processing', $result->status);

        // Should dispatch the trigger job
        Queue::assertPushed(TriggerBrightDataScraping::class, function ($job) {
            return $job->asin === 'B0TEST12345' && $job->country === 'us';
        });
    }

    #[Test]
    public function trigger_job_dispatches_progress_check_job()
    {
        Queue::fake();

        // Mock concurrent job check (getJobsByStatus call)
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'snapshot_id' => 's_test_trigger',
        ])));

        $job = new TriggerBrightDataScraping('B0TEST12345', 'us', $this->service);
        $job->handle();

        // Should dispatch the progress check job with delay
        Queue::assertPushed(CheckBrightDataProgress::class, function ($job) {
            return $job->asin === 'B0TEST12345' &&
                   $job->country === 'us' &&
                   $job->jobId === 's_test_trigger' &&
                   $job->attempt === 1;
        });
    }

    #[Test]
    public function progress_check_job_chains_to_results_when_ready()
    {
        Queue::fake();

        // Mock progress check response showing job is ready
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'     => 'ready',
            'total_rows' => 25,
        ])));

        $job = new CheckBrightDataProgress('B0TEST12345', 'us', 's_test_ready', 1, $this->service);
        $job->handle();

        // Should dispatch the results processing job
        Queue::assertPushed(ProcessBrightDataResults::class, function ($job) {
            return $job->asin === 'B0TEST12345' &&
                   $job->country === 'us' &&
                   $job->jobId === 's_test_ready';
        });
    }

    #[Test]
    public function progress_check_job_reschedules_when_running()
    {
        Queue::fake();

        // Mock progress check response showing job is still running
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'running',
            'message' => 'Still processing...',
        ])));

        $job = new CheckBrightDataProgress('B0TEST12345', 'us', 's_test_running', 1, $this->service);
        $job->handle();

        // Should reschedule another progress check with incremented attempt
        Queue::assertPushed(CheckBrightDataProgress::class, function ($job) {
            return $job->asin === 'B0TEST12345' &&
                   $job->country === 'us' &&
                   $job->jobId === 's_test_running' &&
                   $job->attempt === 2;
        });

        // Should not dispatch results processing
        Queue::assertNotPushed(ProcessBrightDataResults::class);
    }

    #[Test]
    public function results_processing_job_saves_data_correctly()
    {
        // Mock data fetch response
        $mockReviewData = $this->createMockBrightDataResponse();
        $this->mockHandler->append(new Response(200, [], json_encode($mockReviewData)));

        $job = new ProcessBrightDataResults('B0TEST12345', 'us', 's_test_results', $this->service);
        $job->handle();

        // Check that data was saved to database
        $asinData = AsinData::where('asin', 'B0TEST12345')->first();
        $this->assertNotNull($asinData);
        $this->assertEquals('Test Product Name', $asinData->product_title);
        $this->assertEquals('pending_analysis', $asinData->status);

        $reviews = json_decode($asinData->reviews, true);
        $this->assertCount(3, $reviews);
    }

    private function createMockBrightDataResponse(): array
    {
        return [
            [
                'url'                  => 'https://www.amazon.com/dp/B0TEST12345/',
                'product_name'         => 'Test Product Name',
                'product_rating'       => 4.6,
                'product_rating_count' => 166807,
                'rating'               => 5,
                'author_name'          => 'Test Author 1',
                'asin'                 => 'B0TEST12345',
                'review_header'        => 'Excellent product!',
                'review_id'            => 'R1TEST123',
                'review_text'          => 'This is an amazing product that works exactly as described.',
                'author_id'            => 'ATEST123',
                'badge'                => 'Verified Purchase',
                'review_posted_date'   => 'July 15, 2025',
                'review_country'       => 'United States',
                'helpful_count'        => 25,
                'is_amazon_vine'       => false,
                'is_verified'          => true,
            ],
            [
                'url'                  => 'https://www.amazon.com/dp/B0TEST12345/',
                'product_name'         => 'Test Product Name',
                'product_rating'       => 4.6,
                'product_rating_count' => 166807,
                'rating'               => 4,
                'author_name'          => 'Test Author 2',
                'asin'                 => 'B0TEST12345',
                'review_header'        => 'Good value',
                'review_id'            => 'R2TEST456',
                'review_text'          => 'Solid product for the price point.',
                'author_id'            => 'ATEST456',
                'badge'                => 'Verified Purchase',
                'review_posted_date'   => 'July 10, 2025',
                'review_country'       => 'United States',
                'helpful_count'        => 12,
                'is_amazon_vine'       => false,
                'is_verified'          => true,
            ],
            [
                'url'                  => 'https://www.amazon.com/dp/B0TEST12345/',
                'product_name'         => 'Test Product Name',
                'product_rating'       => 4.6,
                'product_rating_count' => 166807,
                'rating'               => 3,
                'author_name'          => 'Test Author 3',
                'asin'                 => 'B0TEST12345',
                'review_header'        => 'Average product',
                'review_id'            => 'R3TEST789',
                'review_text'          => 'Works as expected but nothing special.',
                'author_id'            => 'ATEST789',
                'badge'                => 'Verified Purchase',
                'review_posted_date'   => 'July 5, 2025',
                'review_country'       => 'United States',
                'helpful_count'        => 5,
                'is_amazon_vine'       => true,
                'is_verified'          => true,
            ],
        ];
    }
}
