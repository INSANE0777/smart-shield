<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\SiteStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteStatisticsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function homepage_displays_products_analyzed_count(): void
    {
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('productsAnalyzedCount', 5);
        $response->assertViewHas('productsAnalyzedDisplay', '5');
    }

    #[Test]
    public function about_page_displays_products_analyzed_count(): void
    {
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        $response = $this->get('/about');

        $response->assertStatus(200);
        $response->assertViewHas('productsAnalyzedCount', 5);
    }

    #[Test]
    public function homepage_and_about_page_show_same_count(): void
    {
        AsinData::factory()->count(10)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        $homeResponse = $this->get('/');
        $aboutResponse = $this->get('/about');

        $homeCount = $homeResponse->viewData('productsAnalyzedCount');
        $aboutCount = $aboutResponse->viewData('productsAnalyzedCount');

        $this->assertEquals($homeCount, $aboutCount);
        $this->assertEquals(10, $homeCount);
    }

    #[Test]
    public function cache_is_warmed_after_page_load(): void
    {
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // Cache should not be warm initially
        $service = app(SiteStatisticsService::class);
        $this->assertFalse($service->isCacheWarm());

        // Load homepage
        $this->get('/');

        // Cache should now be warm
        $this->assertTrue($service->isCacheWarm());
    }

    #[Test]
    public function subsequent_requests_use_cached_value(): void
    {
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // First request
        $this->get('/');

        // Add more products
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Another Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 2]]),
        ]);

        // Second request should still show cached value (5, not 10)
        $response = $this->get('/');
        $this->assertEquals(5, $response->viewData('productsAnalyzedCount'));
    }

    #[Test]
    public function scheduled_warm_updates_count_for_subsequent_requests(): void
    {
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 1]]),
        ]);

        // First request warms cache
        $this->get('/');

        // Add more products
        AsinData::factory()->count(5)->create([
            'status'            => 'completed',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'have_product_data' => true,
            'product_title'     => 'Another Product',
            'reviews'           => json_encode([['text' => 'Great product', 'id' => 2]]),
        ]);

        // Simulate scheduled cache warming
        $service = app(SiteStatisticsService::class);
        $service->warmProductsAnalyzedCount();

        // Now the request should show the updated count
        $response = $this->get('/');
        $this->assertEquals(10, $response->viewData('productsAnalyzedCount'));
    }
}
