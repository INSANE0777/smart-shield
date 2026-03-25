<?php

namespace App\Console\Commands;

use App\Models\AsinData;
use App\Services\Amazon\AmazonProductDataService;
use App\Services\LoggingService;
use App\Services\ProductCategorizationService;
use Illuminate\Console\Command;

class CategorizeProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categorize:products
                            {--limit=100 : Maximum number of products to categorize}
                            {--dry-run : Show what would be categorized without making changes}
                            {--force : Re-categorize products that already have categories}
                            {--chunk=50 : Process products in chunks of this size}
                            {--fast : Use minimal delays (50ms vs 150ms)}
                            {--parallel=1 : Number of concurrent requests (1-10)}
                            {--delay= : Custom delay in ms between batches (overrides --fast)}
                            {--scrape : Scrape actual Amazon breadcrumbs instead of LLM inference (full hierarchy)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Categorize products using LLM inference or Amazon breadcrumb scraping';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');
        $fast = $this->option('fast');
        $useScraping = $this->option('scrape');

        $parallel = min(10, max(1, (int) $this->option('parallel')));

        // Determine delay: custom > fast > default
        // Scraping needs longer delays to allow connections to close and avoid "too many open files"
        if ($this->option('delay') !== null) {
            $delayMs = (int) $this->option('delay');
        } elseif ($fast) {
            $delayMs = $useScraping ? 100 : 50; // Even fast mode needs some delay for scraping
        } else {
            $delayMs = $useScraping ? 500 : 150; // Longer delay for scraping to avoid rate limiting
        }

        $this->info('Product Categorization');
        $this->info('======================');
        $this->line('Mode: '.($useScraping ? 'Amazon Breadcrumb Scraping (full hierarchy)' : 'LLM Inference (single category)'));
        $this->line("Limit: {$limit}");
        $this->line("Chunk size: {$chunkSize}");
        $this->line("Parallel requests: {$parallel}");
        $this->line("Delay between batches: {$delayMs}ms");
        $this->line('Dry run: '.($dryRun ? 'Yes' : 'No'));
        $this->line('Force re-categorize: '.($force ? 'Yes' : 'No'));
        $this->newLine();

        // Build query for products needing categorization
        $query = AsinData::where('status', 'completed')
            ->whereNotNull('product_title')
            ->where('product_title', '!=', '');

        if (!$force) {
            // Only categorize products without category_tags
            $query->where(function ($q) {
                $q->whereNull('category_tags')
                    ->orWhereRaw('JSON_LENGTH(category_tags) = 0');
            });
        }

        $totalCount = $query->count();
        $toProcess = min($limit, $totalCount);

        $this->info("Found {$totalCount} products needing categorization");
        $this->info("Will process: {$toProcess}");

        // Estimate time
        $estimatedSeconds = $this->estimateTime($toProcess, $parallel, $delayMs);
        $this->line('Estimated time: '.gmdate('H:i:s', $estimatedSeconds));
        $this->newLine();

        if ($toProcess === 0) {
            $this->info('No products to categorize.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
            $this->newLine();

            $sample = $query->limit(10)->get();
            $this->table(
                ['ID', 'ASIN', 'Country', 'Title (truncated)', 'Current Category'],
                $sample->map(fn ($p) => [
                    $p->id,
                    $p->asin,
                    $p->country,
                    substr($p->product_title, 0, 40).'...',
                    $p->category ?? 'NULL', // Uses accessor that derives from category_tags
                ])->toArray()
            );

            // Show cost estimate (only for LLM mode)
            if (!$useScraping) {
                $this->newLine();
                $this->showCostEstimate($toProcess);
            } else {
                $this->newLine();
                $this->info('Scraping mode: No LLM costs, but uses bandwidth for HTTP requests.');
                if ($parallel > 1) {
                    $this->warn('TIP: If throttling occurs, try --parallel=1 --delay=2000 for sequential requests');
                }
            }

            return Command::SUCCESS;
        }

        // Initialize appropriate service based on mode
        $categorizationService = $useScraping ? null : app(ProductCategorizationService::class);
        $scrapingService = $useScraping ? app(AmazonProductDataService::class) : null;

        $processed = 0;
        $success = 0;
        $failed = 0;
        $throttled = 0;
        $startTime = microtime(true);

        $progressBar = $this->output->createProgressBar($toProcess);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | OK: %success% | Fail: %failed% | Throttled: %throttled%');
        $progressBar->setMessage('0', 'success');
        $progressBar->setMessage('0', 'failed');
        $progressBar->setMessage('0', 'throttled');
        $progressBar->start();

        // Get all products to process
        $allProducts = $query->limit($limit)->get();

        // Process in parallel batches
        foreach ($allProducts->chunk($parallel) as $batch) {
            if ($processed >= $limit) {
                break;
            }

            if ($useScraping && $parallel === 1) {
                // Sequential scraping mode - one request at a time with randomized delays
                foreach ($batch as $product) {
                    if ($processed >= $limit) {
                        break;
                    }

                    $result = $scrapingService->scrapeSingleProductCategory($product, $delayMs);

                    if ($result && !empty($result['category_tags'])) {
                        $product->update([
                            'category_source' => 'amazon_breadcrumb',
                            'category_tags'   => $result['category_tags'],
                        ]);
                        $success++;
                        $progressBar->setMessage((string) $success, 'success');
                    } else {
                        $failed++;
                        $progressBar->setMessage((string) $failed, 'failed');

                        // Track potential throttling (null result often means throttle)
                        if ($result === null) {
                            $throttled++;
                            $progressBar->setMessage((string) $throttled, 'throttled');

                            // Backoff on suspected throttle
                            $backoffMs = min(10000, $delayMs * 2);
                            usleep($backoffMs * 1000);
                        }
                    }

                    $processed++;
                    $progressBar->advance();
                }
            } elseif ($useScraping) {
                // Parallel scraping mode - multiple requests at once
                $batchResult = $scrapingService->scrapeCategoriesBatchParallel($batch->all(), $parallel);
                $results = $batchResult['results'];
                $batchThrottled = $batchResult['throttled'];
                $throttled += $batchThrottled;

                // Update throttle count in progress bar
                $progressBar->setMessage((string) $throttled, 'throttled');

                // Auto-backoff: if throttling detected, add extra delay
                if ($batchThrottled > 0) {
                    $backoffMs = min(5000, $delayMs + ($batchThrottled * 500)); // Max 5 second backoff
                    $progressBar->clear();
                    $this->warn("  Throttled ({$batchThrottled}) - backing off {$backoffMs}ms");
                    $progressBar->display();
                    usleep($backoffMs * 1000);
                }

                foreach ($batch as $product) {
                    if ($processed >= $limit) {
                        break;
                    }

                    if (isset($results[$product->id]) && !empty($results[$product->id]['category_tags'])) {
                        // Save the scraped category data
                        $product->update([
                            'category_source' => 'amazon_breadcrumb',
                            'category_tags'   => $results[$product->id]['category_tags'],
                        ]);
                        $success++;
                        $progressBar->setMessage((string) $success, 'success');
                    } else {
                        $failed++;
                        $progressBar->setMessage((string) $failed, 'failed');
                    }

                    $processed++;
                    $progressBar->advance();
                }

                // Force cleanup to prevent "too many open files" error
                unset($results, $batchResult);
                gc_collect_cycles();
            } elseif ($parallel > 1) {
                // LLM mode - Parallel processing
                $results = $categorizationService->categorizeBatchParallel($batch->all());

                foreach ($batch as $product) {
                    if ($processed >= $limit) {
                        break;
                    }

                    if (isset($results[$product->id]) && $results[$product->id]) {
                        $success++;
                        $progressBar->setMessage((string) $success, 'success');
                    } else {
                        $failed++;
                        $progressBar->setMessage((string) $failed, 'failed');
                    }

                    $processed++;
                    $progressBar->advance();
                }
            } else {
                // LLM mode - Sequential processing
                foreach ($batch as $product) {
                    if ($processed >= $limit) {
                        break;
                    }

                    try {
                        $result = $categorizationService->categorizeAndSave($product);

                        if ($result) {
                            $success++;
                            $progressBar->setMessage((string) $success, 'success');
                        } else {
                            $failed++;
                            $progressBar->setMessage((string) $failed, 'failed');
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $progressBar->setMessage((string) $failed, 'failed');
                        LoggingService::log('Product categorization error in batch', [
                            'product_id' => $product->id,
                            'error'      => $e->getMessage(),
                        ]);
                    }

                    $processed++;
                    $progressBar->advance();
                }
            }

            // Rate limiting - pause between batches
            usleep($delayMs * 1000);
        }

        $progressBar->finish();
        $this->newLine(2);

        $elapsedTime = microtime(true) - $startTime;

        $this->info('Categorization Complete');
        $this->info('=======================');
        $this->line("Processed: {$processed}");
        $this->line("Success: {$success}");
        $this->line("Failed: {$failed}");
        if ($throttled > 0) {
            $this->warn("Throttled: {$throttled} (Amazon rate limiting detected)");
        }
        $this->line('Time: '.gmdate('H:i:s', (int) $elapsedTime)." ({$processed} products)");
        $this->line('Rate: '.round($processed / max(1, $elapsedTime), 1).' products/sec');

        // Show sample of categorized products
        $this->newLine();
        $this->info('Sample of recently categorized products:');

        $sourceFilter = $useScraping ? 'amazon_breadcrumb' : 'llm_inference';
        $recentlyCategorized = AsinData::withCategoryTags()
            ->where('category_source', $sourceFilter)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        if ($recentlyCategorized->isNotEmpty()) {
            if ($useScraping) {
                // Show full path for scraped products
                $this->table(
                    ['ASIN', 'Category Path', 'Depth'],
                    $recentlyCategorized->map(fn ($p) => [
                        $p->asin,
                        $p->category_path, // Full breadcrumb path
                        count($p->category_tags ?? []),
                    ])->toArray()
                );
            } else {
                $this->table(
                    ['ASIN', 'Category', 'Title (truncated)'],
                    $recentlyCategorized->map(fn ($p) => [
                        $p->asin,
                        $p->category, // Derived via accessor from category_tags
                        substr($p->product_title, 0, 40).'...',
                    ])->toArray()
                );
            }
        }

        // Show category distribution (using JSON extraction for grouping)
        $this->newLine();
        $this->info('Category distribution (top 10):');

        // Use raw SQL to extract leaf category from JSON array for grouping
        $distribution = AsinData::withCategoryTags()
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(category_tags, CONCAT('$[', JSON_LENGTH(category_tags) - 1, ']'))) as leaf_category, COUNT(*) as count")
            ->groupBy('leaf_category')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'leaf_category')
            ->toArray();

        if (!empty($distribution)) {
            $this->table(
                ['Category', 'Count'],
                collect($distribution)->map(fn ($count, $cat) => [$cat, $count])->toArray()
            );
        }

        return $failed === $processed ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Estimate processing time.
     */
    private function estimateTime(int $count, int $parallel, int $delayMs): int
    {
        // Average LLM response time ~300ms + delay between batches
        $avgResponseMs = 300;
        $batches = ceil($count / $parallel);
        $totalMs = $batches * ($avgResponseMs + $delayMs);

        return (int) ($totalMs / 1000);
    }

    /**
     * Show cost estimate for DeepSeek.
     */
    private function showCostEstimate(int $count): void
    {
        // DeepSeek pricing
        $inputPricePerMillion = 0.14;
        $outputPricePerMillion = 0.28;

        // Estimate tokens per request
        $inputTokensPerRequest = 150;  // Prompt + category list + title
        $outputTokensPerRequest = 10;   // Just the category name

        $totalInputTokens = $count * $inputTokensPerRequest;
        $totalOutputTokens = $count * $outputTokensPerRequest;

        $inputCost = ($totalInputTokens / 1_000_000) * $inputPricePerMillion;
        $outputCost = ($totalOutputTokens / 1_000_000) * $outputPricePerMillion;
        $totalCost = $inputCost + $outputCost;

        $this->info('DeepSeek Cost Estimate:');
        $this->line('  Input tokens: '.number_format($totalInputTokens).' (~$'.number_format($inputCost, 2).')');
        $this->line('  Output tokens: '.number_format($totalOutputTokens).' (~$'.number_format($outputCost, 2).')');
        $this->line('  Total: ~$'.number_format($totalCost, 2));
    }
}

