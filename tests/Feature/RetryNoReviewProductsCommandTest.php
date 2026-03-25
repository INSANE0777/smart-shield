<?php

namespace Tests\Feature;

use App\Jobs\TriggerBrightDataScraping;
use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetryNoReviewProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[Test]
    public function it_retries_specific_asin_with_grade_u()
    {
        // Create a Grade U product
        $product = AsinData::factory()->create([
            'asin'            => 'B0TEST1234',
            'country'         => 'us',
            'grade'           => 'U',
            'status'          => 'completed',
            'product_title'   => 'Test Product',
            'fake_percentage' => null,
            'explanation'     => null,
            'reviews'         => null,
            'openai_result'   => null,
        ]);

        $this->artisan('products:retry-no-reviews', ['asin' => 'B0TEST1234', '--force' => true])
            ->expectsOutput('Retrying specific ASIN: B0TEST1234 (country: us)')
            ->expectsOutput('✓ Successfully queued: B0TEST1234 (us)')
            ->assertExitCode(0);

        // Verify product was reset
        $product->refresh();
        $this->assertEquals('processing', $product->status);
        $this->assertNull($product->fake_percentage);
        $this->assertNull($product->grade);
        $this->assertNull($product->explanation);
        $this->assertNull($product->reviews);
        $this->assertNull($product->openai_result);
        $this->assertNotNull($product->last_analyzed_at);

        // Verify job was dispatched
        Queue::assertPushed(TriggerBrightDataScraping::class, function ($job) {
            return $job->asin === 'B0TEST1234' && $job->country === 'us';
        });
    }

    #[Test]
    public function it_retries_specific_asin_with_different_country()
    {
        $product = AsinData::factory()->create([
            'asin'          => 'B0TEST1234',
            'country'       => 'ca',
            'grade'         => 'U',
            'status'        => 'completed',
            'product_title' => 'Test Product Canada',
        ]);

        $this->artisan('products:retry-no-reviews', [
            'asin'      => 'B0TEST1234',
            '--country' => 'ca',
            '--force'   => true,
        ])
            ->expectsOutput('Retrying specific ASIN: B0TEST1234 (country: ca)')
            ->expectsOutput('✓ Successfully queued: B0TEST1234 (ca)')
            ->assertExitCode(0);

        Queue::assertPushed(TriggerBrightDataScraping::class, function ($job) {
            return $job->asin === 'B0TEST1234' && $job->country === 'ca';
        });
    }

    #[Test]
    public function it_shows_dry_run_for_specific_asin()
    {
        $product = AsinData::factory()->create([
            'asin'          => 'B0TEST1234',
            'country'       => 'us',
            'grade'         => 'U',
            'status'        => 'completed',
            'product_title' => 'Test Product',
        ]);

        $this->artisan('products:retry-no-reviews', ['asin' => 'B0TEST1234', '--dry-run' => true])
            ->expectsOutput('Retrying specific ASIN: B0TEST1234 (country: us)')
            ->expectsOutput('DRY RUN: No changes made. Remove --dry-run to retry this product.')
            ->assertExitCode(0);

        // Verify no changes were made
        $product->refresh();
        $this->assertEquals('completed', $product->status);
        $this->assertEquals('U', $product->grade);

        // Verify no job was dispatched
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_fails_when_asin_not_found()
    {
        $this->artisan('products:retry-no-reviews', ['asin' => 'B0NOTFOUND', '--force' => true])
            ->expectsOutput('Product not found: B0NOTFOUND (country: us)')
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_fails_when_product_is_not_grade_u()
    {
        AsinData::factory()->create([
            'asin'    => 'B0TEST1234',
            'country' => 'us',
            'grade'   => 'A',
            'status'  => 'completed',
        ]);

        $this->artisan('products:retry-no-reviews', ['asin' => 'B0TEST1234', '--force' => true])
            ->expectsOutput("Product B0TEST1234 has grade 'A', not 'U'. Only Grade U products can be retried.")
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_fails_when_product_is_not_completed()
    {
        AsinData::factory()->create([
            'asin'    => 'B0TEST1234',
            'country' => 'us',
            'grade'   => 'U',
            'status'  => 'processing',
        ]);

        $this->artisan('products:retry-no-reviews', ['asin' => 'B0TEST1234', '--force' => true])
            ->expectsOutput("Product B0TEST1234 has status 'processing', not 'completed'. Only completed products can be retried.")
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_retries_bulk_grade_u_products_by_age()
    {
        // Create Grade U products within age range
        $recentProduct1 = AsinData::factory()->create([
            'asin'              => 'B0RECENT01',
            'country'           => 'us',
            'grade'             => 'U',
            'status'            => 'completed',
            'product_title'     => 'Recent Product 1',
            'first_analyzed_at' => now()->subHours(12),
        ]);

        $recentProduct2 = AsinData::factory()->create([
            'asin'              => 'B0RECENT02',
            'country'           => 'us',
            'grade'             => 'U',
            'status'            => 'completed',
            'product_title'     => 'Recent Product 2',
            'first_analyzed_at' => now()->subHours(18),
        ]);

        // Create old product outside age range
        AsinData::factory()->create([
            'asin'              => 'B0OLD00001',
            'country'           => 'us',
            'grade'             => 'U',
            'status'            => 'completed',
            'product_title'     => 'Old Product',
            'first_analyzed_at' => now()->subHours(48),
        ]);

        // Create non-U grade product (should be ignored)
        AsinData::factory()->create([
            'asin'              => 'B0GRADEA01',
            'country'           => 'us',
            'grade'             => 'A',
            'status'            => 'completed',
            'first_analyzed_at' => now()->subHours(6),
        ]);

        $this->artisan('products:retry-no-reviews', ['--age' => 24, '--force' => true])
            ->expectsOutput('Found 2 Grade U products within last 24 hours.')
            ->expectsOutput('✓ Queued: B0RECENT01 (us)')
            ->expectsOutput('✓ Queued: B0RECENT02 (us)')
            ->expectsOutput('- Successfully queued: 2')
            ->expectsOutput('- Failed: 0')
            ->assertExitCode(0);

        // Verify both recent products were reset
        $recentProduct1->refresh();
        $recentProduct2->refresh();
        $this->assertEquals('processing', $recentProduct1->status);
        $this->assertEquals('processing', $recentProduct2->status);

        // Verify 2 jobs were dispatched
        Queue::assertPushed(TriggerBrightDataScraping::class, 2);
    }

    #[Test]
    public function it_respects_limit_option_for_bulk_retry()
    {
        // Create 3 Grade U products
        for ($i = 1; $i <= 3; $i++) {
            AsinData::factory()->create([
                'asin'              => "B0TEST000{$i}",
                'country'           => 'us',
                'grade'             => 'U',
                'status'            => 'completed',
                'product_title'     => "Test Product {$i}",
                'first_analyzed_at' => now()->subHours(12),
            ]);
        }

        $this->artisan('products:retry-no-reviews', ['--limit' => 2, '--force' => true])
            ->expectsOutput('Found 3 Grade U products within last 24 hours.')
            ->expectsOutput('Will process first 2 products due to --limit=2')
            ->expectsOutput('- Successfully queued: 2')
            ->assertExitCode(0);

        // Verify only 2 jobs were dispatched
        Queue::assertPushed(TriggerBrightDataScraping::class, 2);
    }

    #[Test]
    public function it_shows_dry_run_for_bulk_retry()
    {
        AsinData::factory()->create([
            'asin'              => 'B0TEST1234',
            'country'           => 'us',
            'grade'             => 'U',
            'status'            => 'completed',
            'product_title'     => 'Test Product',
            'first_analyzed_at' => now()->subHours(12),
        ]);

        $this->artisan('products:retry-no-reviews', ['--dry-run' => true])
            ->expectsOutput('Found 1 Grade U products within last 24 hours.')
            ->expectsOutput('DRY RUN: No changes made. Remove --dry-run to process these products.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_handles_no_products_found_gracefully()
    {
        // Create a product outside the age range
        AsinData::factory()->create([
            'asin'              => 'B0OLD00001',
            'country'           => 'us',
            'grade'             => 'U',
            'status'            => 'completed',
            'first_analyzed_at' => now()->subHours(48),
        ]);

        $this->artisan('products:retry-no-reviews', ['--age' => 24])
            ->expectsOutput('No Grade U products found within the specified time range.')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_only_retries_products_with_product_title()
    {
        // Product with title (should be retried)
        AsinData::factory()->create([
            'asin'              => 'B0WITHTITLE',
            'country'           => 'us',
            'grade'             => 'U',
            'status'            => 'completed',
            'product_title'     => 'Product With Title',
            'first_analyzed_at' => now()->subHours(12),
        ]);

        // Product without title (should be ignored)
        AsinData::factory()->create([
            'asin'              => 'B0NOTITLE01',
            'country'           => 'us',
            'grade'             => 'U',
            'status'            => 'completed',
            'product_title'     => null,
            'first_analyzed_at' => now()->subHours(12),
        ]);

        $this->artisan('products:retry-no-reviews', ['--force' => true])
            ->expectsOutput('Found 1 Grade U products within last 24 hours.')
            ->expectsOutput('✓ Queued: B0WITHTITLE (us)')
            ->expectsOutput('- Successfully queued: 1')
            ->assertExitCode(0);

        Queue::assertPushed(TriggerBrightDataScraping::class, 1);
    }
}
