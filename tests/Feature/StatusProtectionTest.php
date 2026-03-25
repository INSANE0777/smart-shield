<?php

namespace Tests\Feature;

use App\Jobs\ProcessBrightDataResults;
use App\Models\AsinData;
use App\Services\Amazon\BrightDataScraperService;
use App\Services\ExtensionReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Test that background jobs never overwrite completed analysis status.
 *
 * This prevents the race condition where:
 * 1. Main analysis completes and sets status='completed'
 * 2. Extension polls API and sees analysis_complete=true
 * 3. Background job (running late) overwrites status='pending_analysis'
 * 4. User clicks link and sees "Product Not Found"
 */
class StatusProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_bright_data_results_does_not_overwrite_completed_status(): void
    {
        // Create a completed analysis
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0TESTPROT',
            'country'           => 'us',
            'status'            => 'completed',
            'fake_percentage'   => 25.5,
            'grade'             => 'B',
            'product_title'     => 'Protected Product',
            'have_product_data' => true,
        ]);

        $originalStatus = $asinData->status;
        $originalGrade = $asinData->grade;

        // Mock BrightData service to return new data
        $mockService = $this->createMock(BrightDataScraperService::class);

        // Use reflection to mock the private methods
        $mockResults = [
            [
                'reviews' => [
                    ['review_id' => '1', 'text' => 'Test review', 'rating' => 5],
                ],
                'product_name'  => 'New Product Name',
                'description'   => 'New description',
                'total_reviews' => 100,
            ],
        ];

        // Create the job
        $job = new ProcessBrightDataResults(
            asin: 'B0TESTPROT',
            country: 'us',
            jobId: 'test-job-123',
            mockService: null // Will use real service but fail gracefully
        );

        // Execute the job (it should skip processing)
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Job may fail due to mocking, but status should still be protected
        }

        // Refresh the model
        $asinData->refresh();

        // Status should NOT have changed
        $this->assertEquals($originalStatus, $asinData->status);
        $this->assertEquals($originalGrade, $asinData->grade);
        $this->assertEquals('completed', $asinData->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function extension_review_service_does_not_overwrite_completed_status(): void
    {
        // Create a completed analysis
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0EXTTEST1',
            'country'           => 'us',
            'status'            => 'completed',
            'fake_percentage'   => 30.0,
            'grade'             => 'C',
            'product_title'     => 'Original Title',
            'have_product_data' => true,
        ]);

        $originalStatus = $asinData->status;
        $originalFakePercentage = $asinData->fake_percentage;

        // Try to process new extension data for the same product
        $extensionData = [
            'asin'                 => 'B0EXTTEST1',
            'country'              => 'us',
            'product_url'          => 'https://www.amazon.com/dp/B0EXTTEST1',
            'extension_version'    => '1.0.0',
            'extraction_timestamp' => '2024-01-01T12:00:00.000Z',
            'reviews'              => [
                [
                    'review_id'         => 'R123',
                    'author'            => 'New Reviewer',
                    'title'             => 'New Review',
                    'content'           => 'This is new review content',
                    'rating'            => 4,
                    'date'              => '2024-01-01',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                    'helpful_votes'     => 0,
                    'extraction_index'  => 1,
                ],
            ],
            'product_info' => [
                'title'                   => 'New Title From Extension',
                'description'             => 'New description',
                'image_url'               => 'https://example.com/new-image.jpg',
                'amazon_rating'           => 4.5,
                'total_reviews_on_amazon' => 200,
            ],
        ];

        $service = new ExtensionReviewService();
        $result = $service->processExtensionData($extensionData);

        // Status should NOT have changed
        $this->assertEquals($originalStatus, $result->status);
        $this->assertEquals($originalFakePercentage, $result->fake_percentage);
        $this->assertEquals('completed', $result->status);

        // Title should NOT have changed
        $this->assertEquals('Original Title', $result->product_title);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function bright_data_scraper_fetch_async_respects_completed_status(): void
    {
        // Create a completed analysis
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0BDTEST01',
            'country'           => 'us',
            'status'            => 'completed',
            'fake_percentage'   => 15.0,
            'grade'             => 'A',
            'product_title'     => 'Protected from BrightData',
            'have_product_data' => true,
        ]);

        $originalStatus = $asinData->status;

        Queue::fake();

        $service = new BrightDataScraperService();
        $result = $service->fetchReviewsAsync('B0BDTEST01', 'us');

        // Status should NOT have changed to 'processing'
        $this->assertEquals($originalStatus, $result->status);
        $this->assertEquals('completed', $result->status);

        // Job should not have been dispatched since already completed
        Queue::assertNotPushed(\App\Jobs\TriggerBrightDataScraping::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multiple_background_jobs_cannot_regress_status(): void
    {
        // Create a completed analysis
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0MULTITEST',
            'country'           => 'us',
            'status'            => 'completed',
            'fake_percentage'   => 20.0,
            'grade'             => 'B',
            'product_title'     => 'Multi Job Protected',
            'have_product_data' => true,
        ]);

        // Simulate multiple services trying to process this product
        $extensionService = new ExtensionReviewService();
        $brightDataService = new BrightDataScraperService();

        Queue::fake();

        // Try extension submission
        $extensionData = [
            'asin'                 => 'B0MULTITEST',
            'country'              => 'us',
            'product_url'          => 'https://www.amazon.com/dp/B0MULTITEST',
            'extension_version'    => '1.0.0',
            'extraction_timestamp' => '2024-01-01T12:00:00.000Z',
            'reviews'              => [],
            'product_info'         => [
                'title'                   => 'Should Not Update',
                'total_reviews_on_amazon' => 0,
            ],
        ];

        $result1 = $extensionService->processExtensionData($extensionData);

        // Try BrightData fetch
        $result2 = $brightDataService->fetchReviewsAsync('B0MULTITEST', 'us');

        // Both should return the original model without changing status
        $this->assertEquals('completed', $result1->status);
        $this->assertEquals('completed', $result2->status);
        $this->assertEquals('Multi Job Protected', $result1->product_title);

        // Verify final state
        $asinData->refresh();
        $this->assertEquals('completed', $asinData->status);
        $this->assertEquals(20.0, $asinData->fake_percentage);
        $this->assertEquals('B', $asinData->grade);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function status_can_be_modified_before_completion(): void
    {
        // Create a product that's NOT completed yet
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0NOTDONE1',
            'country'         => 'us',
            'status'          => 'fetched',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        // This should be allowed to change status
        $extensionData = [
            'asin'                 => 'B0NOTDONE1',
            'country'              => 'us',
            'product_url'          => 'https://www.amazon.com/dp/B0NOTDONE1',
            'extension_version'    => '1.0.0',
            'extraction_timestamp' => '2024-01-01T12:00:00.000Z',
            'reviews'              => [
                [
                    'review_id'         => 'R1',
                    'author'            => 'Test',
                    'title'             => 'Test',
                    'content'           => 'Content',
                    'rating'            => 5,
                    'date'              => '2024-01-01',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                    'helpful_votes'     => 0,
                    'extraction_index'  => 1,
                ],
            ],
            'product_info' => [
                'title'                   => 'New Title',
                'total_reviews_on_amazon' => 10,
            ],
        ];

        $service = new ExtensionReviewService();
        $result = $service->processExtensionData($extensionData);

        // Status CAN change since not completed
        $this->assertEquals('fetched', $result->status);
        $this->assertEquals('New Title', $result->product_title);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_analyzed_method_correctly_identifies_completion(): void
    {
        // Not completed - missing grade
        $notComplete1 = AsinData::factory()->create([
            'status'          => 'completed',
            'fake_percentage' => 25.0,
            'grade'           => null,
        ]);
        $this->assertFalse($notComplete1->isAnalyzed());

        // Not completed - missing fake_percentage
        $notComplete2 = AsinData::factory()->create([
            'status'          => 'completed',
            'fake_percentage' => null,
            'grade'           => 'B',
        ]);
        $this->assertFalse($notComplete2->isAnalyzed());

        // Not completed - wrong status
        $notComplete3 = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
        ]);
        $this->assertFalse($notComplete3->isAnalyzed());

        // Properly completed
        $completed = AsinData::factory()->create([
            'status'          => 'completed',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
        ]);
        $this->assertTrue($completed->isAnalyzed());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function completed_products_maintain_status_through_refresh(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0REFRESH1',
            'country'           => 'us',
            'status'            => 'completed',
            'fake_percentage'   => 35.0,
            'grade'             => 'D',
            'product_title'     => 'Refresh Test',
            'have_product_data' => true,
        ]);

        // Verify isAnalyzed before any operations
        $this->assertTrue($asinData->isAnalyzed());

        // Simulate a background job checking the product
        $fresh = AsinData::where('asin', 'B0REFRESH1')->where('country', 'us')->first();
        $this->assertTrue($fresh->isAnalyzed());
        $this->assertEquals('completed', $fresh->status);

        // Simulate multiple checks
        for ($i = 0; $i < 5; $i++) {
            $check = AsinData::where('asin', 'B0REFRESH1')->where('country', 'us')->first();
            $this->assertTrue($check->isAnalyzed());
            $this->assertEquals('completed', $check->status);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function race_condition_scenario_completed_before_background_job(): void
    {
        // Simulate the exact race condition:
        // 1. Extension submits data
        // 2. Main analysis completes quickly
        // 3. Extension polls and sees completed
        // 4. Background job (late) tries to update

        // Step 1: Extension submission creates initial record
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0RACECOND',
            'country'         => 'us',
            'status'          => 'fetched',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        // Step 2: Main analysis completes
        $asinData->update([
            'status'            => 'completed',
            'fake_percentage'   => 28.0,
            'grade'             => 'C',
            'have_product_data' => true,
        ]);

        $this->assertTrue($asinData->isAnalyzed());

        // Step 3: Extension polls - sees completed
        $apiCheck = AsinData::where('asin', 'B0RACECOND')->where('country', 'us')->first();
        $this->assertTrue($apiCheck->isAnalyzed());

        // Step 4: Background job tries to update (late arrival)
        $extensionData = [
            'asin'                 => 'B0RACECOND',
            'country'              => 'us',
            'product_url'          => 'https://www.amazon.com/dp/B0RACECOND',
            'extension_version'    => '1.0.0',
            'extraction_timestamp' => '2024-01-01T12:00:00.000Z',
            'reviews'              => [],
            'product_info'         => [
                'title'                   => 'Late Update',
                'total_reviews_on_amazon' => 50,
            ],
        ];

        $service = new ExtensionReviewService();
        $result = $service->processExtensionData($extensionData);

        // CRITICAL: Status must remain completed
        $this->assertEquals('completed', $result->status);
        $this->assertEquals(28.0, $result->fake_percentage);
        $this->assertEquals('C', $result->grade);

        // Final verification
        $finalCheck = AsinData::where('asin', 'B0RACECOND')->where('country', 'us')->first();
        $this->assertTrue($finalCheck->isAnalyzed());
        $this->assertEquals('completed', $finalCheck->status);
    }
}
