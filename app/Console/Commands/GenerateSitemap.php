<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use App\Models\AsinData;
use Illuminate\Console\Command;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate 
                            {--clear-cache : Clear existing sitemap cache before regenerating}
                            {--show-stats : Show statistics about included products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate XML sitemap with all analyzed products for SEO';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clearCache = $this->option('clear-cache');
        $showStats = $this->option('show-stats');

        $this->info('🗺️ Generating XML Sitemap');
        $this->info('========================');

        if ($clearCache) {
            $this->info('🧹 Clearing existing sitemap cache...');
            SitemapController::clearCache();

            $this->info('🔥 Warming cache...');
            SitemapController::warmCache();
        }

        // Get statistics about products to be included
        $allProducts = AsinData::all();
        $analyzedProducts = $allProducts->filter(function ($product) {
            return $product->isAnalyzed();
        });

        $totalProducts = $analyzedProducts->count();
        $withProductData = $analyzedProducts->where('have_product_data', true)->count();
        $withSlugs = $analyzedProducts->whereNotNull('product_title')->count();

        $this->info("📊 Found {$totalProducts} analyzed products to include");
        $this->info("   • {$withProductData} with complete product data");
        $this->info("   • {$withSlugs} with SEO-friendly URLs");

        if ($showStats) {
            $this->showDetailedStats($analyzedProducts);
        }

        // Determine if we need sitemap index
        $limit = 1000;
        $totalPages = ceil($totalProducts / $limit);

        if ($totalPages > 1) {
            $this->info("📄 Will generate sitemap index with {$totalPages} product sitemaps");
        } else {
            $this->info('📄 Will generate single sitemap (under 1000 products)');
        }

        // Pre-generate main sitemap
        $this->info('🔄 Generating main sitemap...');

        $sitemapController = new SitemapController();

        try {
            $mainSitemap = $sitemapController->index();
            $this->info('✅ Main sitemap generated successfully');
        } catch (\Exception $e) {
            $this->error('❌ Failed to generate main sitemap: '.$e->getMessage());

            return self::FAILURE;
        }

        // Pre-generate product sitemaps if needed
        if ($totalPages > 1) {
            $this->info('🔄 Generating product sitemaps...');

            for ($page = 1; $page <= $totalPages; $page++) {
                try {
                    $sitemapController->products($page);
                    $this->info("   • Page {$page}/{$totalPages} generated");
                } catch (\Exception $e) {
                    $this->error("❌ Failed to generate product sitemap page {$page}: ".$e->getMessage());

                    return self::FAILURE;
                }
            }

            // Generate sitemap index
            $this->info('🔄 Generating sitemap index...');

            try {
                $sitemapController->sitemapIndex();
                $this->info('✅ Sitemap index generated successfully');
            } catch (\Exception $e) {
                $this->error('❌ Failed to generate sitemap index: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('🎉 Sitemap generation completed successfully!');
        $this->newLine();

        $this->info('📍 Your sitemaps are available at:');
        $this->line('   • Main sitemap: '.url('/sitemap.xml'));

        if ($totalPages > 1) {
            $this->line('   • Sitemap index: '.url('/sitemap-index.xml'));
            $this->line("   • Product sitemaps: /sitemap-products-1.xml through /sitemap-products-{$totalPages}.xml");
        }

        $this->newLine();
        $this->info('💡 Next steps:');
        $this->line('   1. Submit '.url('/sitemap.xml').' to Google Search Console');
        $this->line('   2. Update robots.txt to reference the sitemap');
        $this->line('   3. Set up automated generation with: php artisan schedule:run');

        return self::SUCCESS;
    }

    /**
     * Show detailed statistics about products in sitemap.
     */
    private function showDetailedStats($analyzedProducts): void
    {
        $this->newLine();
        $this->info('📈 Detailed Statistics');
        $this->info('======================');

        // Grade distribution
        $gradeStats = $analyzedProducts
            ->groupBy('grade')
            ->map(function ($products) {
                return $products->count();
            })
            ->sortKeys()
            ->toArray();

        if (!empty($gradeStats)) {
            $this->info('Grade Distribution:');
            foreach ($gradeStats as $grade => $count) {
                $grade = $grade ?: 'NULL';
                $this->line("   • Grade {$grade}: {$count} products");
            }
        }

        // Recent activity
        $recentCount = $analyzedProducts
            ->where('updated_at', '>', now()->subDays(7))
            ->count();

        $this->info("Recent Activity (last 7 days): {$recentCount} products");

        // Countries
        $countryStats = $analyzedProducts
            ->groupBy('country')
            ->map(function ($products) {
                return $products->count();
            })
            ->toArray();

        if (!empty($countryStats)) {
            $this->info('Country Distribution:');
            foreach ($countryStats as $country => $count) {
                $this->line("   • {$country}: {$count} products");
            }
        }
    }
}
