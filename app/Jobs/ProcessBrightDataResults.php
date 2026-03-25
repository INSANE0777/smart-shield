<?php

namespace App\Jobs;

use App\Models\AsinData;
use App\Services\Amazon\BrightDataScraperService;
use App\Services\LoggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBrightDataResults implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes to download and process data
    public $backoff = [30, 60, 120];

    public function __construct(
        public string $asin,
        public string $country,
        public string $jobId,
        private ?BrightDataScraperService $mockService = null
    ) {
        $this->onQueue('brightdata');
    }

    public function handle(): void
    {
        LoggingService::log('Processing BrightData results', [
            'asin'   => $this->asin,
            'job_id' => $this->jobId,
        ]);

        try {
            $brightDataService = $this->mockService ?? new BrightDataScraperService();

            // Download the job data
            $results = $this->fetchJobData($brightDataService, $this->jobId);

            if (empty($results)) {
                throw new \Exception('No results returned from BrightData job');
            }

            // Transform the data to our internal format
            $transformedData = $this->transformBrightDataResults($brightDataService, $results, $this->asin);

            // Save to database
            $asinData = $this->saveResults($transformedData);

            LoggingService::log('BrightData results processed successfully', [
                'asin'              => $this->asin,
                'job_id'            => $this->jobId,
                'reviews_count'     => count($transformedData['reviews']),
                'have_product_data' => $asinData->have_product_data,
            ]);
        } catch (\Exception $e) {
            LoggingService::log('Failed to process BrightData results', [
                'asin'   => $this->asin,
                'job_id' => $this->jobId,
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function fetchJobData(BrightDataScraperService $service, string $jobId): array
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('fetchJobData');
        $method->setAccessible(true);

        return $method->invoke($service, $jobId);
    }

    private function transformBrightDataResults(BrightDataScraperService $service, array $results, string $asin): array
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('transformBrightDataResults');
        $method->setAccessible(true);

        return $method->invoke($service, $results, $asin);
    }

    private function saveResults(array $transformedData): AsinData
    {
        // Save to database using existing columns only
        $asinData = AsinData::firstOrCreate(
            ['asin' => $this->asin, 'country' => $this->country],
            ['status' => 'pending_analysis']
        );

        // CRITICAL: Never overwrite a completed analysis - this job may run AFTER
        // the main analysis has already finished
        if ($asinData->isAnalyzed()) {
            LoggingService::log('BrightData results skipped - analysis already complete', [
                'asin'   => $this->asin,
                'status' => $asinData->status,
                'grade'  => $asinData->grade,
            ]);

            return $asinData;
        }

        $asinData->reviews = json_encode($transformedData['reviews']);
        $asinData->product_description = $transformedData['description'] ?? '';
        $asinData->total_reviews_on_amazon = $transformedData['total_reviews'] ?? count($transformedData['reviews']);
        $asinData->country = $this->country;
        $asinData->status = 'pending_analysis';

        // Extract product data if available from BrightData
        $hasProductTitle = false;
        $hasProductImage = false;

        if (!empty($transformedData['product_name'])) {
            $asinData->product_title = $transformedData['product_name'];
            $hasProductTitle = true;
        }
        if (!empty($transformedData['product_image_url'])) {
            $asinData->product_image_url = $transformedData['product_image_url'];
            $hasProductImage = true;
        }

        // Only set have_product_data = true if we actually have both title and image
        // If BrightData doesn't provide complete product metadata, let AmazonProductDataService handle it
        $asinData->have_product_data = $hasProductTitle && $hasProductImage;

        LoggingService::log('BrightData product data extraction results', [
            'asin'                           => $this->asin,
            'has_product_title'              => $hasProductTitle,
            'has_product_image'              => $hasProductImage,
            'have_product_data'              => $asinData->have_product_data,
            'will_trigger_separate_scraping' => !$asinData->have_product_data,
        ]);

        $asinData->save();

        return $asinData;
    }

    public function failed(\Throwable $exception): void
    {
        LoggingService::log('BrightData results processing job failed permanently', [
            'asin'   => $this->asin,
            'job_id' => $this->jobId,
            'error'  => $exception->getMessage(),
        ]);
    }
}
