<?php

namespace Tests\Feature;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyzePricesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_displays_help_information(): void
    {
        $this->artisan('analyze:prices', ['--help' => true])
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_no_products_message_when_none_found(): void
    {
        $this->artisan('analyze:prices')
            ->expectsOutput('No products found matching the criteria.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_supports_dry_run_mode(): void
    {
        AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'product_title'         => 'Test Product',
            'price_analysis_status' => 'pending',
            'first_analyzed_at'     => now(),
        ]);

        $this->artisan('analyze:prices', ['--dry-run' => true])
            ->expectsOutput('DRY RUN - No changes will be made.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_processes_specific_asin(): void
    {
        $content = json_encode([
            'msrp_analysis'     => ['estimated_msrp' => '$49.99'],
            'market_comparison' => ['price_positioning' => 'Mid-range'],
            'price_insights'    => ['seasonal_consideration' => 'N/A'],
            'summary'           => 'Good price.',
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ], 200),
            '*/api/generate' => Http::response([
                'response' => $content,
            ], 200),
        ]);

        $asinData = AsinData::factory()->create([
            'asin'                  => 'B0TEST1234',
            'country'               => 'us',
            'status'                => 'completed',
            'have_product_data'     => true,
            'product_title'         => 'Test Product',
            'price_analysis_status' => 'pending',
        ]);

        $this->artisan('analyze:prices', [
            '--asin'    => 'B0TEST1234',
            '--country' => 'us',
        ])
            ->assertExitCode(0);

        $asinData->refresh();
        $this->assertEquals('completed', $asinData->price_analysis_status);
    }

    #[Test]
    public function it_respects_days_option(): void
    {
        // Product analyzed 10 days ago (should be included with --days=15)
        AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'product_title'         => 'Recent Product',
            'price_analysis_status' => 'pending',
            'first_analyzed_at'     => now()->subDays(10),
        ]);

        // Product analyzed 20 days ago (should be excluded with --days=15)
        AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'product_title'         => 'Old Product',
            'price_analysis_status' => 'pending',
            'first_analyzed_at'     => now()->subDays(20),
        ]);

        $this->artisan('analyze:prices', ['--days' => 15, '--dry-run' => true])
            ->expectsOutput('Found 1 product(s) to process.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_respects_limit_option(): void
    {
        AsinData::factory()->count(5)->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'price_analysis_status' => 'pending',
            'first_analyzed_at'     => now(),
        ]);

        $this->artisan('analyze:prices', ['--limit' => 2, '--dry-run' => true])
            ->expectsOutput('Found 2 product(s) to process.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_skips_products_already_analyzed_unless_forced(): void
    {
        AsinData::factory()->withPriceAnalysis()->create([
            'first_analyzed_at' => now(),
        ]);

        $this->artisan('analyze:prices', ['--dry-run' => true])
            ->expectsOutput('No products found matching the criteria.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_includes_already_analyzed_products_when_forced(): void
    {
        AsinData::factory()->withPriceAnalysis()->create([
            'first_analyzed_at' => now(),
        ]);

        $this->artisan('analyze:prices', ['--force' => true, '--dry-run' => true])
            ->expectsOutput('Found 1 product(s) to process.')
            ->assertExitCode(0);
    }
}
