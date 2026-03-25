<?php

namespace Tests\Feature;

use App\Services\ReviewAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalysisStartRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->mock(ReviewAnalysisService::class, function ($mock) {
            $mock->shouldReceive('extractAsinFromUrl')->andReturn('B123456789');
        });
    }

    #[Test]
    public function it_rate_limits_analysis_start_to_30_requests_per_minute_per_ip(): void
    {
        config(['analysis.async_enabled' => true]);

        for ($i = 1; $i <= 30; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson('/api/analysis/start', [
                    'productUrl' => "https://amazon.com/dp/B123456789?i={$i}",
                ]);

            $response->assertStatus(200);
        }

        $rateLimited = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/analysis/start', [
                'productUrl' => 'https://amazon.com/dp/B123456789?i=31',
            ]);

        $rateLimited->assertStatus(429);
    }
}

