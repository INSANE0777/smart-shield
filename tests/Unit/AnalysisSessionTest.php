<?php

namespace Tests\Unit;

use App\Models\AnalysisSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_session_creation()
    {
        $session = AnalysisSession::create([
            'user_session' => 'test-session-123',
            'asin'         => 'B123456789',
            'product_url'  => 'https://amazon.com/dp/B123456789',
            'status'       => 'pending',
            'total_steps'  => 7,
        ]);

        $this->assertNotNull($session->id);
        $this->assertEquals('pending', $session->status);
        $this->assertEquals(7, $session->total_steps);
        $this->assertEquals(0, $session->current_step);
        $this->assertEquals(0.0, $session->progress_percentage);
    }

    public function test_update_progress_updates_database()
    {
        $session = AnalysisSession::create([
            'user_session' => 'test-session-123',
            'asin'         => 'B123456789',
            'product_url'  => 'https://amazon.com/dp/B123456789',
            'status'       => 'pending',
        ]);

        // Update progress
        $session->updateProgress(3, 45.5, 'Testing progress update');

        // Verify model instance is updated
        $this->assertEquals(3, $session->current_step);
        $this->assertEquals(45.5, $session->progress_percentage);
        $this->assertEquals('Testing progress update', $session->current_message);

        // Verify database is updated by fetching fresh instance
        $fresh = AnalysisSession::find($session->id);
        $this->assertEquals(3, $fresh->current_step);
        $this->assertEquals(45.5, $fresh->progress_percentage);
        $this->assertEquals('Testing progress update', $fresh->current_message);
    }

    public function test_update_progress_handles_concurrent_updates()
    {
        $session = AnalysisSession::create([
            'user_session' => 'test-session-123',
            'asin'         => 'B123456789',
            'product_url'  => 'https://amazon.com/dp/B123456789',
            'status'       => 'pending',
        ]);

        // Simulate concurrent update directly to database
        AnalysisSession::where('id', $session->id)->update([
            'current_step'        => 1,
            'progress_percentage' => 10.0,
            'current_message'     => 'Concurrent update',
        ]);

        // Now call updateProgress - should refresh and work correctly
        $session->updateProgress(2, 25.0, 'Second update');

        // Verify final state
        $fresh = AnalysisSession::find($session->id);
        $this->assertEquals(2, $fresh->current_step);
        $this->assertEquals(25.0, $fresh->progress_percentage);
        $this->assertEquals('Second update', $fresh->current_message);
    }

    public function test_mark_as_processing()
    {
        $session = AnalysisSession::create([
            'user_session' => 'test-session-123',
            'asin'         => 'B123456789',
            'product_url'  => 'https://amazon.com/dp/B123456789',
            'status'       => 'pending',
        ]);

        $session->markAsProcessing();

        $this->assertEquals('processing', $session->status);
        $this->assertNotNull($session->started_at);
        $this->assertTrue($session->isProcessing());
    }

    public function test_mark_as_completed()
    {
        $session = AnalysisSession::create([
            'user_session' => 'test-session-123',
            'asin'         => 'B123456789',
            'product_url'  => 'https://amazon.com/dp/B123456789',
            'status'       => 'processing',
        ]);

        $result = ['success' => true, 'analysis_result' => ['fake_percentage' => 25.0]];
        $session->markAsCompleted($result);

        $this->assertEquals('completed', $session->status);
        $this->assertEquals(100.0, $session->progress_percentage);
        $this->assertEquals('Analysis complete!', $session->current_message);
        $this->assertEquals($result, $session->result);
        $this->assertNotNull($session->completed_at);
        $this->assertTrue($session->isCompleted());
    }

    public function test_mark_as_failed()
    {
        $session = AnalysisSession::create([
            'user_session' => 'test-session-123',
            'asin'         => 'B123456789',
            'product_url'  => 'https://amazon.com/dp/B123456789',
            'status'       => 'processing',
        ]);

        $session->markAsFailed('Test error message');

        $this->assertEquals('failed', $session->status);
        $this->assertEquals('Test error message', $session->error_message);
        $this->assertNotNull($session->completed_at);
        $this->assertTrue($session->isFailed());
    }

    public function test_status_check_methods()
    {
        $session = AnalysisSession::create([
            'user_session' => 'test-session-123',
            'asin'         => 'B123456789',
            'product_url'  => 'https://amazon.com/dp/B123456789',
            'status'       => 'pending',
        ]);

        // Test pending status
        $this->assertTrue($session->isProcessing()); // pending counts as processing
        $this->assertFalse($session->isCompleted());
        $this->assertFalse($session->isFailed());

        // Test processing status
        $session->update(['status' => 'processing']);
        $this->assertTrue($session->isProcessing());
        $this->assertFalse($session->isCompleted());
        $this->assertFalse($session->isFailed());

        // Test completed status
        $session->update(['status' => 'completed']);
        $this->assertFalse($session->isProcessing());
        $this->assertTrue($session->isCompleted());
        $this->assertFalse($session->isFailed());

        // Test failed status
        $session->update(['status' => 'failed']);
        $this->assertFalse($session->isProcessing());
        $this->assertFalse($session->isCompleted());
        $this->assertTrue($session->isFailed());
    }
}
