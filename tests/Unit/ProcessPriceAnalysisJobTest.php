<?php

namespace Tests\Unit;

use App\Jobs\ProcessPriceAnalysis;
use App\Models\AsinData;
use App\Services\PriceAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessPriceAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mock all LLM provider endpoints.
     */
    private function mockAllLLMProviders(): void
    {
        $content = json_encode([
            'msrp_analysis' => [
                'estimated_msrp'          => '$49.99',
                'msrp_source'             => 'Category average',
                'amazon_price_assessment' => 'Below MSRP',
            ],
            'market_comparison' => [
                'price_positioning'          => 'Mid-range',
                'typical_alternatives_range' => '$30-$70',
                'value_proposition'          => 'Good value.',
            ],
            'price_insights' => [
                'seasonal_consideration' => 'N/A',
                'deal_indicators'        => 'N/A',
                'caution_flags'          => 'N/A',
            ],
            'summary' => 'Competitively priced product.',
        ]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ], 200),
            '*/api/generate' => Http::response([
                'response' => $content,
            ], 200),
        ]);
    }

    #[Test]
    public function it_dispatches_to_price_analysis_queue(): void
    {
        Queue::fake();

        $asinData = AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'price_analysis_status' => 'pending',
        ]);

        ProcessPriceAnalysis::dispatch($asinData->id);

        Queue::assertPushedOn('price-analysis', ProcessPriceAnalysis::class);
    }

    #[Test]
    public function it_skips_products_with_existing_price_analysis(): void
    {
        Http::fake(); // Should not be called

        $asinData = AsinData::factory()->withPriceAnalysis()->create();

        $job = new ProcessPriceAnalysis($asinData->id);
        $job->handle(app(PriceAnalysisService::class));

        // Should not make any API calls
        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_products_without_completed_review_analysis(): void
    {
        Http::fake(); // Should not be called

        $asinData = AsinData::factory()->processing()->create();

        $job = new ProcessPriceAnalysis($asinData->id);
        $job->handle(app(PriceAnalysisService::class));

        // Should not make any API calls
        Http::assertNothingSent();
    }

    #[Test]
    public function it_processes_eligible_products(): void
    {
        $this->mockAllLLMProviders();

        $asinData = AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'product_title'         => 'Test Product',
            'price_analysis_status' => 'pending',
        ]);

        $job = new ProcessPriceAnalysis($asinData->id);
        $job->handle(app(PriceAnalysisService::class));

        $asinData->refresh();
        $this->assertEquals('completed', $asinData->price_analysis_status);
        $this->assertNotNull($asinData->price_analysis);
    }

    #[Test]
    public function it_handles_missing_asin_data_gracefully(): void
    {
        $job = new ProcessPriceAnalysis(99999); // Non-existent ID
        $job->handle(app(PriceAnalysisService::class));

        // Should complete without exception
        $this->assertTrue(true);
    }

    #[Test]
    public function it_marks_status_as_failed_on_permanent_failure(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response('Error', 500),
        ]);

        $asinData = AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'product_title'         => 'Test Product',
            'price_analysis_status' => 'pending',
        ]);

        $job = new ProcessPriceAnalysis($asinData->id);
        $job->failed(new \Exception('Test failure'));

        $asinData->refresh();
        $this->assertEquals('failed', $asinData->price_analysis_status);
    }

    #[Test]
    public function it_has_correct_job_tags(): void
    {
        $asinDataId = 123;
        $job = new ProcessPriceAnalysis($asinDataId);

        $tags = $job->tags();

        $this->assertContains('price-analysis', $tags);
        $this->assertContains('asin:123', $tags);
    }
}
