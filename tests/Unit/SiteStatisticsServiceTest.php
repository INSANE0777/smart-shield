<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\SiteStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SiteStatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SiteStatisticsService::class);
        Cache::flush();
    }

    #[Test]
    public function it_returns_correct_products_analyzed_count(): void
    {
        // Create displayable products (meets all criteria)
        AsinData::factory()->count(3)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // Create non-displayable products (incomplete analysis)
        AsinData::factory()->create([
            'status'            => 'processing',
            'fake_percentage'   => null,
            'grade'             => null,
            'have_product_data' => false,
        ]);

        $count = $this->service->getProductsAnalyzedCount();

        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_returns_formatted_display_for_small_counts(): void
    {
        AsinData::factory()->count(500)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        $display = $this->service->getProductsAnalyzedDisplay();

        $this->assertEquals('500', $display);
    }

    #[Test]
    public function it_returns_formatted_display_for_large_counts(): void
    {
        AsinData::factory()->count(5500)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        $display = $this->service->getProductsAnalyzedDisplay();

        $this->assertEquals('5K+', $display);
    }

    #[Test]
    public function it_caches_the_count(): void
    {
        AsinData::factory()->count(10)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // First call should query DB and cache
        $firstCount = $this->service->getProductsAnalyzedCount();
        $this->assertEquals(10, $firstCount);

        // Verify cache is warm
        $this->assertTrue($this->service->isCacheWarm());
        $this->assertTrue(Cache::has(SiteStatisticsService::CACHE_KEY_PRODUCTS_ANALYZED));

        // Add more products
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // Should still return cached value (10, not 15)
        $cachedCount = $this->service->getProductsAnalyzedCount();
        $this->assertEquals(10, $cachedCount);
    }

    #[Test]
    public function warm_products_analyzed_count_updates_cache(): void
    {
        AsinData::factory()->count(10)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // Warm the cache
        $warmedCount = $this->service->warmProductsAnalyzedCount();
        $this->assertEquals(10, $warmedCount);

        // Add more products
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // Warm again should update the cache
        $newWarmedCount = $this->service->warmProductsAnalyzedCount();
        $this->assertEquals(15, $newWarmedCount);

        // Get should now return the new value
        $this->assertEquals(15, $this->service->getProductsAnalyzedCount());
    }

    #[Test]
    public function clear_cache_removes_cached_value(): void
    {
        AsinData::factory()->count(10)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // Warm the cache
        $this->service->warmProductsAnalyzedCount();
        $this->assertTrue($this->service->isCacheWarm());

        // Clear the cache
        $this->service->clearCache();
        $this->assertFalse($this->service->isCacheWarm());
    }

    #[Test]
    public function it_uses_consistent_criteria_with_product_analysis_policy(): void
    {
        // Create products with empty reviews (should not be counted)
        AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'No Reviews Product',
            'reviews'           => '[]',
        ]);

        // Create products with null product title (should not be counted)
        AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => null,
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // Create valid product (should be counted)
        AsinData::factory()->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Valid Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        $count = $this->service->getProductsAnalyzedCount();

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_returns_zero_when_no_products(): void
    {
        $count = $this->service->getProductsAnalyzedCount();
        $this->assertEquals(0, $count);

        $display = $this->service->getProductsAnalyzedDisplay();
        $this->assertEquals('0', $display);
    }

    #[Test]
    public function it_handles_edge_case_at_1000_products(): void
    {
        AsinData::factory()->count(1000)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        $display = $this->service->getProductsAnalyzedDisplay();

        $this->assertEquals('1K+', $display);
    }
}

