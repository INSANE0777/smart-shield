<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalysisThroughputScheduleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function schedule_includes_analysis_throughput_monitor(): void
    {
        config([
            'analysis.throughput_monitoring.enabled'                   => true,
            'analysis.throughput_monitoring.schedule_interval_minutes' => 15,
        ]);

        $this->artisan('schedule:list')
            ->expectsOutputToContain('analysis:throughput-monitor')
            ->assertExitCode(0);
    }
}
