<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmSitemapCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:warm 
                            {--clear : Clear existing cache before warming}
                            {--verify : Verify cache was populated successfully}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm sitemap cache by generating all sitemaps in advance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Warming sitemap cache...');

        $startTime = microtime(true);

        if ($this->option('clear')) {
            $this->info('Clearing existing cache...');
            SitemapController::clearCache();
        }

        try {
            // Warm the cache
            SitemapController::warmCache();

            $duration = round(microtime(true) - $startTime, 2);
            $this->info("Cache warmed successfully in {$duration} seconds");

            if ($this->option('verify')) {
                $this->verifyCache();
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to warm sitemap cache: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Verify that cache was populated.
     */
    private function verifyCache(): void
    {
        $this->info('Verifying cache...');

        $keys = [
            'sitemap.index',
            'sitemap.static',
            'sitemap.products',
            'sitemap.analysis',
        ];

        $allCached = true;
        foreach ($keys as $key) {
            $isCached = Cache::has($key);
            $status = $isCached ? 'CACHED' : 'MISSING';
            $this->line("  {$key}: {$status}");

            if (!$isCached) {
                $allCached = false;
            }
        }

        if ($allCached) {
            $this->info('All sitemaps cached successfully');
        } else {
            $this->warn('Some sitemaps were not cached');
        }
    }
}
