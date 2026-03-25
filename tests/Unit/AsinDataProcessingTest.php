<?php

namespace Tests\Unit;

use App\Models\AnalysisSession;
use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsinDataProcessingTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_processing_returns_true_for_fetched_status(): void
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'fetched',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $this->assertTrue($asinData->isProcessing());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_processing_returns_true_for_pending_status(): void
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'pending',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $this->assertTrue($asinData->isProcessing());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_processing_returns_true_for_pending_analysis_status(): void
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'pending_analysis',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $this->assertTrue($asinData->isProcessing());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_processing_returns_true_for_processing_status(): void
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $this->assertTrue($asinData->isProcessing());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_processing_returns_false_for_completed_status(): void
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'completed',
            'fake_percentage' => 25.5,
            'grade'           => 'B',
        ]);

        $this->assertFalse($asinData->isProcessing());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_processing_returns_false_for_failed_status(): void
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'failed',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $this->assertFalse($asinData->isProcessing());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_estimated_processing_time_returns_base_time_for_small_review_count(): void
    {
        $asinData = AsinData::factory()->create([
            'status'  => 'processing',
            'reviews' => array_fill(0, 10, ['content' => 'test']),
        ]);

        $estimatedMinutes = $asinData->getEstimatedProcessingTimeMinutes();

        // 10 reviews: 10/50 = 0.2, ceil(0.2) = 1, so 2 + 1 = 3 minutes
        $this->assertEquals(3, $estimatedMinutes);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_estimated_processing_time_adds_time_for_more_reviews(): void
    {
        // 150 reviews: 150/50 = 3, so 2 + 3 = 5 minutes
        $asinData = AsinData::factory()->create([
            'status'  => 'processing',
            'reviews' => array_fill(0, 150, ['content' => 'test']),
        ]);

        $estimatedMinutes = $asinData->getEstimatedProcessingTimeMinutes();

        $this->assertEquals(5, $estimatedMinutes);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_estimated_processing_time_caps_at_maximum(): void
    {
        // 1000 reviews would calculate to 2 + 20 = 22, but should cap at 10
        $asinData = AsinData::factory()->create([
            'status'  => 'processing',
            'reviews' => array_fill(0, 1000, ['content' => 'test']),
        ]);

        $estimatedMinutes = $asinData->getEstimatedProcessingTimeMinutes();

        $this->assertEquals(10, $estimatedMinutes); // Capped at 10
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_returns_false_when_no_session_exists(): void
    {
        $processingInfo = AsinData::checkProcessingSession('B0NOTEXIST');

        $this->assertFalse($processingInfo['is_processing']);
        $this->assertEquals(0, $processingInfo['estimated_minutes']);
        $this->assertNull($processingInfo['session']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_returns_true_when_pending_session_exists(): void
    {
        AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440000',
            'user_session'        => 'test_session_pending',
            'asin'                => 'B0TESTPEND',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTPEND',
            'status'              => 'pending',
            'current_step'        => 0,
            'progress_percentage' => 0,
            'total_steps'         => 5,
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0TESTPEND');

        $this->assertTrue($processingInfo['is_processing']);
        $this->assertGreaterThan(0, $processingInfo['estimated_minutes']);
        $this->assertNotNull($processingInfo['session']);
        $this->assertEquals('B0TESTPEND', $processingInfo['session']->asin);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_returns_true_when_processing_session_exists(): void
    {
        AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440001',
            'user_session'        => 'test_session_processing',
            'asin'                => 'B0TESTPROC',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTPROC',
            'status'              => 'processing',
            'current_step'        => 2,
            'progress_percentage' => 40,
            'total_steps'         => 5,
            'started_at'          => now()->subMinute(),
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0TESTPROC');

        $this->assertTrue($processingInfo['is_processing']);
        $this->assertGreaterThan(0, $processingInfo['estimated_minutes']);
        $this->assertNotNull($processingInfo['session']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_calculates_estimate_based_on_elapsed_time(): void
    {
        // Session started 2 minutes ago, so estimate should be 3 - 2 = 1 minute
        AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440002',
            'user_session'        => 'test_session_elapsed',
            'asin'                => 'B0TESTELAP',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTELAP',
            'status'              => 'processing',
            'current_step'        => 3,
            'progress_percentage' => 60,
            'total_steps'         => 5,
            'started_at'          => now()->subMinutes(2),
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0TESTELAP');

        $this->assertTrue($processingInfo['is_processing']);
        $this->assertEquals(1, $processingInfo['estimated_minutes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_gives_conservative_estimate_for_long_running(): void
    {
        // Session started 5 minutes ago (more than expected 3 minutes)
        AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440003',
            'user_session'        => 'test_session_long',
            'asin'                => 'B0TESTLONG',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTLONG',
            'status'              => 'processing',
            'current_step'        => 4,
            'progress_percentage' => 80,
            'total_steps'         => 5,
            'started_at'          => now()->subMinutes(5),
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0TESTLONG');

        $this->assertTrue($processingInfo['is_processing']);
        $this->assertEquals(2, $processingInfo['estimated_minutes']); // Conservative estimate
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_returns_most_recent_session(): void
    {
        // Create older session
        AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440004',
            'user_session'        => 'test_session_mult_old',
            'asin'                => 'B0TESTMULT',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTMULT',
            'status'              => 'processing',
            'current_step'        => 1,
            'progress_percentage' => 20,
            'total_steps'         => 5,
            'created_at'          => now()->subMinutes(10),
        ]);

        // Create newer session
        $newerSession = AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440005',
            'user_session'        => 'test_session_mult_new',
            'asin'                => 'B0TESTMULT',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTMULT',
            'status'              => 'processing',
            'current_step'        => 3,
            'progress_percentage' => 60,
            'total_steps'         => 5,
            'created_at'          => now()->subMinutes(2),
            'started_at'          => now()->subMinutes(2),
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0TESTMULT');

        $this->assertTrue($processingInfo['is_processing']);
        $this->assertEquals($newerSession->id, $processingInfo['session']->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_ignores_completed_sessions(): void
    {
        AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440006',
            'user_session'        => 'test_session_completed',
            'asin'                => 'B0TESTCOMP',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTCOMP',
            'status'              => 'completed',
            'current_step'        => 5,
            'progress_percentage' => 100,
            'total_steps'         => 5,
            'started_at'          => now()->subMinutes(5),
            'completed_at'        => now(),
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0TESTCOMP');

        $this->assertFalse($processingInfo['is_processing']);
        $this->assertNull($processingInfo['session']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function check_processing_session_ignores_failed_sessions(): void
    {
        AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440007',
            'user_session'        => 'test_session_failed',
            'asin'                => 'B0TESTFAIL',
            'product_url'         => 'https://www.amazon.com/dp/B0TESTFAIL',
            'status'              => 'failed',
            'current_step'        => 2,
            'progress_percentage' => 40,
            'total_steps'         => 5,
            'started_at'          => now()->subMinutes(3),
            'completed_at'        => now(),
            'error_message'       => 'Test error',
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0TESTFAIL');

        $this->assertFalse($processingInfo['is_processing']);
        $this->assertNull($processingInfo['session']);
    }
}
