<?php

namespace App\Console\Commands;

use App\Models\AsinData;
use App\Services\PriceAnalysisService;
use Illuminate\Console\Command;

/**
 * Console command for batch processing price analysis on existing products.
 *
 * Supports concurrent processing for faster batch analysis.
 */
class AnalyzePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analyze:prices
                            {--days=1 : Process products analyzed within the last X days}
                            {--asin= : Process a specific ASIN (uses country from database)}
                            {--country= : Country code for specific ASIN (optional, defaults to database value)}
                            {--force : Re-analyze even if price analysis already exists}
                            {--dry-run : Show what would be processed without actually processing}
                            {--limit= : Maximum number of products to process}
                            {--concurrent=5 : Number of concurrent API requests (default: 5)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run price analysis on products in the database';

    /**
     * Execute the console command.
     */
    public function handle(PriceAnalysisService $priceService): int
    {
        if (!$priceService->isAvailable()) {
            $this->error('Price analysis service is not available. Check LLM API configuration.');

            return Command::FAILURE;
        }

        $products = $this->getProductsToProcess();

        if ($products->isEmpty()) {
            $this->info('No products found matching the criteria.');

            return Command::SUCCESS;
        }

        $this->info("Found {$products->count()} product(s) to process.");

        if ($this->option('dry-run')) {
            return $this->handleDryRun($products);
        }

        return $this->processProducts($products, $priceService);
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
     * Process the products with price analysis using concurrent HTTP requests.
     */
    private function processProducts($products, PriceAnalysisService $priceService): int
    {
        $concurrent = max(1, min(10, (int) $this->option('concurrent'))); // Cap at 10
        $total = $products->count();

        // Estimate time: ~4s per batch of concurrent requests
        $batches = ceil($total / $concurrent);
        $estimatedSeconds = $batches * 5; // ~5s per batch including overhead
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
            $results = $priceService->analyzeBatchConcurrently($chunk->all());

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
     * Analyze a single product.
     */
    private function analyzeProduct(AsinData $product, PriceAnalysisService $priceService): bool
    {
        try {
            $priceService->analyzePricing($product);

            return true;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("Failed to analyze {$product->asin}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Display final results summary.
     */
    private function displayResults(int $success, int $failed): void
    {
        $this->newLine(2);
        $this->info('Price analysis complete.');
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
                $query->where(function ($q) {
                    $q->whereNull('price_analysis_status')
                        ->orWhere('price_analysis_status', 'pending')
                        ->orWhere('price_analysis_status', 'failed');
                });
            }

            return $query;
        }

        // Batch mode - products from last X days
        $query->where('status', 'completed')
            ->where('have_product_data', true)
            ->whereNotNull('product_title')
            ->where('first_analyzed_at', '>=', now()->subDays($days));

        // Unless forcing, only process products that haven't been analyzed
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('price_analysis_status')
                    ->orWhere('price_analysis_status', 'pending')
                    ->orWhere('price_analysis_status', 'failed');
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
            ['ASIN', 'Country', 'Title', 'Price Status', 'Analyzed At'],
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
            $product->price_analysis_status ?? 'pending',
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

        return strlen($title) > 40 ? substr($title, 0, 40).'...' : $title;
    }
}
