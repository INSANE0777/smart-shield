<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BrightDataManager extends Command
{
    protected $signature = 'brightdata:manage 
                           {action : Action to perform: analyze|cancel|check|progress|snapshots|cleanup|complete|download|monitor|cancel-job|list-jobs|cancel-all-running}
                           {--job-id= : Specific job ID to operate on}
                           {--status= : Filter by job status}
                           {--limit=100 : Limit number of jobs to process}
                           {--force : Force action without confirmation}';

    protected $description = 'Unified BrightData job management (replaces 9 separate commands)';

    public function handle()
    {
        $action = $this->argument('action');

        $this->info("BrightData Manager - Action: {$action}");

        switch ($action) {
            case 'analyze':
                return $this->analyzeJobs();
            case 'cancel':
                return $this->cancelJobs();
            case 'check':
                return $this->checkJob();
            case 'progress':
                return $this->checkProgress();
            case 'snapshots':
                return $this->checkSnapshots();
            case 'cleanup':
                return $this->cleanupJobs();
            case 'complete':
                return $this->completeAnalysis();
            case 'download':
                return $this->downloadSnapshot();
            case 'monitor':
                return $this->monitorJobs();
            case 'cancel-job':
                return $this->cancelSpecificJob();
            case 'list-jobs':
                return $this->listJobs();
            case 'cancel-all-running':
                return $this->cancelAllRunningJobs();
            default:
                $this->error("Unknown action: {$action}");
                $this->info('Available actions: analyze, cancel, check, progress, snapshots, cleanup, complete, download, monitor, cancel-job, list-jobs, cancel-all-running');

                return 1;
        }
    }

    private function analyzeJobs()
    {
        $this->info('Analyzing BrightData jobs...');
        // Implementation would call the original AnalyzeBrightDataJobs logic
        $this->warn('Implementation needed: Move logic from AnalyzeBrightDataJobs');

        return 0;
    }

    private function cancelJobs()
    {
        $jobId = $this->option('job-id');
        $force = $this->option('force');

        if ($jobId) {
            $this->info("Cancelling job: {$jobId}");
        } else {
            $this->info('Cancelling all pending jobs...');
        }

        if (!$force && !$this->confirm('Are you sure?')) {
            $this->info('Cancelled.');

            return 0;
        }

        $this->warn('Implementation needed: Move logic from CancelBrightDataJobs');

        return 0;
    }

    private function checkJob()
    {
        $jobId = $this->option('job-id');

        if (!$jobId) {
            $this->error('--job-id is required for check action');

            return 1;
        }

        $this->info("Checking job: {$jobId}");
        $this->warn('Implementation needed: Move logic from CheckBrightDataJob');

        return 0;
    }

    private function checkProgress()
    {
        $this->info('Checking BrightData progress...');
        $this->warn('Implementation needed: Move logic from CheckBrightDataProgress');

        return 0;
    }

    private function checkSnapshots()
    {
        $this->info('Checking BrightData snapshots...');
        $this->warn('Implementation needed: Move logic from CheckBrightDataSnapshots');

        return 0;
    }

    private function cleanupJobs()
    {
        $status = $this->option('status');
        $limit = $this->option('limit');
        $force = $this->option('force');

        $this->info('Cleaning up stale BrightData jobs...');

        if (!$force && !$this->confirm('This will cancel old running BrightData jobs. Are you sure?')) {
            $this->info('Cancelled.');

            return 0;
        }

        try {
            $service = new \App\Services\Amazon\BrightDataScraperService();
            $result = $service->cancelStaleJobs();

            if (isset($result['error'])) {
                $this->error("Cleanup failed: {$result['error']}");

                return 1;
            }

            if (isset($result['message'])) {
                $this->info($result['message']);

                return 0;
            }

            $this->info('Cleanup completed successfully:');
            $this->line("- Stale threshold: {$result['stale_threshold_minutes']} minutes");
            $this->line("- Candidates found: {$result['candidates_found']}");
            $this->line('- Successfully canceled: '.count($result['canceled']));
            $this->line('- Failed to cancel: '.count($result['failed']));

            if (!empty($result['canceled'])) {
                $this->info("\nCanceled jobs:");
                foreach ($result['canceled'] as $job) {
                    $this->line("  - Job {$job['id']} (age: {$job['age_minutes']} minutes)");
                }
            }

            if (!empty($result['failed'])) {
                $this->warn("\nFailed to cancel:");
                foreach ($result['failed'] as $job) {
                    $this->line("  - Job {$job['id']} (age: {$job['age_minutes']} minutes)");
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Cleanup failed with exception: {$e->getMessage()}");

            return 1;
        }
    }

    private function completeAnalysis()
    {
        $this->info('Completing BrightData analysis...');
        $this->warn('Implementation needed: Move logic from CompleteBrightDataAnalysis');

        return 0;
    }

    private function downloadSnapshot()
    {
        $jobId = $this->option('job-id');

        if (!$jobId) {
            $this->error('--job-id is required for download action');

            return 1;
        }

        $this->info("Downloading snapshot for job: {$jobId}");
        $this->warn('Implementation needed: Move logic from DownloadBrightDataSnapshot');

        return 0;
    }

    private function monitorJobs()
    {
        $this->info('Monitoring BrightData jobs...');
        $this->warn('Implementation needed: Move logic from MonitorBrightDataJobs');

        return 0;
    }

    private function cancelSpecificJob()
    {
        $jobId = $this->option('job-id');

        if (!$jobId) {
            $this->error('--job-id is required for cancel-job action');
            $this->info('Usage: php artisan brightdata:manage cancel-job --job-id=s_mf6sonieuewv52bsy');

            return 1;
        }

        $force = $this->option('force');

        if (!$force && !$this->confirm("Cancel BrightData job {$jobId}?")) {
            $this->info('Cancelled.');

            return 0;
        }

        try {
            $service = new \App\Services\Amazon\BrightDataScraperService();
            $result = $service->cancelJob($jobId);

            if ($result) {
                $this->info("Successfully canceled job: {$jobId}");

                return 0;
            } else {
                $this->error("Failed to cancel job: {$jobId}");

                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error canceling job {$jobId}: {$e->getMessage()}");

            return 1;
        }
    }

    private function listJobs()
    {
        $status = $this->option('status') ?? 'running';
        $limit = (int) $this->option('limit');

        $this->info("Listing BrightData jobs with status: {$status}");

        try {
            $service = new \App\Services\Amazon\BrightDataScraperService();

            // Use reflection to access the private method
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('getJobsByStatus');
            $method->setAccessible(true);

            $jobs = $method->invoke($service, $status);

            if (empty($jobs)) {
                $this->info("No jobs found with status: {$status}");

                return 0;
            }

            $this->info('Found '.count($jobs).' jobs:');
            $this->line('');

            $count = 0;
            foreach ($jobs as $job) {
                if ($limit > 0 && $count >= $limit) {
                    break;
                }

                $jobId = $job['id'] ?? 'unknown';
                $createdAt = $job['created_at'] ?? 'unknown';
                $jobStatus = $job['status'] ?? 'unknown';

                // Calculate age if we have created_at
                $age = 'unknown';
                if ($createdAt !== 'unknown') {
                    try {
                        $created = \Carbon\Carbon::parse($createdAt);
                        $age = $created->diffForHumans();
                    } catch (\Exception $e) {
                        $age = 'parse error';
                    }
                }

                $this->line("Job ID: {$jobId}");
                $this->line("  Status: {$jobStatus}");
                $this->line("  Created: {$createdAt}");
                $this->line("  Age: {$age}");
                $this->line('');

                $count++;
            }

            if ($limit > 0 && count($jobs) > $limit) {
                $this->info("Showing first {$limit} of ".count($jobs).' jobs. Use --limit=0 to show all.');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error listing jobs: {$e->getMessage()}");

            return 1;
        }
    }

    private function cancelAllRunningJobs()
    {
        $force = $this->option('force');
        $limit = (int) $this->option('limit');

        $this->info('Fetching all running BrightData jobs...');

        try {
            $service = new \App\Services\Amazon\BrightDataScraperService();

            // Use reflection to access the private method
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('getJobsByStatus');
            $method->setAccessible(true);

            $jobs = $method->invoke($service, 'running');

            if (empty($jobs)) {
                $this->info('No running jobs found.');

                return 0;
            }

            $totalJobs = count($jobs);
            $jobsToCancel = $limit > 0 ? array_slice($jobs, 0, $limit) : $jobs;
            $cancelCount = count($jobsToCancel);

            $this->warn("Found {$totalJobs} running jobs.");
            if ($limit > 0 && $totalJobs > $limit) {
                $this->info("Will cancel first {$cancelCount} jobs due to --limit={$limit}");
            } else {
                $this->info("Will cancel all {$cancelCount} jobs.");
            }

            if (!$force && !$this->confirm('This will cancel running BrightData jobs. Are you sure?')) {
                $this->info('Cancelled.');

                return 0;
            }

            $this->info('Canceling jobs...');
            $canceled = 0;
            $failed = 0;

            foreach ($jobsToCancel as $index => $job) {
                $jobId = $job['id'] ?? 'unknown';

                if ($jobId === 'unknown') {
                    $this->warn('Skipping job with unknown ID');
                    $failed++;
                    continue;
                }

                try {
                    $result = $service->cancelJob($jobId);

                    if ($result) {
                        $canceled++;
                        $this->line("✓ Canceled: {$jobId} ({$canceled}/{$cancelCount})");
                    } else {
                        $failed++;
                        $this->error("✗ Failed: {$jobId}");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->error("✗ Error canceling {$jobId}: {$e->getMessage()}");
                }

                // Small delay to avoid overwhelming the API
                if ($index < count($jobsToCancel) - 1) {
                    usleep(500000); // 0.5 seconds
                }
            }

            $this->info('');
            $this->info('Cancellation complete:');
            $this->line("- Successfully canceled: {$canceled}");
            $this->line("- Failed to cancel: {$failed}");
            $this->line('- Total processed: '.($canceled + $failed));

            return $failed > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error("Error canceling jobs: {$e->getMessage()}");

            return 1;
        }
    }
}
