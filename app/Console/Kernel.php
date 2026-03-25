<?php

namespace App\Console;

use App\Services\AnalysisThroughputMonitor;
use App\Services\SiteStatisticsService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Warm site statistics cache every hour to ensure page loads never block on DB queries
        $schedule->call(function (): void {
            app(SiteStatisticsService::class)->warmProductsAnalyzedCount();
        })
            ->hourly()
            ->name('site-stats:warm')
            ->withoutOverlapping();

        // Warm sitemap cache every 50 minutes to prevent expiry
        $schedule->command('sitemap:warm')
                 ->cron('*/50 * * * *')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Generate sitemap daily to ensure Google has fresh product links
        $schedule->command('sitemap:generate --clear-cache')
                 ->daily()
                 ->at('02:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Clean up old analysis sessions weekly
        $schedule->command('analysis:cleanup --hours=168') // 7 days
                 ->weekly()
                 ->sundays()
                 ->at('03:00');

        // Retry Grade U products (no reviews found) every hour - limit 10 to keep volume minimal
        $schedule->command('products:retry-no-reviews --limit=10 --age=24')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Clean up stale BrightData jobs every hour
        $schedule->command('brightdata:manage cleanup --force')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Monitor analysis throughput to detect stuck workers, traffic spikes, or upstream degradation
        $intervalMinutes = (int) config('analysis.throughput_monitoring.schedule_interval_minutes', 15);
        if ($intervalMinutes <= 0) {
            $intervalMinutes = 15;
        }

        $schedule->call(function (): void {
            app(AnalysisThroughputMonitor::class)->checkAndAlert();
        })
            ->cron("*/{$intervalMinutes} * * * *")
            ->name('analysis:throughput-monitor')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

