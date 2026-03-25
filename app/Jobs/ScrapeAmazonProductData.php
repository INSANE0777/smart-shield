<?php

namespace App\Jobs;

use App\Models\AsinData;
use App\Services\Amazon\AmazonProductDataService;
use App\Services\LoggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScrapeAmazonProductData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AsinData $asinData
    ) {
        // Set the queue name for product scraping jobs
        $this->onQueue('product-scraping');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        LoggingService::log('Starting Amazon product data scraping job', [
            'asin'    => $this->asinData->asin,
            'country' => $this->asinData->country,
            'id'      => $this->asinData->id,
        ]);

        try {
            // Check if product data has already been scraped
            if ($this->asinData->have_product_data) {
                LoggingService::log('Product data already scraped, skipping', [
                    'asin' => $this->asinData->asin,
                ]);

                return;
            }

            // Create the product data service
            $productDataService = app(AmazonProductDataService::class);

            // Scrape the product data
            $productData = $productDataService->scrapeProductData(
                $this->asinData->asin,
                $this->asinData->country
            );

            if (empty($productData)) {
                LoggingService::log('Product data scraping failed - continuing without product metadata', [
                    'asin'    => $this->asinData->asin,
                    'country' => $this->asinData->country,
                    'reason'  => 'Amazon may be blocking scraper or page structure changed',
                ]);

                // Mark as attempted but don't fail the job
                $this->asinData->update([
                    'product_data_scraped_at' => now(),
                    'have_product_data'       => false,
                ]);

                return;
            }

            // Only update fields that are actually missing or empty
            // Don't overwrite existing good data with fallback/mock data
            $updateData = [
                'product_data_scraped_at' => now(),
            ];

            // Only update title if we don't have one or if the scraped data is better than placeholder
            if (empty($this->asinData->product_title) ||
                (str_starts_with($this->asinData->product_title, 'Test Product') &&
                 !empty($productData['title']) &&
                 !str_starts_with($productData['title'], 'Test Product'))) {
                $updateData['product_title'] = $productData['title'] ?? null;
            }

            // Only update image if we don't have one or if the scraped data is better than placeholder
            if (empty($this->asinData->product_image_url) ||
                (str_contains($this->asinData->product_image_url, 'placeholder') &&
                 !empty($productData['image_url']) &&
                 !str_contains($productData['image_url'], 'placeholder'))) {
                $updateData['product_image_url'] = $productData['image_url'] ?? null;
            }

            // Update category_tags if we got it from the breadcrumb and don't already have it
            // category_tags is the ONLY stored field - category/category_path are derived via model accessors
            if (!empty($productData['category_tags']) && empty($this->asinData->category_tags)) {
                $updateData['category_tags'] = $productData['category_tags'];
                $updateData['category_source'] = 'amazon_breadcrumb';
            }

            // Set have_product_data = true if we have both title and image now
            $hasTitle = !empty($this->asinData->product_title) || !empty($updateData['product_title']);
            $hasImage = !empty($this->asinData->product_image_url) || !empty($updateData['product_image_url']);
            $updateData['have_product_data'] = $hasTitle && $hasImage;

            $this->asinData->update($updateData);

            // Clear sitemap cache since product data affects SEO URLs and priorities
            \App\Http\Controllers\SitemapController::clearCache();

            LoggingService::log('Successfully scraped and saved Amazon product data', [
                'asin'          => $this->asinData->asin,
                'title'         => $productData['title'] ?? 'N/A',
                'has_image'     => !empty($productData['image_url']),
                'category_tags' => $productData['category_tags'] ?? [],
            ]);
        } catch (\Exception $e) {
            LoggingService::log('Failed to scrape Amazon product data', [
                'asin'    => $this->asinData->asin,
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Log the error for monitoring
            Log::error('Amazon product data scraping failed', [
                'asin'         => $this->asinData->asin,
                'error'        => $e->getMessage(),
                'attempt'      => $this->attempts(),
                'max_attempts' => $this->tries,
            ]);

            // Re-throw the exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        LoggingService::log('Amazon product data scraping job failed permanently', [
            'asin'     => $this->asinData->asin,
            'error'    => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Optionally, you could send an alert or notification here
        // For now, we'll just log the failure
        Log::error('Amazon product data scraping job failed permanently', [
            'asin'     => $this->asinData->asin,
            'error'    => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
