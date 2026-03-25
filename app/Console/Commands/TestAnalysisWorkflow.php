<?php

namespace App\Console\Commands;

use App\Jobs\ProcessProductAnalysis;
use App\Models\AnalysisSession;
use App\Services\ReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestAnalysisWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:analysis-workflow 
                            {url : Amazon product URL or ASIN to analyze}
                            {--sync : Run synchronously instead of using queue}
                            {--detailed : Show detailed progress output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the complete analysis workflow with a given Amazon URL or ASIN';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = $this->argument('url');
        $sync = $this->option('sync');
        $verbose = $this->option('detailed');

        $this->info("Testing analysis workflow for: {$input}");
        $this->newLine();

        try {
            // Parse and validate the input
            $productUrl = $this->parseInput($input);
            $this->info("Parsed URL: {$productUrl}");

            // Extract ASIN and country for logging
            $reviewService = app(ReviewService::class);
            $asin = $reviewService->extractAsin($productUrl);
            $country = $reviewService->extractCountryFromUrl($productUrl);

            $this->info("ASIN: {$asin}");
            $this->info("Country: {$country}");
            $this->newLine();

            // Create analysis session
            $session = $this->createAnalysisSession($asin, $productUrl);
            $this->info("Created analysis session: {$session->id}");

            if ($sync) {
                $this->info('Running analysis synchronously...');
                $this->runSynchronousAnalysis($session, $productUrl, $verbose);
            } else {
                $this->info('Dispatching analysis job to queue...');
                $this->runAsynchronousAnalysis($session, $productUrl, $verbose);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Analysis failed: {$e->getMessage()}");
            if ($verbose) {
                $this->error("Stack trace: {$e->getTraceAsString()}");
            }

            return Command::FAILURE;
        }
    }

    private function parseInput(string $input): string
    {
        // If it's already a full URL, return it
        if (str_starts_with($input, 'http')) {
            return $input;
        }

        // If it looks like an ASIN (10 characters, alphanumeric), create a URL
        if (preg_match('/^[A-Z0-9]{10}$/', $input)) {
            return "https://www.amazon.com/dp/{$input}/";
        }

        throw new \InvalidArgumentException('Invalid input. Provide either a full Amazon URL or a 10-character ASIN.');
    }

    private function createAnalysisSession(string $asin, string $productUrl): AnalysisSession
    {
        return AnalysisSession::create([
            'id'                  => (string) Str::uuid(),
            'user_session'        => 'console-test-'.time(),
            'asin'                => $asin,
            'product_url'         => $productUrl,
            'status'              => 'pending',
            'current_step'        => 0,
            'progress_percentage' => 0.0,
            'current_message'     => 'Queued for analysis...',
            'total_steps'         => 7,
        ]);
    }

    private function runSynchronousAnalysis(AnalysisSession $session, string $productUrl, bool $verbose): void
    {
        $this->info('Executing ProcessProductAnalysis job synchronously...');
        $this->newLine();

        $job = new ProcessProductAnalysis($session->id, $productUrl);

        if ($verbose) {
            $this->startProgressMonitoring($session);
        }

        // Execute the job directly
        $job->handle();

        // Refresh session to get final state
        $session->refresh();

        $this->displayResults($session);
    }

    private function runAsynchronousAnalysis(AnalysisSession $session, string $productUrl, bool $verbose): void
    {
        // Dispatch the job to the queue
        ProcessProductAnalysis::dispatch($session->id, $productUrl);

        $this->info("Job dispatched to queue. Session ID: {$session->id}");
        $this->newLine();

        if ($verbose) {
            $this->info('Monitoring progress (press Ctrl+C to stop monitoring)...');
            $this->monitorAsyncProgress($session);
        } else {
            $this->info('Use --detailed flag to monitor progress in real-time.');
            $this->info('Check session status with: php artisan tinker');
            $this->info("Then run: \\App\\Models\\AnalysisSession::find('{$session->id}')");
        }
    }

    private function startProgressMonitoring(AnalysisSession $session): void
    {
        // Start a background process to monitor progress
        $this->info('Progress monitoring enabled. Updates will appear below:');
        $this->newLine();
    }

    private function monitorAsyncProgress(AnalysisSession $session): void
    {
        $maxWaitTime = 600; // 10 minutes
        $startTime = time();
        $lastStep = 0;

        while (time() - $startTime < $maxWaitTime) {
            $session->refresh();

            // Show progress updates
            if ($session->current_step > $lastStep) {
                $this->info(sprintf(
                    'Step %d/7 (%d%%): %s',
                    $session->current_step,
                    (int) $session->progress_percentage,
                    $session->current_message
                ));
                $lastStep = $session->current_step;
            }

            // Check if completed
            if ($session->isCompleted()) {
                $this->newLine();
                $this->info('Analysis completed successfully!');
                $this->displayResults($session);

                return;
            }

            // Check if failed
            if ($session->isFailed()) {
                $this->newLine();
                $this->error("Analysis failed: {$session->error_message}");

                return;
            }

            sleep(2); // Poll every 2 seconds
        }

        $this->error('Timeout waiting for analysis to complete.');
    }

    private function displayResults(AnalysisSession $session): void
    {
        $this->newLine();
        $this->info('=== ANALYSIS RESULTS ===');

        $this->table([
            'Field', 'Value',
        ], [
            ['Session ID', $session->id],
            ['ASIN', $session->asin],
            ['Status', $session->status],
            ['Progress', $session->progress_percentage.'%'],
            ['Current Step', $session->current_step.'/7'],
            ['Message', $session->current_message],
            ['Started At', $session->started_at?->format('Y-m-d H:i:s') ?? 'Not started'],
            ['Completed At', $session->completed_at?->format('Y-m-d H:i:s') ?? 'Not completed'],
        ]);

        if ($session->isCompleted() && $session->result) {
            $this->newLine();
            $this->info('=== FINAL ANALYSIS DATA ===');

            $result = $session->result;
            if (isset($result['asin_data'])) {
                $asinData = $result['asin_data'];
                $this->table([
                    'Metric', 'Value',
                ], [
                    ['Product Title', $asinData['product_title'] ?? 'N/A'],
                    ['Total Reviews', $asinData['total_reviews_on_amazon'] ?? 'N/A'],
                    ['Fake Percentage', ($asinData['fake_percentage'] ?? 'N/A').'%'],
                    ['Grade', $asinData['grade'] ?? 'N/A'],
                    ['Amazon Rating', $asinData['amazon_rating'] ?? 'N/A'],
                    ['Adjusted Rating', $asinData['adjusted_rating'] ?? 'N/A'],
                    ['Have Product Data', $asinData['have_product_data'] ? 'Yes' : 'No'],
                ]);

                if (isset($result['redirect_url'])) {
                    $this->newLine();
                    $this->info("View results at: {$result['redirect_url']}");
                }
            }
        }

        if ($session->isFailed()) {
            $this->newLine();
            $this->error("Error: {$session->error_message}");
        }
    }
}
