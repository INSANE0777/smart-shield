<?php

namespace App\Jobs;

use App\Models\AsinData;
use App\Services\LoggingService;
use App\Services\PriceAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for processing price analysis independently of main review analysis.
 *
 * This job runs on a separate queue (price-analysis) and does not block
 * or impact the main analysis flow. It's designed to be a value-add feature
 * that completes asynchronously.
 */
class ProcessPriceAnalysis implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times to retry the job.
     */
    public int $tries = 2;

    /**
     * Timeout for the job in seconds.
     */
    public int $timeout = 120;

    /**
     * Backoff delays for retries.
     */
    public array $backoff = [30, 60];

    /**
     * The ASIN data ID to process.
     */
    private int $asinDataId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $asinDataId)
    {
        $this->asinDataId = $asinDataId;

        // Use dedicated price-analysis queue
        $this->onQueue('price-analysis');
    }

    /**
     * Execute the job.
     */
    public function handle(PriceAnalysisService $priceService): void
    {
        $asinData = AsinData::find($this->asinDataId);

        if (!$asinData) {
            Log::warning('Price analysis job: AsinData not found', [
                'asin_data_id' => $this->asinDataId,
            ]);

            return;
        }

        // Skip if already completed or doesn't need analysis
        if ($asinData->hasPriceAnalysis()) {
            LoggingService::log('Price analysis already completed, skipping', [
                'asin' => $asinData->asin,
            ]);

            return;
        }

        // Skip if main review analysis isn't complete
        if (!$asinData->isAnalyzed()) {
            LoggingService::log('Main analysis not complete, skipping price analysis', [
                'asin'   => $asinData->asin,
                'status' => $asinData->status,
            ]);

            return;
        }

        try {
            LoggingService::log('Starting price analysis job', [
                'asin'    => $asinData->asin,
                'country' => $asinData->country,
                'attempt' => $this->attempts(),
            ]);

            $priceService->analyzePricing($asinData);

            LoggingService::log('Price analysis job completed successfully', [
                'asin' => $asinData->asin,
            ]);
        } catch (\Exception $e) {
            LoggingService::log('Price analysis job failed', [
                'asin'    => $asinData->asin,
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw for retry mechanism if not final attempt
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            // On final failure, just log - don't break anything
            Log::error('Price analysis job failed permanently', [
                'asin_data_id' => $this->asinDataId,
                'asin'         => $asinData->asin,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $asinData = AsinData::find($this->asinDataId);

        if ($asinData) {
            $asinData->update(['price_analysis_status' => 'failed']);
        }

        Log::error('Price analysis job failed permanently', [
            'asin_data_id' => $this->asinDataId,
            'error'        => $exception->getMessage(),
            'attempts'     => $this->attempts(),
        ]);
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return ['price-analysis', 'asin:'.$this->asinDataId];
    }
}
