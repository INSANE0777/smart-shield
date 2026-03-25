<?php

namespace Tests\Feature;

use App\Jobs\ProcessEditorialContent;
use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateEditorialContentCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_includes_pending_products_for_batch_processing(): void
    {
        // 'pending' is the default status for unprocessed products
        // Command should include them for batch processing

        // Product with pending status (default for unprocessed)
        $pendingProduct = AsinData::factory()->create([
            'asin'              => 'B001PEND',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Unprocessed Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'pending',
            'first_analyzed_at' => now()->subDay(),
        ]);

        // Product with failed status (should be picked up for retry)
        $failedProduct = AsinData::factory()->create([
            'asin'              => 'B002FAIL',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Product That Failed Previously',
            'grade'             => 'A',
            'fake_percentage'   => 5.0,
            'editorial_status'  => 'failed',
            'first_analyzed_at' => now()->subDay(),
        ]);

        // Run the command in dry-run mode to see what it would process
        $this->artisan('generate:editorial', ['--days' => 7, '--dry-run' => true])
            ->assertSuccessful();

        // Verify both pending and failed products are in scope
        $scope = AsinData::pendingEditorialContent()->pluck('asin')->toArray();

        $this->assertContains('B001PEND', $scope, 'Pending products should be included');
        $this->assertContains('B002FAIL', $scope, 'Failed products should be included for retry');
    }

    #[Test]
    public function command_excludes_completed_and_processing_products(): void
    {
        // Products that are completed or currently processing should be skipped

        // Product with completed status (already done)
        AsinData::factory()->create([
            'asin'              => 'B001DONE',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Already Processed Product',
            'grade'             => 'A',
            'fake_percentage'   => 5.0,
            'editorial_status'  => 'completed',
            'editorial_content' => ['test' => 'data'],
            'first_analyzed_at' => now()->subDay(),
        ]);

        // Product with processing status (job running)
        AsinData::factory()->create([
            'asin'              => 'B002PROC',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Currently Processing Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'processing',
            'first_analyzed_at' => now()->subDay(),
        ]);

        $scope = AsinData::pendingEditorialContent()->pluck('asin')->toArray();

        $this->assertNotContains('B001DONE', $scope, 'Completed products should be excluded');
        $this->assertNotContains('B002PROC', $scope, 'Processing products should be excluded');
    }

    #[Test]
    public function duplicate_processing_prevented_by_service_check(): void
    {
        Queue::fake();

        // Create a product and dispatch a job (simulating job in queue)
        $product = AsinData::factory()->create([
            'asin'              => 'B001QUEUE',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Product With Job In Queue',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'pending',
            'first_analyzed_at' => now()->subDay(),
        ]);

        // Dispatch a job (this would happen when product is analyzed)
        ProcessEditorialContent::dispatch($product->id);
        Queue::assertPushed(ProcessEditorialContent::class);

        // Product IS in scope (pending status is included)
        $foundProducts = AsinData::pendingEditorialContent()
            ->where('asin', 'B001QUEUE')
            ->get();
        $this->assertCount(1, $foundProducts, 'Pending products are in scope');

        // But once processing starts, status changes to 'processing'
        // which excludes it from further processing
        $product->update(['editorial_status' => 'processing']);

        $foundProducts = AsinData::pendingEditorialContent()
            ->where('asin', 'B001QUEUE')
            ->get();
        $this->assertCount(0, $foundProducts, 'Processing products are excluded');
    }

    #[Test]
    public function command_processes_correct_status_combinations(): void
    {
        // Create products with all possible statuses
        $statuses = [
            'pending'    => true,   // Should be processed (default for unprocessed)
            'processing' => false,  // Should NOT be processed (job running)
            'completed'  => false,  // Should NOT be processed (already done)
            'failed'     => true,   // Should be processed (retry)
        ];

        $index = 1;
        foreach ($statuses as $status => $shouldProcess) {
            AsinData::factory()->create([
                'asin'              => "B00{$index}TEST",
                'status'            => 'completed',
                'have_product_data' => true,
                'product_title'     => "Product with {$status} status",
                'grade'             => 'B',
                'fake_percentage'   => 15.5,
                'editorial_status'  => $status,
                'editorial_content' => $status === 'completed' ? ['test' => 'data'] : null,
                'first_analyzed_at' => now()->subDay(),
            ]);
            $index++;
        }

        $scopeAsins = AsinData::pendingEditorialContent()->pluck('asin')->toArray();

        // Verify each status is handled correctly
        $this->assertContains('B001TEST', $scopeAsins, 'Pending should be included');
        $this->assertNotContains('B002TEST', $scopeAsins, 'Processing should be excluded');
        $this->assertNotContains('B003TEST', $scopeAsins, 'Completed should be excluded');
        $this->assertContains('B004TEST', $scopeAsins, 'Failed should be included for retry');
    }

    #[Test]
    public function processing_status_prevents_concurrent_processing(): void
    {
        // This test verifies the state machine prevents concurrent processing

        $product = AsinData::factory()->create([
            'asin'              => 'B001RACE',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Race Condition Test Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'pending',
            'first_analyzed_at' => now()->subDay(),
        ]);

        // Initially, product is in scope (pending)
        $this->assertTrue(
            AsinData::pendingEditorialContent()->where('id', $product->id)->exists(),
            'Pending product should be in scope'
        );
        $this->assertTrue($product->needsEditorialContent(), 'Pending product needs editorial content');

        // Once job starts, it sets status to processing
        $product->update(['editorial_status' => 'processing']);

        // Now product is excluded from both scope and needsEditorialContent()
        $this->assertFalse(
            AsinData::pendingEditorialContent()->where('id', $product->id)->exists(),
            'Processing products should not be in scope'
        );
        $this->assertFalse($product->fresh()->needsEditorialContent(), 'Processing products should not need content');
    }

    #[Test]
    public function blade_template_conditions_are_independent_of_command_scope(): void
    {
        // The blade uses hasEditorialContent() which only checks for 'completed' status
        // This is independent of command scope changes

        $statuses = ['pending', 'processing', 'failed', 'completed'];

        foreach ($statuses as $status) {
            $product = AsinData::factory()->create([
                'editorial_status'  => $status,
                'editorial_content' => $status === 'completed' ? ['buyers_guide' => ['headline' => 'Test']] : null,
            ]);

            if ($status === 'completed') {
                $this->assertTrue($product->hasEditorialContent(), 'Completed should return true for hasEditorialContent');
            } else {
                $this->assertFalse($product->hasEditorialContent(), "{$status} should return false for hasEditorialContent");
            }
        }
    }
}
