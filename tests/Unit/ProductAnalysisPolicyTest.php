<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\ProductAnalysisPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductAnalysisPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProductAnalysisPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ProductAnalysisPolicy();
    }

    #[Test]
    public function it_determines_product_is_analyzable_with_reviews()
    {
        $product = AsinData::factory()->create([
            'reviews' => [
                ['rating' => 5, 'text' => 'Great product'],
                ['rating' => 4, 'text' => 'Good value'],
            ],
        ]);

        $this->assertTrue($this->policy->isAnalyzable($product));
    }

    #[Test]
    public function it_determines_product_is_not_analyzable_without_reviews()
    {
        $product = AsinData::factory()->create([
            'reviews' => [],
        ]);

        $this->assertFalse($this->policy->isAnalyzable($product));
    }

    #[Test]
    public function it_determines_product_is_not_analyzable_with_null_reviews()
    {
        $product = AsinData::factory()->create([
            'reviews' => null,
        ]);

        $this->assertFalse($this->policy->isAnalyzable($product));
    }

    #[Test]
    public function it_determines_product_should_display_in_listing_when_complete()
    {
        $product = AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25.0,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => [
                ['rating' => 5, 'text' => 'Great product'],
            ],
        ]);

        $this->assertTrue($this->policy->shouldDisplayInListing($product));
    }

    #[Test]
    public function it_determines_product_should_not_display_without_reviews()
    {
        $product = AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25.0,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => [],
        ]);

        $this->assertFalse($this->policy->shouldDisplayInListing($product));
    }

    #[Test]
    public function it_determines_product_should_not_display_without_product_data()
    {
        $product = AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25.0,
            'grade'             => 'B',
            'have_product_data' => false,
            'product_title'     => 'Test Product',
            'reviews'           => [
                ['rating' => 5, 'text' => 'Great product'],
            ],
        ]);

        $this->assertFalse($this->policy->shouldDisplayInListing($product));
    }

    #[Test]
    public function it_provides_consistent_default_analysis_result()
    {
        $result = $this->policy->getDefaultAnalysisResult();

        $this->assertEquals([], $result['detailed_scores']);
        $this->assertEquals('system', $result['analysis_provider']);
        $this->assertEquals(0.0, $result['total_cost']);
    }

    #[Test]
    public function it_provides_consistent_default_metrics()
    {
        $metrics = $this->policy->getDefaultMetrics();

        $this->assertEquals(0, $metrics['fake_percentage']);
        $this->assertEquals('U', $metrics['grade']);
        $this->assertEquals('No reviews could be extracted for analysis. This may occur when Amazon is actively blocking automated review collection, implementing anti-bot measures, or when the product genuinely has no customer reviews. Our system will automatically retry this analysis periodically to check if reviews become available.', $metrics['explanation']);
        $this->assertEquals(0.0, $metrics['amazon_rating']);
        $this->assertEquals(0.0, $metrics['adjusted_rating']);
        $this->assertEquals(0, $metrics['total_reviews']);
        $this->assertEquals(0, $metrics['fake_count']);
    }

    #[Test]
    public function it_completes_analysis_for_products_without_reviews()
    {
        $product = AsinData::factory()->create([
            'reviews'         => [],
            'status'          => 'pending_analysis',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $result = $this->policy->completeAnalysisWithoutReviews($product);

        // Verify analysis result was set
        $this->assertNotNull($result->openai_result);
        $openaiResult = $result->openai_result;
        if (is_string($openaiResult)) {
            $openaiResult = json_decode($openaiResult, true);
        }
        $this->assertEquals([], $openaiResult['detailed_scores']);
        $this->assertEquals('system', $openaiResult['analysis_provider']);

        // Verify metrics were set
        $this->assertEquals('completed', $result->status);
        $this->assertEquals(0, $result->fake_percentage);
        $this->assertEquals('U', $result->grade);
        $this->assertEquals('No reviews could be extracted for analysis. This may occur when Amazon is actively blocking automated review collection, implementing anti-bot measures, or when the product genuinely has no customer reviews. Our system will automatically retry this analysis periodically to check if reviews become available.', $result->explanation);
        $this->assertEquals(0.0, $result->amazon_rating);
        $this->assertEquals(0.0, $result->adjusted_rating);

        // Verify timestamps were set
        $this->assertNotNull($result->first_analyzed_at);
        $this->assertNotNull($result->last_analyzed_at);
    }

    #[Test]
    public function it_applies_displayable_constraints_to_query()
    {
        // Create products with different states
        $displayableProduct = AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25.0,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Displayable Product',
            'reviews'           => [['rating' => 5, 'text' => 'Great']],
        ]);

        $nonDisplayableProduct1 = AsinData::factory()->create([
            'status'            => 'pending',  // Not completed
            'fake_percentage'   => 25.0,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Pending Product',
            'reviews'           => [['rating' => 5, 'text' => 'Great']],
        ]);

        $nonDisplayableProduct2 = AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25.0,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'No Reviews Product',
            'reviews'           => [], // No reviews
        ]);

        $query = AsinData::query();
        $results = $this->policy->applyDisplayableConstraints($query)->get();

        // Should only return the displayable product
        $this->assertCount(1, $results);
        $this->assertEquals($displayableProduct->id, $results->first()->id);
    }
}
