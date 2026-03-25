<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\ReviewAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAnalysisServiceNoReviewsRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_grade_u_product_is_auto_retried_on_new_user_request_within_limits(): void
    {
        $asin = 'B0NOREV001';
        $country = 'us';

        AsinData::factory()->create([
            'asin'            => $asin,
            'country'         => $country,
            'status'          => 'completed',
            'grade'           => 'U',
            'fake_percentage' => 0.0,
            'reviews'         => [],
            'openai_result'   => [
                'analysis_provider' => 'system',
                'total_cost'        => 0.0,
                'detailed_scores'   => [],
            ],
            'analysis_notes' => null,
        ]);

        $service = app(ReviewAnalysisService::class);
        $result = $service->checkProductExists("https://www.amazon.com/dp/{$asin}/");

        $this->assertTrue($result['needs_fetching'], 'Grade U product should be treated as needs_fetching when auto-retry is initiated');
        $this->assertTrue($result['needs_openai'], 'Grade U product should be treated as needs_openai when auto-retry is initiated');

        $fresh = AsinData::where('asin', $asin)->where('country', $country)->firstOrFail();
        $this->assertEquals('processing', $fresh->status);
        $this->assertNull($fresh->grade);
        $this->assertNull($fresh->reviews);

        $notes = json_decode((string) $fresh->analysis_notes, true);
        $this->assertIsArray($notes);
        $this->assertArrayHasKey('no_reviews_retry', $notes);
        $this->assertEquals(1, $notes['no_reviews_retry']['retry_count'] ?? null);
    }

    public function test_grade_u_product_is_not_retried_within_cooldown_window(): void
    {
        $asin = 'B0NOREV002';
        $country = 'us';

        AsinData::factory()->create([
            'asin'            => $asin,
            'country'         => $country,
            'status'          => 'completed',
            'grade'           => 'U',
            'fake_percentage' => 0.0,
            'reviews'         => [],
            'openai_result'   => [
                'analysis_provider' => 'system',
                'total_cost'        => 0.0,
                'detailed_scores'   => [],
            ],
            'analysis_notes' => json_encode([
                'no_reviews_retry' => [
                    'retry_count'   => 1,
                    'last_retry_at' => now()->subHours(3)->toIso8601String(),
                ],
            ]),
        ]);

        $service = app(ReviewAnalysisService::class);
        $result = $service->checkProductExists("https://www.amazon.com/dp/{$asin}/");

        $this->assertFalse($result['needs_fetching']);
        $this->assertFalse($result['needs_openai']);

        $fresh = AsinData::where('asin', $asin)->where('country', $country)->firstOrFail();
        $this->assertEquals('completed', $fresh->status);
        $this->assertEquals('U', $fresh->grade);
    }

    public function test_grade_u_product_is_not_retried_after_max_retries_reached(): void
    {
        $asin = 'B0NOREV003';
        $country = 'us';

        AsinData::factory()->create([
            'asin'            => $asin,
            'country'         => $country,
            'status'          => 'completed',
            'grade'           => 'U',
            'fake_percentage' => 0.0,
            'reviews'         => [],
            'openai_result'   => [
                'analysis_provider' => 'system',
                'total_cost'        => 0.0,
                'detailed_scores'   => [],
            ],
            'analysis_notes' => json_encode([
                'no_reviews_retry' => [
                    'retry_count'   => 2,
                    'last_retry_at' => now()->subHours(12)->toIso8601String(),
                ],
            ]),
        ]);

        $service = app(ReviewAnalysisService::class);
        $result = $service->checkProductExists("https://www.amazon.com/dp/{$asin}/");

        $this->assertFalse($result['needs_fetching']);
        $this->assertFalse($result['needs_openai']);

        $fresh = AsinData::where('asin', $asin)->where('country', $country)->firstOrFail();
        $this->assertEquals('completed', $fresh->status);
        $this->assertEquals('U', $fresh->grade);
    }
}
