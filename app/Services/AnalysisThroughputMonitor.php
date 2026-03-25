<?php

namespace App\Services;

use App\Models\AsinData;
use Illuminate\Support\Facades\Cache;

class AnalysisThroughputMonitor
{
    private const SERVICE_NAME = 'Analysis Throughput';
    private const ERROR_TYPE = 'PERFORMANCE_ALERT';

    public function checkAndAlert(): void
    {
        if (!config('analysis.throughput_monitoring.enabled', true)) {
            return;
        }

        $windowMinutes = (int) config('analysis.throughput_monitoring.window_minutes', 60);
        $minAnalyzed = (int) config('analysis.throughput_monitoring.min_analyzed_products', 5);
        $cooldownMinutes = (int) config('analysis.throughput_monitoring.alert_cooldown_minutes', 60);

        if ($windowMinutes <= 0 || $minAnalyzed <= 0) {
            return;
        }

        $since = now()->subMinutes($windowMinutes);

        $count = AsinData::query()
            ->completed()
            ->where('last_analyzed_at', '>=', $since)
            ->count();

        $throttleKey = $this->getThrottleKey($windowMinutes, $minAnalyzed);

        if ($count >= $minAnalyzed) {
            Cache::forget($throttleKey);
            app(AlertManager::class)->recordRecovery(self::SERVICE_NAME, self::ERROR_TYPE);

            return;
        }

        if (Cache::has($throttleKey)) {
            return;
        }

        Cache::put($throttleKey, true, now()->addMinutes($cooldownMinutes));

        $message = "Analysis throughput is low: {$count} analyzed products in the last {$windowMinutes} minutes (minimum {$minAnalyzed}).";

        app(AlertManager::class)->recordFailure(
            self::SERVICE_NAME,
            self::ERROR_TYPE,
            $message,
            [
                'window_minutes' => $windowMinutes,
                'min_analyzed'   => $minAnalyzed,
                'actual_analyzed'=> $count,
            ]
        );
    }

    private function getThrottleKey(int $windowMinutes, int $minAnalyzed): string
    {
        return "analysis_throughput_alert:{$windowMinutes}:{$minAnalyzed}";
    }
}

