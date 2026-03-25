<?php

namespace Tests\Unit;

use App\Services\Amazon\BrightDataScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for BrightData polling edge cases that could cause undefined variable errors.
 *
 * These tests specifically target scenarios that were missed in the original test suite:
 * 1. Unknown/unexpected status values during polling
 * 2. RequestException during polling attempts
 * 3. Non-zero pollInterval values to catch undefined variable bugs
 */
class BrightDataPollingEdgeCasesTest extends TestCase
{
    private BrightDataScraperService $service;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        // CRITICAL: Use non-zero pollInterval to catch undefined variable bugs
        $this->service = new BrightDataScraperService(
            httpClient: $httpClient,
            apiKey: 'test_api_key',
            datasetId: 'test_dataset_id',
            baseUrl: 'https://api.brightdata.com/datasets/v3',
            pollInterval: 1, // Non-zero to trigger the bug
            maxAttempts: 2   // Low for faster tests
        );
    }

    #[Test]
    public function it_handles_unknown_status_during_polling_without_undefined_variable_error()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock unknown status (this triggers the line 508 bug path)
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'unknown_status', // This is the key - not 'running', 'ready', 'failed'
            'records' => 0,
        ])));

        // Mock second polling attempt with completion
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,
        ])));

        // Mock empty data fetch
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // This should not throw "Undefined variable $pollInterval" error
        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertEquals([], $result['reviews']);
    }

    #[Test]
    public function it_handles_multiple_unknown_statuses_during_polling()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock multiple unknown statuses
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'initializing',
            'records' => 0,
        ])));

        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'pending',
            'records' => 0,
        ])));

        // Final status check after max attempts
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'timeout',
            'records' => 0,
        ])));

        // This should handle multiple unknown statuses without undefined variable errors
        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertEquals([], $result['reviews']);
    }

    #[Test]
    public function it_handles_request_exception_during_polling_without_undefined_variable_error()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock RequestException during polling (this triggers the line 530 bug path)
        $this->mockHandler->append(new RequestException(
            'Connection timeout',
            new Request('GET', 'test')
        ));

        // Mock second polling attempt with completion
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,
        ])));

        // Mock empty data fetch
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // This should not throw "Undefined variable $pollInterval" error
        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertEquals([], $result['reviews']);
    }

    #[Test]
    public function it_handles_multiple_request_exceptions_during_polling()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock multiple RequestExceptions
        $this->mockHandler->append(new RequestException(
            'Network error',
            new Request('GET', 'test')
        ));

        $this->mockHandler->append(new RequestException(
            'Timeout error',
            new Request('GET', 'test')
        ));

        // Final status check after max attempts
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'failed',
            'records' => 0,
        ])));

        // This should handle multiple exceptions without undefined variable errors
        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertEquals([], $result['reviews']);
    }

    #[Test]
    public function it_handles_mixed_unknown_status_and_exceptions()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mix of unknown status and exception
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'weird_status',
            'records' => 0,
        ])));

        $this->mockHandler->append(new RequestException(
            'API error',
            new Request('GET', 'test')
        ));

        // Final status check
        $this->mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,
        ])));

        // Mock empty data fetch
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // This tests both bug paths in the same execution
        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertEquals([], $result['reviews']);
    }

    #[Test]
    public function it_uses_correct_poll_interval_property_in_all_scenarios()
    {
        // Create service with specific pollInterval to verify it's used correctly
        $mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $service = new BrightDataScraperService(
            httpClient: $httpClient,
            apiKey: 'test_api_key',
            datasetId: 'test_dataset_id',
            baseUrl: 'https://api.brightdata.com/datasets/v3',
            pollInterval: 5, // Specific value to test
            maxAttempts: 1
        );

        // Mock concurrent job check
        $mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock unknown status to trigger sleep($this->pollInterval) path
        $mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'unknown',
            'records' => 0,
        ])));

        // Final status check
        $mockHandler->append(new Response(200, [], json_encode([
            'status'  => 'ready',
            'records' => 0,
        ])));

        // Mock empty data fetch
        $mockHandler->append(new Response(200, [], json_encode([])));

        // This should work without undefined variable errors
        $result = $service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertEquals([], $result['reviews']);
    }
}
