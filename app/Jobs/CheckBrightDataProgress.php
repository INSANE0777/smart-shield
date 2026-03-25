<?php

namespace App\Jobs;

use App\Services\Amazon\BrightDataScraperService;
use App\Services\LoggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckBrightDataProgress implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 10; // Maximum attempts to check progress
    public $timeout = 60;
    public $backoff = [30]; // Always wait 30 seconds between attempts

    public function __construct(
        public string $asin,
        public string $country,
        public string $jobId,
        public int $attempt = 1,
        private ?BrightDataScraperService $mockService = null
    ) {
        $this->onQueue('brightdata');
    }

    public function handle(): void
    {
        LoggingService::log('Checking BrightData progress', [
            'asin'         => $this->asin,
            'job_id'       => $this->jobId,
            'attempt'      => $this->attempt,
            'max_attempts' => $this->tries,
        ]);

        try {
            $brightDataService = $this->mockService ?? new BrightDataScraperService();
            $progressInfo = $this->getJobProgressInfo($brightDataService, $this->jobId);

            $status = $progressInfo['status'] ?? 'unknown';
            $totalRows = $progressInfo['total_rows'] ?? 0;

            LoggingService::log('BrightData job progress check', [
                'job_id'     => $this->jobId,
                'attempt'    => $this->attempt,
                'status'     => $status,
                'total_rows' => $totalRows,
            ]);

            if ($status === 'ready') {
                // Job completed successfully, chain the results processing job
                LoggingService::log('BrightData job completed, processing results', [
                    'asin'       => $this->asin,
                    'job_id'     => $this->jobId,
                    'total_rows' => $totalRows,
                ]);

                ProcessBrightDataResults::dispatch($this->asin, $this->country, $this->jobId)
                    ->onQueue('brightdata');

                return; // Job chain continues to results processing
            }

            if ($status === 'failed' || $status === 'error') {
                throw new \Exception("BrightData job failed with status: {$status}");
            }

            if ($status === 'running' && $this->attempt < $this->tries) {
                // Job still running, schedule another check
                LoggingService::log('BrightData job still running, scheduling next check', [
                    'asin'         => $this->asin,
                    'job_id'       => $this->jobId,
                    'next_attempt' => $this->attempt + 1,
                ]);

                CheckBrightDataProgress::dispatch($this->asin, $this->country, $this->jobId, $this->attempt + 1)
                    ->delay(now()->addSeconds(30))
                    ->onQueue('brightdata');

                return; // Current job completes, next check is scheduled
            }

            // Max attempts reached or unknown status
            throw new \Exception("BrightData job timeout or unknown status after {$this->attempt} attempts. Final status: {$status}");
        } catch (\Exception $e) {
            LoggingService::log('BrightData progress check failed', [
                'asin'    => $this->asin,
                'job_id'  => $this->jobId,
                'attempt' => $this->attempt,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getJobProgressInfo(BrightDataScraperService $service, string $jobId): array
    {
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getJobProgressInfo');
        $method->setAccessible(true);

        return $method->invoke($service, $jobId);
    }

    public function failed(\Throwable $exception): void
    {
        LoggingService::log('BrightData progress check job failed permanently', [
            'asin'    => $this->asin,
            'job_id'  => $this->jobId,
            'attempt' => $this->attempt,
            'error'   => $exception->getMessage(),
        ]);
    }
}
