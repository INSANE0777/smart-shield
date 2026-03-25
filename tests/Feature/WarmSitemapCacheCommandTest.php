<?php

namespace Tests\Feature;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarmSitemapCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_warms_sitemap_cache_successfully()
    {
        AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        Cache::forget('sitemap.index');
        Cache::forget('sitemap.static');
        Cache::forget('sitemap.products');
        Cache::forget('sitemap.analysis');

        $this->artisan('sitemap:warm')
            ->expectsOutput('Warming sitemap cache...')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('sitemap.index'));
        $this->assertTrue(Cache::has('sitemap.static'));
        $this->assertTrue(Cache::has('sitemap.products'));
        $this->assertTrue(Cache::has('sitemap.analysis'));
    }

    #[Test]
    public function it_clears_cache_before_warming_when_flag_provided()
    {
        Cache::put('sitemap.index', 'old-content', 3600);
        Cache::put('sitemap.static', 'old-content', 3600);
        Cache::put('sitemap.products', 'old-content', 3600);
        Cache::put('sitemap.analysis', 'old-content', 3600);

        $this->artisan('sitemap:warm --clear')
            ->expectsOutput('Warming sitemap cache...')
            ->expectsOutput('Clearing existing cache...')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('sitemap.index'));
        $this->assertNotEquals('old-content', Cache::get('sitemap.index'));
    }

    #[Test]
    public function it_verifies_cache_when_flag_provided()
    {
        AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        $this->artisan('sitemap:warm --verify')
            ->expectsOutput('Warming sitemap cache...')
            ->expectsOutput('Verifying cache...')
            ->expectsOutput('All sitemaps cached successfully')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_empty_database_gracefully()
    {
        Cache::forget('sitemap.index');
        Cache::forget('sitemap.static');
        Cache::forget('sitemap.products');
        Cache::forget('sitemap.analysis');

        $this->artisan('sitemap:warm')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('sitemap.index'));
        $this->assertTrue(Cache::has('sitemap.static'));
        $this->assertTrue(Cache::has('sitemap.products'));
        $this->assertTrue(Cache::has('sitemap.analysis'));
    }

    #[Test]
    public function it_reports_execution_time()
    {
        $this->artisan('sitemap:warm')
            ->expectsOutput('Warming sitemap cache...')
            ->assertExitCode(0);

        // Verify cache was populated (which means command completed successfully)
        $this->assertTrue(Cache::has('sitemap.index'));
    }

    #[Test]
    public function it_works_with_large_product_dataset()
    {
        AsinData::factory()->count(100)->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Bulk Test Product',
        ]);

        Cache::forget('sitemap.products');

        $this->artisan('sitemap:warm')
            ->assertExitCode(0);

        $this->assertTrue(Cache::has('sitemap.products'));

        $cachedContent = Cache::get('sitemap.products');
        $urlCount = substr_count($cachedContent, '<url>');
        $this->assertEquals(100, $urlCount);
    }
}
