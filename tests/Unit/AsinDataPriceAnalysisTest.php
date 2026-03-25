<?php

namespace Tests\Unit;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsinDataPriceAnalysisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function has_price_analysis_returns_true_when_completed(): void
    {
        $asinData = AsinData::factory()->withPriceAnalysis()->create();

        $this->assertTrue($asinData->hasPriceAnalysis());
    }

    #[Test]
    public function has_price_analysis_returns_false_when_pending(): void
    {
        $asinData = AsinData::factory()->create([
            'price_analysis_status' => 'pending',
            'price_analysis'        => null,
        ]);

        $this->assertFalse($asinData->hasPriceAnalysis());
    }

    #[Test]
    public function is_price_analysis_processing_returns_true_when_processing(): void
    {
        $asinData = AsinData::factory()->priceAnalysisProcessing()->create();

        $this->assertTrue($asinData->isPriceAnalysisProcessing());
    }

    #[Test]
    public function needs_price_analysis_returns_true_for_eligible_product(): void
    {
        $asinData = AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'price_analysis_status' => 'pending',
            'price_analysis'        => null,
        ]);

        $this->assertTrue($asinData->needsPriceAnalysis());
    }

    #[Test]
    public function needs_price_analysis_returns_false_when_already_completed(): void
    {
        $asinData = AsinData::factory()->withPriceAnalysis()->create();

        $this->assertFalse($asinData->needsPriceAnalysis());
    }

    #[Test]
    public function needs_price_analysis_returns_false_when_processing(): void
    {
        $asinData = AsinData::factory()->priceAnalysisProcessing()->create();

        $this->assertFalse($asinData->needsPriceAnalysis());
    }

    #[Test]
    public function needs_price_analysis_returns_false_when_review_analysis_incomplete(): void
    {
        $asinData = AsinData::factory()->processing()->create([
            'price_analysis_status' => 'pending',
        ]);

        $this->assertFalse($asinData->needsPriceAnalysis());
    }

    #[Test]
    public function scope_pending_price_analysis_finds_eligible_products(): void
    {
        // Create eligible product
        AsinData::factory()->create([
            'status'                => 'completed',
            'have_product_data'     => true,
            'product_title'         => 'Eligible Product',
            'price_analysis_status' => 'pending',
        ]);

        // Create completed product (should not be found)
        AsinData::factory()->withPriceAnalysis()->create();

        // Create processing product (should not be found)
        AsinData::factory()->processing()->create();

        $results = AsinData::pendingPriceAnalysis()->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Eligible Product', $results->first()->product_title);
    }

    #[Test]
    public function price_analysis_is_cast_to_array(): void
    {
        $priceData = [
            'msrp_analysis' => ['estimated_msrp' => '$50'],
            'summary'       => 'Test summary',
        ];

        $asinData = AsinData::factory()->create([
            'price_analysis'        => $priceData,
            'price_analysis_status' => 'completed',
        ]);

        $asinData->refresh();

        $this->assertIsArray($asinData->price_analysis);
        $this->assertEquals('$50', $asinData->price_analysis['msrp_analysis']['estimated_msrp']);
    }

    #[Test]
    public function price_analyzed_at_is_cast_to_datetime(): void
    {
        $asinData = AsinData::factory()->withPriceAnalysis()->create();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $asinData->price_analyzed_at);
    }
}
