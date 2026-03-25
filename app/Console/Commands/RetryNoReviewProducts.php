<?php

namespace App\Console\Commands;

use App\Jobs\TriggerBrightDataScraping;
use App\Models\AsinData;
use App\Services\LoggingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class RetryNoReviewProducts extends Command
{
    protected $signature = 'products:retry-no-reviews 
                           {asin? : Specific ASIN to retry (optional)}
                           {--country=us : Country code for specific ASIN retry}
                           {--limit=10 : Maximum number of products to retry}
                           {--age=24 : Only retry products analyzed within this many hours}
                           {--dry-run : Show what would be done without making changes}
                           {--force : Force retry without confirmation}';

    protected $description = 'Retry analysis for Grade U products (no reviews found) to attempt re-extraction';

    public function handle()
    {
        $asin = $this->argument('asin');
        $country = $this->option('country');
        $limit = (int) $this->option('limit');
        $ageHours = (int) $this->option('age');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Handle specific ASIN retry
        if ($asin) {
            return $this->retrySpecificAsin($asin, $country, $dryRun, $force);
        }

        $this->info('Retrying Grade U products (no reviews found)');
        $this->line("- Limit: {$limit} products");
        $this->line("- Age filter: within last {$ageHours} hours");
        $this->line('- Mode: '.($dryRun ? 'DRY RUN' : 'LIVE'));
        $this->line('');

        // Find Grade U products within the specified age range
        $cutoffTime = now()->subHours($ageHours);

        $query = AsinData::where('grade', 'U')
            ->where('status', 'completed')
            ->where('first_analyzed_at', '>=', $cutoffTime)
            ->whereNotNull('product_title') // Only retry products that have basic product data
            ->orderBy('first_analyzed_at', 'desc');

        $totalCount = $query->count();
        $products = $query->take($limit)->get();

        if ($totalCount === 0) {
            $this->info('No Grade U products found within the specified time range.');

            return 0;
        }

        $this->info("Found {$totalCount} Grade U products within last {$ageHours} hours.");

        if ($totalCount > $limit) {
            $this->info("Will process first {$limit} products due to --limit={$limit}");
        }

        if ($products->isEmpty()) {
            $this->info('No products to process after filtering.');

            return 0;
        }

        // Display products to be retried
        $this->table(
            ['ASIN', 'Country', 'Product Title', 'Analyzed', 'Current Grade'],
            $products->map(function ($product) {
                return [
                    $product->asin,
                    strtoupper($product->country),
                    $this->truncate($product->product_title ?? 'No title', 40),
                    $product->first_analyzed_at->diffForHumans(),
                    $product->grade,
                ];
            })->toArray()
        );

        if ($dryRun) {
            $this->info('');
            $this->warn('DRY RUN: No changes made. Remove --dry-run to process these products.');

            return 0;
        }

        if (!$force && !$this->confirm("Retry analysis for {$products->count()} Grade U products?")) {
            $this->info('Cancelled.');

            return 0;
        }

        $this->info('');
        $this->info('Processing products...');

        $processed = 0;
        $failed = 0;

        foreach ($products as $product) {
            try {
                // Reset the product status to trigger fresh analysis
                $product->update([
                    'status'           => 'processing',
                    'reviews'          => null,
                    'openai_result'    => null,
                    'fake_percentage'  => null,
                    'grade'            => null,
                    'explanation'      => null,
                    'last_analyzed_at' => now(),
                ]);

                // Trigger new BrightData scraping job
                TriggerBrightDataScraping::dispatch($product->asin, $product->country);

                $processed++;
                $this->line("✓ Queued: {$product->asin} ({$product->country})");

                LoggingService::log('Grade U product retry queued', [
                    'asin'                   => $product->asin,
                    'country'                => $product->country,
                    'product_title'          => $product->product_title,
                    'original_analysis_date' => $product->first_analyzed_at,
                ]);

                // Small delay to avoid overwhelming the queue
                if ($processed < $products->count()) {
                    usleep(100000); // 0.1 seconds
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("✗ Failed: {$product->asin} - {$e->getMessage()}");

                LoggingService::log('Grade U product retry failed', [
                    'asin'    => $product->asin,
                    'country' => $product->country,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $this->info('');
        $this->info('Retry processing complete:');
        $this->line("- Successfully queued: {$processed}");
        $this->line("- Failed: {$failed}");
        $this->line('- Total processed: '.($processed + $failed));

        if ($processed > 0) {
            $this->info('');
            $this->info('Products have been reset and queued for fresh BrightData analysis.');
            $this->info('Check the queue workers and logs for progress updates.');
        }

        return $failed > 0 ? 1 : 0;
    }

    private function retrySpecificAsin(string $asin, string $country, bool $dryRun, bool $force): int
    {
        $this->info("Retrying specific ASIN: {$asin} (country: {$country})");
        $this->line('- Mode: '.($dryRun ? 'DRY RUN' : 'LIVE'));
        $this->line('');

        // Find the specific product
        $product = AsinData::where('asin', $asin)
            ->where('country', $country)
            ->first();

        if (!$product) {
            $this->error("Product not found: {$asin} (country: {$country})");

            return 1;
        }

        if ($product->grade !== 'U') {
            $this->error("Product {$asin} has grade '{$product->grade}', not 'U'. Only Grade U products can be retried.");

            return 1;
        }

        if ($product->status !== 'completed') {
            $this->error("Product {$asin} has status '{$product->status}', not 'completed'. Only completed products can be retried.");

            return 1;
        }

        // Display product info
        $this->table(
            ['ASIN', 'Country', 'Product Title', 'Analyzed', 'Current Grade', 'Status'],
            [[
                $product->asin,
                strtoupper($product->country),
                $this->truncate($product->product_title ?? 'No title', 40),
                $product->first_analyzed_at?->diffForHumans() ?? 'Unknown',
                $product->grade,
                $product->status,
            ]]
        );

        if ($dryRun) {
            $this->info('');
            $this->warn('DRY RUN: No changes made. Remove --dry-run to retry this product.');

            return 0;
        }

        if (!$force && !$this->confirm("Retry analysis for {$asin} ({$country})?")) {
            $this->info('Cancelled.');

            return 0;
        }

        try {
            // Reset the product status to trigger fresh analysis
            $product->update([
                'status'           => 'processing',
                'reviews'          => null,
                'openai_result'    => null,
                'fake_percentage'  => null,
                'grade'            => null,
                'explanation'      => null,
                'last_analyzed_at' => now(),
            ]);

            // Trigger new BrightData scraping job
            TriggerBrightDataScraping::dispatch($product->asin, $product->country);

            $this->info("✓ Successfully queued: {$product->asin} ({$product->country})");

            LoggingService::log('Specific Grade U product retry queued', [
                'asin'                   => $product->asin,
                'country'                => $product->country,
                'product_title'          => $product->product_title,
                'original_analysis_date' => $product->first_analyzed_at,
            ]);

            $this->info('');
            $this->info('Product has been reset and queued for fresh BrightData analysis.');
            $this->info('Check the queue workers and logs for progress updates.');

            return 0;
        } catch (\Exception $e) {
            $this->error("✗ Failed to retry {$product->asin}: {$e->getMessage()}");

            LoggingService::log('Specific Grade U product retry failed', [
                'asin'    => $product->asin,
                'country' => $product->country,
                'error'   => $e->getMessage(),
            ]);

            return 1;
        }
    }

    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3).'...' : $text;
    }
}
