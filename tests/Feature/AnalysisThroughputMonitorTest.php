<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\AlertManager;
use App\Services\AnalysisThroughputMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalysisThroughputMonitorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_an_alert_when_throughput_is_below_threshold(): void
    {
        Carbon::setTestNow(now());

        config([
            'analysis.throughput_monitoring.enabled'                => true,
            'analysis.throughput_monitoring.window_minutes'         => 60,
            'analysis.throughput_monitoring.min_analyzed_products'  => 5,
            'analysis.throughput_monitoring.alert_cooldown_minutes' => 60,
        ]);

        AsinData::factory()->count(2)->create([
            'last_analyzed_at' => now()->subMinutes(10),
        ]);

        $captured = [
            'service'   => null,
            'errorType' => null,
            'message'   => null,
            'context'   => null,
        ];

        $this->mock(AlertManager::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('recordFailure')
                ->once()
                ->with('Analysis Throughput', 'PERFORMANCE_ALERT', Mockery::type('string'), Mockery::type('array'))
                ->andReturnUsing(function (string $service, string $errorType, string $message, array $context) use (&$captured): void {
                    $captured['service'] = $service;
                    $captured['errorType'] = $errorType;
                    $captured['message'] = $message;
                    $captured['context'] = $context;
                });

            $mock->shouldReceive('recordRecovery')->never();
        });

        app(AnalysisThroughputMonitor::class)->checkAndAlert();

        $this->assertTrue(Cache::has('analysis_throughput_alert:60:5'));
        $this->assertNotNull($captured['message']);
        $this->assertStringContainsString('Analysis throughput is low', (string) $captured['message']);
        $this->assertSame(60, $captured['context']['window_minutes'] ?? null);
        $this->assertSame(5, $captured['context']['min_analyzed'] ?? null);
        $this->assertSame(2, $captured['context']['actual_analyzed'] ?? null);
    }

    #[Test]
    public function it_does_not_spam_alerts_within_the_cooldown_window(): void
    {
        Carbon::setTestNow(now());

        config([
            'analysis.throughput_monitoring.enabled'                => true,
            'analysis.throughput_monitoring.window_minutes'         => 60,
            'analysis.throughput_monitoring.min_analyzed_products'  => 5,
            'analysis.throughput_monitoring.alert_cooldown_minutes' => 60,
        ]);

        AsinData::factory()->count(1)->create([
            'last_analyzed_at' => now()->subMinutes(5),
        ]);

        $this->mock(AlertManager::class, function ($mock) {
            $mock->shouldReceive('recordFailure')->once();
            $mock->shouldReceive('recordRecovery')->never();
        });

        $monitor = app(AnalysisThroughputMonitor::class);
        $monitor->checkAndAlert();
        $monitor->checkAndAlert();
    }

    #[Test]
    public function it_records_recovery_and_clears_throttle_when_throughput_is_healthy(): void
    {
        Carbon::setTestNow(now());

        config([
            'analysis.throughput_monitoring.enabled'                => true,
            'analysis.throughput_monitoring.window_minutes'         => 60,
            'analysis.throughput_monitoring.min_analyzed_products'  => 5,
            'analysis.throughput_monitoring.alert_cooldown_minutes' => 60,
        ]);

        Cache::put('analysis_throughput_alert:60:5', true, now()->addMinutes(60));

        AsinData::factory()->count(5)->create([
            'last_analyzed_at' => now()->subMinutes(15),
        ]);

        $this->mock(AlertManager::class, function ($mock) {
            $mock->shouldReceive('recordRecovery')
                ->once()
                ->with('Analysis Throughput', 'PERFORMANCE_ALERT');
            $mock->shouldReceive('recordFailure')->never();
        });

        app(AnalysisThroughputMonitor::class)->checkAndAlert();

        $this->assertFalse(Cache::has('analysis_throughput_alert:60:5'));
    }
}

