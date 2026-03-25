<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DataProcessCommand extends Command
{
    protected $signature = 'data:process 
                            {operation : The data processing operation to perform}
                            {--limit= : Maximum number of items to process}
                            {--dry-run : Show what would be done without executing}
                            {--force : Skip confirmation prompts}
                            {--chunk-size= : Process in chunks of this size}
                            {--timeout= : Timeout in seconds}
                            {--detailed : Show detailed output}';

    protected $description = 'Consolidated data processing command';

    private array $availableOperations = [
        'backfill-counts' => [
            'command'     => 'backfill:total-review-counts',
            'description' => 'Backfill total review counts for existing products',
            'options'     => ['limit', 'dry-run', 'force', 'chunk-size'],
        ],
        'cleanup-zero-reviews' => [
            'command'     => 'cleanup:zero-review-products',
            'description' => 'Clean up products with zero reviews',
            'options'     => ['limit', 'dry-run', 'force'],
        ],
        'fix-discrepancies' => [
            'command'     => 'fix:review-count-discrepancies',
            'description' => 'Fix review count discrepancies',
            'options'     => ['limit', 'dry-run', 'force'],
        ],
        'force-rescrape' => [
            'command'     => 'force:rescrape-deduplicated',
            'description' => 'Force re-scraping of deduplicated products',
            'options'     => ['limit', 'dry-run', 'force'],
        ],
        'audit-duplication' => [
            'command'     => 'audit:review-duplication',
            'description' => 'Audit review duplication issues',
            'options'     => ['limit', 'detailed'],
        ],
        'cleanup-sessions' => [
            'command'     => 'cleanup:analysis-sessions',
            'description' => 'Clean up old analysis sessions',
            'options'     => ['force'],
        ],
        'generate-sitemap' => [
            'command'     => 'generate:sitemap',
            'description' => 'Generate XML sitemap for SEO',
            'options'     => ['force'],
        ],
    ];

    public function handle(): int
    {
        $operation = $this->argument('operation');

        // Handle help/list request
        if ($operation === 'list' || $operation === 'help') {
            return $this->showAvailableOperations();
        }

        // Validate operation
        if (!isset($this->availableOperations[$operation])) {
            $this->error("Unknown operation: {$operation}");
            $this->info("Run 'php artisan data:process list' to see available operations");

            return 1;
        }

        $operationConfig = $this->availableOperations[$operation];

        // Build command arguments from available options
        $commandArgs = [];
        foreach ($operationConfig['options'] as $option) {
            $value = $this->option($option);
            if ($value !== null) {
                if (is_bool($value) && $value) {
                    $commandArgs["--{$option}"] = true;
                } elseif (!is_bool($value)) {
                    $commandArgs["--{$option}"] = $value;
                }
            }
        }

        // Execute the underlying command
        $this->info("Executing: {$operationConfig['description']}");
        if (!empty($commandArgs)) {
            $this->info('Options: '.json_encode($commandArgs, JSON_PRETTY_PRINT));
        }
        $this->newLine();

        try {
            $exitCode = Artisan::call($operationConfig['command'], $commandArgs);
            $this->line(Artisan::output());

            if ($exitCode === 0) {
                $this->info('Data processing operation completed successfully');
            } else {
                $this->error("Data processing operation failed with exit code: {$exitCode}");
            }

            return $exitCode;
        } catch (\Exception $e) {
            $this->error('Failed to execute data processing operation: '.$e->getMessage());

            return 1;
        }
    }

    private function showAvailableOperations(): int
    {
        $this->info('Available data processing operations:');
        $this->newLine();

        foreach ($this->availableOperations as $operation => $config) {
            $this->line("<info>{$operation}</info> - {$config['description']}");
            if (!empty($config['options'])) {
                $options = implode(', ', array_map(fn ($opt) => "--{$opt}", $config['options']));
                $this->line("  Options: {$options}");
            }
            $this->newLine();
        }

        $this->info('Usage:');
        $this->line('  php artisan data:process <operation> [options]');
        $this->newLine();

        $this->info('Examples:');
        $this->line('  php artisan data:process backfill-counts --limit=1000 --chunk-size=100');
        $this->line('  php artisan data:process cleanup-zero-reviews --dry-run');
        $this->line('  php artisan data:process fix-discrepancies --force');
        $this->line('  php artisan data:process generate-sitemap');

        return 0;
    }
}
