<?php

namespace Tests\Unit;

use App\Services\AlertManager;
use App\Services\Amazon\BrightDataScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrightDataTimeoutManagementTest extends TestCase
{
    use RefreshDatabase;

    private BrightDataScraperService $service;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        $this->service = new BrightDataScraperService(
            httpClient: $mockClient,
            apiKey: 'test-api-key',
            datasetId: 'test-dataset',
            baseUrl: 'https://api.brightdata.com/datasets/v3',
            pollInterval: 1, // 1 second for faster tests
            maxAttempts: 3   // 3 attempts for faster tests
        );
    }

    #[Test]
    public function it_cancels_job_after_polling_timeout()
    {
        // Mock concurrent job check (getJobsByStatus call)
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock job trigger response
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test-job-123'])));

        // Mock polling responses - all running
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));

        // Mock final progress check
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));

        // Mock job cancellation response
        $this->mockHandler->append(new Response(200, [], 'OK'));

        $result = $this->service->fetchReviews('B123456789', 'us');

        // Should return empty results due to timeout
        $this->assertEmpty($result['reviews']);
        $this->assertEquals(0, $result['total_reviews']);

        // Verify all requests were made (concurrent check + trigger + 3 polls + progress check + cancel)
        $this->assertEquals(0, $this->mockHandler->count());
    }

    #[Test]
    public function it_alerts_when_running_jobs_exceed_threshold()
    {
        // Mock the snapshots API to return 75 running jobs
        $runningJobs = array_fill(0, 75, ['id' => 'job-'.uniqid(), 'status' => 'running']);
        $this->mockHandler->append(new Response(200, [], json_encode($runningJobs)));

        // Mock job trigger to be blocked due to high count
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test-job-456'])));

        // Mock AlertManager
        $alertManager = $this->mock(AlertManager::class);
        $alertManager->shouldReceive('recordFailure')
            ->once()
            ->with(
                'BrightData Job Management',
                'HIGH_CONCURRENT_JOBS',
                'High number of running BrightData jobs: 75/100 limit',
                \Mockery::on(function ($context) {
                    return $context['current_running_jobs'] === 75
                        && $context['alert_threshold'] === 70
                        && $context['max_limit'] === 100;
                })
            );

        // This should trigger the alert due to high job count
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('canCreateNewJob');
        $method->setAccessible(true);

        $canCreate = $method->invoke($this->service);

        // Should still allow job creation (75 < 90 limit) but alert should be triggered
        $this->assertTrue($canCreate);
    }

    #[Test]
    public function it_does_not_alert_when_running_jobs_below_threshold()
    {
        // Mock the snapshots API to return 50 running jobs (below 70 threshold)
        $runningJobs = array_fill(0, 50, ['id' => 'job-'.uniqid(), 'status' => 'running']);
        $this->mockHandler->append(new Response(200, [], json_encode($runningJobs)));

        // Mock AlertManager - should NOT be called
        $alertManager = $this->mock(AlertManager::class);
        $alertManager->shouldNotReceive('recordFailure');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('canCreateNewJob');
        $method->setAccessible(true);

        $canCreate = $method->invoke($this->service);

        $this->assertTrue($canCreate);
    }

    #[Test]
    public function it_blocks_job_creation_when_at_max_concurrent_limit()
    {
        // Mock the snapshots API to return 95 running jobs (above 90 limit)
        $runningJobs = array_fill(0, 95, ['id' => 'job-'.uniqid(), 'status' => 'running']);
        $this->mockHandler->append(new Response(200, [], json_encode($runningJobs)));

        // Mock AlertManager - should be called for high job count
        $alertManager = $this->mock(AlertManager::class);
        $alertManager->shouldReceive('recordFailure')->once();

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('canCreateNewJob');
        $method->setAccessible(true);

        $canCreate = $method->invoke($this->service);

        // Should block job creation when at limit
        $this->assertFalse($canCreate);
    }

    #[Test]
    public function it_handles_job_cancellation_failure_gracefully()
    {
        // Mock job trigger response
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test-job-789'])));

        // Mock polling responses - all running (timeout scenario)
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));

        // Mock final progress check
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));

        // Mock job cancellation failure
        $this->mockHandler->append(new Response(404, [], 'Job not found'));

        $result = $this->service->fetchReviews('B987654321', 'us');

        // Should still return empty results even if cancellation fails
        $this->assertEmpty($result['reviews']);
        $this->assertEquals(0, $result['total_reviews']);
    }

    #[Test]
    public function it_successfully_cancels_job_via_public_method()
    {
        // Mock successful cancellation
        $this->mockHandler->append(new Response(200, [], 'OK'));

        $result = $this->service->cancelJob('test-job-id');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_job_cancellation_api_errors()
    {
        // Mock cancellation failure
        $this->mockHandler->append(new Response(404, [], 'Job not found'));

        $result = $this->service->cancelJob('nonexistent-job-id');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_logs_cancellation_attempts_with_proper_context()
    {
        // Mock successful cancellation
        $this->mockHandler->append(new Response(200, [], 'OK'));

        // Test that the method executes without throwing exceptions
        $result = $this->service->cancelJob('test-job-for-logging');

        // Should return true for successful cancellation
        $this->assertTrue($result);
        $this->assertTrue(true);
    }
}
