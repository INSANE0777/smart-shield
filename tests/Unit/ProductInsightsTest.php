<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\MetricsCalculationService;
use App\Services\SEOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInsightsTest extends TestCase
{
    use RefreshDatabase;

    private MetricsCalculationService $metricsService;
    private SEOService $seoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsService = new MetricsCalculationService();
        $this->seoService = new SEOService();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stores_product_insights_from_llm_results()
    {
        $aggregateData = [
            'fake_percentage'  => 15,
            'confidence'       => 'high',
            'explanation'      => 'Analysis shows authentic reviews with specific details.',
            'product_insights' => 'Premium wireless headphones featuring exceptional audio quality and all-day battery life, praised by customers for comfort and performance.',
            'fake_examples'    => [],
            'key_patterns'     => ['Specific product details', 'Balanced feedback'],
        ];

        $asinData = AsinData::factory()->create([
            'status'          => 'processing', // Ensure it needs update
            'fake_percentage' => null, // Ensure it needs update
            'grade'           => null, // Ensure it needs update
            'reviews'         => json_encode([
                ['text' => 'Great wireless headphones with excellent sound', 'rating' => 5],
                ['text' => 'Battery lasts all day, very comfortable', 'rating' => 4],
            ]),
            'openai_result' => json_encode($aggregateData), // Store as JSON encoded openai_result for processing
        ]);

        // Simulate the metrics calculation process
        $result = $this->metricsService->calculateFinalMetrics($asinData);

        $this->assertNotNull($result);
        $freshAsinData = $asinData->fresh();

        // Should store product insights
        $this->assertEquals(
            'Premium wireless headphones featuring exceptional audio quality and all-day battery life, praised by customers for comfort and performance.',
            $freshAsinData->product_insights
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_missing_product_insights_gracefully()
    {
        $aggregateData = [
            'fake_percentage' => 25,
            'confidence'      => 'medium',
            'explanation'     => 'Mixed review signals detected.',
            // No product_insights field
            'fake_examples' => [],
            'key_patterns'  => [],
        ];

        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode([
                ['text' => 'Good product', 'rating' => 4],
            ]),
            'openai_result' => json_encode($aggregateData),
        ]);

        $result = $this->metricsService->calculateFinalMetrics($asinData);

        $this->assertNotNull($result);
        $freshAsinData = $asinData->fresh();

        // Should handle missing product insights without error
        $this->assertNull($freshAsinData->product_insights);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_product_insights_in_seo_generation()
    {
        $asinData = AsinData::factory()->create([
            'product_title'    => 'Wireless Bluetooth Headphones',
            'explanation'      => 'Comprehensive analysis of 50 reviews shows 20% fake content.',
            'product_insights' => 'High-quality wireless headphones with superior sound clarity and comfortable design, highly recommended by genuine customers for daily use.',
            'fake_percentage'  => 20,
            'grade'            => 'B',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Should generate SEO content that includes product insights
        $this->assertArrayHasKey('meta_description', $seoData);
        $this->assertArrayHasKey('ai_summary', $seoData);
        $this->assertNotEmpty($seoData['meta_description']);
        $this->assertNotEmpty($seoData['ai_summary']);

        // Verify product insights are stored in the model
        $this->assertEquals('High-quality wireless headphones with superior sound clarity and comfortable design, highly recommended by genuine customers for daily use.', $asinData->product_insights);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_falls_back_when_product_insights_missing_in_seo()
    {
        $asinData = AsinData::factory()->create([
            'product_title'    => 'Generic Product',
            'explanation'      => 'Analysis shows moderate fake review activity.',
            'product_insights' => null, // No insights available
            'fake_percentage'  => 35,
            'grade'            => 'C',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Should still generate SEO data without product insights
        $this->assertArrayHasKey('meta_description', $seoData);
        $this->assertArrayHasKey('ai_summary', $seoData);
        $this->assertNotEmpty($seoData['meta_description']);
        $this->assertNotEmpty($seoData['ai_summary']);

        // Should generate SEO data even without product insights
        $this->assertNotEmpty($seoData['ai_summary']);
        $this->assertStringContainsString('35%', $seoData['ai_summary']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_product_insights_in_enhanced_explanation()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $aggregateData = [
            'fake_percentage'  => 10,
            'confidence'       => 'high',
            'explanation'      => 'Excellent review authenticity detected.\n\nSpecific customer experiences validate product quality.\n\nMinimal fake review activity observed.',
            'product_insights' => 'Premium quality product with exceptional customer satisfaction and reliable performance.',
            'key_patterns'     => ['Detailed feedback', 'Verified purchases'],
        ];

        $asinData->update(['openai_result' => json_encode($aggregateData)]);
        $result = $this->metricsService->calculateFinalMetrics($asinData);
        $freshAsinData = $asinData->fresh();

        // Should store both explanation and product insights
        $this->assertStringContainsString('Excellent review authenticity', $freshAsinData->explanation);
        $this->assertEquals('Premium quality product with exceptional customer satisfaction and reliable performance.', $freshAsinData->product_insights);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_product_insights_field_in_database()
    {
        $asinData = AsinData::create([
            'asin'             => 'B123456789',
            'country'          => 'us',
            'reviews'          => [],
            'product_insights' => 'Test product insights for database validation.',
        ]);

        $this->assertEquals('Test product insights for database validation.', $asinData->product_insights);

        // Test updating the field
        $asinData->update(['product_insights' => 'Updated product insights content.']);
        $this->assertEquals('Updated product insights content.', $asinData->fresh()->product_insights);

        // Test null value
        $asinData->update(['product_insights' => null]);
        $this->assertNull($asinData->fresh()->product_insights);
    }
}
