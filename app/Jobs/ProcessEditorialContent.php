<?php

namespace App\Jobs;

use App\Models\AsinData;
use App\Services\EditorialContentService;
use App\Services\LoggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for generating editorial content independently of main review analysis.
 *
 * This job runs on a separate queue (editorial-content) and does not block
 * or impact the main analysis flow. It's designed to generate unique,
 * substantive editorial content for product pages to satisfy AdSense
 * content quality requirements.
 */
class ProcessEditorialContent implements ShouldQueue
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
    public int $timeout = 180;

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

        // Use dedicated editorial-content queue
        $this->onQueue('editorial-content');
    }

    /**
     * Execute the job.
     */
    public function handle(EditorialContentService $editorialService): void
    {
        $asinData = AsinData::find($this->asinDataId);

        if (!$asinData) {
            Log::warning('Editorial content job: AsinData not found', [
                'asin_data_id' => $this->asinDataId,
            ]);

            return;
        }

        // Skip if already completed
        if ($asinData->hasEditorialContent()) {
            LoggingService::log('Editorial content already generated, skipping', [
                'asin' => $asinData->asin,
            ]);

            return;
        }

        // Skip if main review analysis isn't complete
        if (!$asinData->isAnalyzed()) {
            LoggingService::log('Main analysis not complete, skipping editorial content', [
                'asin'   => $asinData->asin,
                'status' => $asinData->status,
            ]);

            return;
        }

        try {
            LoggingService::log('Starting editorial content job', [
                'asin'    => $asinData->asin,
                'country' => $asinData->country,
                'attempt' => $this->attempts(),
            ]);

            $editorialService->generateEditorialContent($asinData);

            LoggingService::log('Editorial content job completed successfully', [
                'asin' => $asinData->asin,
            ]);
        } catch (\Exception $e) {
            LoggingService::log('Editorial content job failed', [
                'asin'    => $asinData->asin,
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw for retry mechanism if not final attempt
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            // On final failure, just log - don't break anything
            Log::error('Editorial content job failed permanently', [
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
            $asinData->update(['editorial_status' => 'failed']);
        }

        Log::error('Editorial content job failed permanently', [
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
        return ['editorial-content', 'asin:'.$this->asinDataId];
    }
}
