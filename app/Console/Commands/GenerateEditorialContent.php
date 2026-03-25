<?php

namespace App\Console\Commands;

use App\Models\AsinData;
use App\Services\EditorialContentService;
use Illuminate\Console\Command;

/**
 * Console command for batch generating editorial content on existing products.
 *
 * Supports concurrent processing for faster batch generation.
 * This command is designed for backfilling editorial content on products
 * that were analyzed before the editorial content feature was added.
 */
class GenerateEditorialContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:editorial
                            {--days=30 : Process products analyzed within the last X days}
                            {--asin= : Process a specific ASIN}
                            {--country= : Country code for specific ASIN (optional)}
                            {--force : Re-generate even if editorial content already exists}
                            {--dry-run : Show what would be processed without actually processing}
                            {--limit= : Maximum number of products to process}
                            {--concurrent=5 : Concurrent API requests (1-15, recommended: 8-10 for DeepSeek)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate editorial content for products in the database';

    /**
     * Execute the console command.
     */
    public function handle(EditorialContentService $editorialService): int
    {
        if (!$editorialService->isAvailable()) {
            $this->error('Editorial content service is not available. Check LLM API configuration.');

            return Command::FAILURE;
        }

        $this->info('Using LLM provider: '.$editorialService->getProviderName());
        $this->newLine();

        $products = $this->getProductsToProcess();

        if ($products->isEmpty()) {
            $this->info('No products found matching the criteria.');

            return Command::SUCCESS;
        }

        $this->info("Found {$products->count()} product(s) to process.");

        if ($this->option('dry-run')) {
            return $this->handleDryRun($products);
        }

        return $this->processProducts($products, $editorialService);
    }

    /**
     * Get products to process based on command options.
     */
    private function getProductsToProcess(): \Illuminate\Support\Collection
    {
        $query = $this->buildQuery(
            $this->option('asin'),
            $this->option('country'),
            (int) $this->option('days'),
            $this->option('force')
        );

        if ($this->option('limit')) {
            $query->limit((int) $this->option('limit'));
        }

        return $query->get();
    }

    /**
     * Handle dry run mode.
     */
    private function handleDryRun($products): int
    {
        $this->info('DRY RUN - No changes will be made.');
        $this->newLine();
        $this->displayProductList($products);

        return Command::SUCCESS;
    }

    /**
     * Process the products with editorial content generation using concurrent HTTP requests.
     */
    private function processProducts($products, EditorialContentService $editorialService): int
    {
        // Allow up to 15 concurrent requests (DeepSeek can handle high concurrency)
        // User can specify lower values if hitting rate limits
        $concurrent = max(1, min(15, (int) $this->option('concurrent')));
        $total = $products->count();

        // Estimate time: DeepSeek averages ~8-15s per editorial request
        // With concurrency, we process $concurrent at a time
        $batches = ceil($total / $concurrent);
        $estimatedSeconds = $batches * 12; // ~12s average per batch (network + LLM generation)
        $this->info("Processing {$total} products with {$concurrent} concurrent requests");
        $this->info('Estimated time: '.gmdate('H:i:s', (int) $estimatedSeconds)." ({$batches} batches)");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $success = 0;
        $failed = 0;

        // Process in chunks for concurrent execution
        $chunks = $products->chunk($concurrent);

        foreach ($chunks as $chunk) {
            $results = $editorialService->generateBatchConcurrently($chunk->all());

            foreach ($results as $productId => $result) {
                if ($result['success']) {
                    $success++;
                } else {
                    $failed++;
                    // Log failure but don't stop processing
                    $product = $chunk->firstWhere('id', $productId);
                    if ($product) {
                        $this->newLine();
                        $this->error("Failed: {$product->asin} - {$result['error']}");
                    }
                }
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->displayResults($success, $failed);

        return Command::SUCCESS; // Don't fail command for individual product failures
    }

    /**
     * Display final results summary.
     */
    private function displayResults(int $success, int $failed): void
    {
        $this->newLine(2);
        $this->info('Editorial content generation complete.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed', $success + $failed],
                ['Successful', $success],
                ['Failed', $failed],
            ]
        );
    }

    /**
     * Build the query for products to process.
     */
    private function buildQuery(?string $asin, ?string $country, int $days, bool $force): \Illuminate\Database\Eloquent\Builder
    {
        $query = AsinData::query();

        // Specific ASIN mode
        if ($asin) {
            $query->where('asin', $asin);

            // Only filter by country if explicitly specified
            if ($country) {
                $query->where('country', $country);
            }

            if (!$force) {
                // Include pending, failed, or NULL status
                // Note: 'pending' is the DB default for unprocessed products
                // Duplicate processing is prevented by hasEditorialContent() check in service
                $query->where(function ($q) {
                    $q->whereNull('editorial_status')
                        ->orWhere('editorial_status', 'pending')
                        ->orWhere('editorial_status', 'failed');
                });
            }

            return $query;
        }

        // Batch mode - products from last X days
        $query->where('status', 'completed')
            ->where('have_product_data', true)
            ->whereNotNull('product_title')
            ->whereNotNull('grade')
            ->whereNotNull('fake_percentage')
            ->where('first_analyzed_at', '>=', now()->subDays($days));

        // Unless forcing, only process products that haven't been processed
        // Note: 'pending' is the DB default for unprocessed products
        // Duplicate processing is prevented by hasEditorialContent() check in service
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('editorial_status')
                    ->orWhere('editorial_status', 'pending')
                    ->orWhere('editorial_status', 'failed');
            });
        }

        // Order by most recently analyzed first
        $query->orderBy('first_analyzed_at', 'desc');

        return $query;
    }

    /**
     * Display the list of products that would be processed.
     */
    private function displayProductList($products): void
    {
        $tableData = $products->map(fn ($product) => $this->formatProductRow($product))->toArray();

        $this->table(
            ['ASIN', 'Country', 'Title', 'Grade', 'Editorial Status', 'Analyzed At'],
            $tableData
        );
    }

    /**
     * Format a single product row for display.
     */
    private function formatProductRow(AsinData $product): array
    {
        return [
            $product->asin,
            $product->country,
            $this->truncateTitle($product->product_title),
            $product->grade ?? 'N/A',
            $product->editorial_status ?? 'pending',
            $product->first_analyzed_at?->format('Y-m-d H:i') ?? 'N/A',
        ];
    }

    /**
     * Truncate title to display length.
     */
    private function truncateTitle(?string $title): string
    {
        if (empty($title)) {
            return 'N/A';
        }

        return strlen($title) > 35 ? substr($title, 0, 35).'...' : $title;
    }
}

