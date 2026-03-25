<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\AnalysisStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalysisStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_status_change_for_non_completed_analysis(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0TEST12345',
            'country'         => 'us',
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $this->assertTrue(AnalysisStatusService::canChangeStatus($asinData, 'pending_analysis'));
        $this->assertTrue(AnalysisStatusService::canChangeStatus($asinData, 'completed'));
    }

    #[Test]
    public function it_blocks_status_change_for_completed_analysis(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0TEST12345',
            'country'         => 'us',
            'status'          => 'completed',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
            'reviews'         => json_encode([['id' => 1, 'text' => 'Test review']]),
        ]);

        $this->assertFalse(AnalysisStatusService::canChangeStatus($asinData, 'pending_analysis'));
        $this->assertFalse(AnalysisStatusService::canChangeStatus($asinData, 'processing'));
    }

    #[Test]
    public function update_status_respects_completion_lock(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0TEST12345',
            'country'         => 'us',
            'status'          => 'completed',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
            'reviews'         => json_encode([['id' => 1, 'text' => 'Test review']]),
        ]);

        // Attempt to change status should fail
        $result = AnalysisStatusService::updateStatus($asinData, 'pending_analysis');

        $this->assertFalse($result);
        $this->assertEquals('completed', $asinData->fresh()->status);
    }

    #[Test]
    public function update_status_works_for_non_completed(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0TEST12345',
            'country'         => 'us',
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $result = AnalysisStatusService::updateStatus($asinData, 'pending_analysis');

        $this->assertTrue($result);
        $this->assertEquals('pending_analysis', $asinData->fresh()->status);
    }

    #[Test]
    public function mark_completed_requires_metrics(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0TEST12345',
            'country'         => 'us',
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        // Should fail without required metrics
        $result = AnalysisStatusService::markCompleted($asinData);
        $this->assertFalse($result);
        $this->assertNotEquals('completed', $asinData->fresh()->status);
    }

    #[Test]
    public function mark_completed_succeeds_with_metrics(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0TEST12345',
            'country'         => 'us',
            'status'          => 'processing',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
        ]);

        $result = AnalysisStatusService::markCompleted($asinData);

        $this->assertTrue($result);
        $this->assertEquals('completed', $asinData->fresh()->status);
        $this->assertNotNull($asinData->fresh()->last_analyzed_at);
    }

    #[Test]
    public function mark_failed_does_not_overwrite_completed(): void
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0TEST12345',
            'country'         => 'us',
            'status'          => 'completed',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
            'reviews'         => json_encode([['id' => 1, 'text' => 'Test review']]),
        ]);

        $result = AnalysisStatusService::markFailed($asinData, 'Test failure');

        $this->assertFalse($result);
        $this->assertEquals('completed', $asinData->fresh()->status);
    }

    #[Test]
    public function is_ready_for_display_checks_analyzed_state(): void
    {
        // Not ready - missing data
        $incomplete = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
        ]);
        $this->assertFalse(AnalysisStatusService::isReadyForDisplay($incomplete));

        // Ready - complete data
        $complete = AsinData::factory()->create([
            'status'          => 'completed',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
            'reviews'         => json_encode([['id' => 1, 'text' => 'Test review']]),
        ]);
        $this->assertTrue(AnalysisStatusService::isReadyForDisplay($complete));
    }

    #[Test]
    public function get_display_status_normalizes_internal_states(): void
    {
        // Completed
        $completed = AsinData::factory()->create([
            'status'          => 'completed',
            'fake_percentage' => 25.0,
            'grade'           => 'B',
            'reviews'         => json_encode([['id' => 1, 'text' => 'Test review']]),
        ]);
        $this->assertEquals('completed', AnalysisStatusService::getDisplayStatus($completed));

        // Failed
        $failed = AsinData::factory()->create([
            'status'          => 'failed',
            'fake_percentage' => null,
            'grade'           => null,
        ]);
        $this->assertEquals('failed', AnalysisStatusService::getDisplayStatus($failed));

        // Processing (various internal states)
        $processing = AsinData::factory()->create([
            'status'          => 'pending_analysis',
            'fake_percentage' => null,
            'grade'           => null,
        ]);
        $this->assertEquals('processing', AnalysisStatusService::getDisplayStatus($processing));
    }
}
