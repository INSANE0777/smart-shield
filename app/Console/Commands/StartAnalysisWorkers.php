<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class StartAnalysisWorkers extends Command
{
    protected $signature = 'analysis:workers 
                           {action=start : Action to perform (start|stop|restart|status)}
                           {--workers=2 : Number of worker processes}
                           {--timeout=300 : Worker timeout in seconds}
                           {--memory=256 : Memory limit in MB}
                           {--daemon : Run workers as daemon processes}';

    protected $description = 'Manage analysis queue workers for async processing';

    public function handle(): int
    {
        $action = $this->argument('action');
        $workers = (int) $this->option('workers');
        $timeout = (int) $this->option('timeout');
        $memory = (int) $this->option('memory');
        $daemon = $this->option('daemon');

        switch ($action) {
            case 'start':
                return $this->startWorkers($workers, $timeout, $memory, $daemon);
            case 'stop':
                return $this->stopWorkers();
            case 'restart':
                $this->stopWorkers();
                sleep(2);

                return $this->startWorkers($workers, $timeout, $memory, $daemon);
            case 'status':
                return $this->showStatus();
            default:
                $this->error("Unknown action: {$action}");
                $this->info('Available actions: start, stop, restart, status');

                return 1;
        }
    }

    private function startWorkers(int $workers, int $timeout, int $memory, bool $daemon): int
    {
        $this->info('🚀 Starting Analysis Queue Workers');
        $this->info('=================================');

        // Check if queue connection is configured
        if (config('queue.default') === 'sync') {
            $this->warn('⚠️  Queue connection is set to "sync" - async analysis will not work');
            $this->info('💡 Set QUEUE_CONNECTION=database in your .env file for async processing');
        }

        for ($i = 1; $i <= $workers; $i++) {
            $command = [
                'php',
                'artisan',
                'queue:work',
                'analysis',
                '--queue=analysis',
                "--timeout={$timeout}",
                "--memory={$memory}",
                '--tries=3',
                '--delay=10',
                '--sleep=3',
                '--max-jobs=100',
                '--max-time=3600', // 1 hour max runtime
            ];

            if ($daemon) {
                $command[] = '--daemon';
            }

            $this->info("🔄 Starting worker {$i}/{$workers}...");

            if ($daemon) {
                // Start as background process
                $process = Process::start($command);
                $this->info("✅ Worker {$i} started with PID: ".$process->id());
            } else {
                // Show command for manual execution
                $commandStr = implode(' ', $command);
                $this->info('🔧 Run this command in a separate terminal:');
                $this->line("   {$commandStr}");
            }
        }

        if (!$daemon) {
            $this->newLine();
            $this->info('💡 Tips:');
            $this->info('• Run each command in a separate terminal window');
            $this->info('• Use --daemon flag to run workers in background');
            $this->info('• Monitor workers with: php artisan analysis:workers status');
            $this->info('• For production, use a process manager like Supervisor');
        }

        $this->newLine();
        $this->info('📊 Configuration:');
        $this->info("• Workers: {$workers}");
        $this->info("• Timeout: {$timeout}s");
        $this->info("• Memory: {$memory}MB");
        $this->info('• Queue: analysis');
        $this->info('• Mode: '.($daemon ? 'daemon' : 'manual'));

        return 0;
    }

    private function stopWorkers(): int
    {
        $this->info('🛑 Stopping Analysis Queue Workers');
        $this->info('==================================');

        // Find and stop queue worker processes
        $processes = Process::run('pgrep -f "queue:work analysis"')->output();

        if (empty(trim($processes))) {
            $this->info('ℹ️  No analysis workers found running');

            return 0;
        }

        $pids = array_filter(explode("\n", trim($processes)));

        foreach ($pids as $pid) {
            $this->info("🔄 Stopping worker with PID: {$pid}");
            Process::run("kill -TERM {$pid}");
        }

        $this->info('✅ Worker stop signals sent');
        $this->info('💡 Workers will finish current jobs before stopping');

        return 0;
    }

    private function showStatus(): int
    {
        $this->info('📊 Analysis Queue Workers Status');
        $this->info('===============================');

        // Check for running workers
        $processes = Process::run('pgrep -f "queue:work analysis"')->output();
        $pids = array_filter(explode("\n", trim($processes)));

        if (empty($pids)) {
            $this->info('❌ No analysis workers running');
        } else {
            $this->info('✅ Running workers:');
            foreach ($pids as $pid) {
                $processInfo = Process::run("ps -p {$pid} -o pid,cmd --no-headers")->output();
                $this->info("  • PID {$pid}: ".trim($processInfo));
            }
        }

        $this->newLine();

        // Check queue configuration
        $this->info('⚙️  Queue Configuration:');
        $this->info('• Default connection: '.config('queue.default'));
        $this->info('• Analysis connection: '.config('queue.connections.analysis.driver', 'not configured'));
        $this->info('• Async enabled: '.(config('analysis.async_enabled') ? 'yes' : 'no'));

        $this->newLine();

        // Check for pending jobs
        try {
            $pendingJobs = \DB::table('jobs')
                ->where('queue', 'analysis')
                ->where('available_at', '<=', now()->timestamp)
                ->count();

            $this->info('📋 Queue Status:');
            $this->info("• Pending jobs: {$pendingJobs}");
        } catch (\Exception $e) {
            $this->warn('⚠️  Could not check queue status: '.$e->getMessage());
        }

        return 0;
    }
}

