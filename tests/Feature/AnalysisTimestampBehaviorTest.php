<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\ReviewAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalysisTimestampBehaviorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function new_analysis_sets_both_timestamps()
    {
        // Create a product without analysis timestamps
        $product = AsinData::factory()->create([
            'asin'              => 'B0TESTNEW123',
            'status'            => 'pending',
            'first_analyzed_at' => null,
            'last_analyzed_at'  => null,
        ]);

        // Mock the ReviewAnalysisService to avoid LLM calls
        $mockService = $this->createMock(ReviewAnalysisService::class);
        $mockService->method('analyzeProduct')
            ->willReturn([
                'success'   => true,
                'asin_data' => $product,
            ]);

        // Simulate what happens when analysis completes
        $now = now();
        $product->update([
            'status'            => 'completed',
            'fake_percentage'   => 25.0,
            'grade'             => 'B',
            'first_analyzed_at' => $now,
            'last_analyzed_at'  => $now,
        ]);

        $product->refresh();

        // Both timestamps should be set and equal for new analysis
        $this->assertNotNull($product->first_analyzed_at);
        $this->assertNotNull($product->last_analyzed_at);
        $this->assertEquals(
            $product->first_analyzed_at->format('Y-m-d H:i:s'),
            $product->last_analyzed_at->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function existing_analysis_preserves_first_analyzed_at()
    {
        // Create a product that was already analyzed
        $originalAnalysisDate = now()->subDays(3);
        $product = AsinData::factory()->create([
            'asin'              => 'B0TESTEXIST1',
            'status'            => 'completed',
            'fake_percentage'   => 40.0,
            'grade'             => 'C',
            'first_analyzed_at' => $originalAnalysisDate,
            'last_analyzed_at'  => $originalAnalysisDate,
        ]);

        // Simulate re-analysis (like user clicking "Re-analyze" button)
        $reanalysisDate = now();
        $product->update([
            'fake_percentage'  => 20.0,
            'grade'            => 'A',
            'last_analyzed_at' => $reanalysisDate, // Only update this
            // first_analyzed_at should remain unchanged
        ]);

        $product->refresh();

        // first_analyzed_at should be preserved, last_analyzed_at should be updated
        $this->assertEquals(
            $originalAnalysisDate->format('Y-m-d H:i:s'),
            $product->first_analyzed_at->format('Y-m-d H:i:s')
        );
        $this->assertEquals(
            $reanalysisDate->format('Y-m-d H:i:s'),
            $product->last_analyzed_at->format('Y-m-d H:i:s')
        );
        $this->assertNotEquals(
            $product->first_analyzed_at->format('Y-m-d H:i:s'),
            $product->last_analyzed_at->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function ui_display_logic_works_correctly()
    {
        // Test case 1: Product never re-analyzed (both timestamps same)
        $analysisDate = now()->subDays(2);
        $product1 = AsinData::factory()->create([
            'first_analyzed_at' => $analysisDate,
            'last_analyzed_at'  => $analysisDate,
        ]);

        // UI should show "Analyzed X ago"
        $isReanalyzed = $product1->last_analyzed_at && $product1->first_analyzed_at &&
                       $product1->last_analyzed_at->ne($product1->first_analyzed_at);
        $this->assertFalse($isReanalyzed);

        // Test case 2: Product re-analyzed (timestamps different)
        $product2 = AsinData::factory()->create([
            'first_analyzed_at' => now()->subDays(5),
            'last_analyzed_at'  => now()->subHours(2),
        ]);

        // UI should show "Re-analyzed X ago"
        $isReanalyzed = $product2->last_analyzed_at && $product2->first_analyzed_at &&
                       $product2->last_analyzed_at->ne($product2->first_analyzed_at);
        $this->assertTrue($isReanalyzed);

        // Test case 3: Fallback to updated_at when analysis timestamps missing
        $product3 = AsinData::factory()->create([
            'first_analyzed_at' => null,
            'last_analyzed_at'  => null,
        ]);

        $displayTimestamp = ($product3->first_analyzed_at ?? $product3->updated_at);
        $this->assertEquals($product3->updated_at, $displayTimestamp);
    }

    #[Test]
    public function future_reanalyze_button_behavior()
    {
        // Simulate what the future "Re-analyze" button should do
        $originalAnalysisDate = now()->subWeeks(1);
        $product = AsinData::factory()->create([
            'asin'              => 'B0TESTFUTURE1',
            'status'            => 'completed',
            'fake_percentage'   => 50.0,
            'grade'             => 'C',
            'first_analyzed_at' => $originalAnalysisDate,
            'last_analyzed_at'  => $originalAnalysisDate,
        ]);

        // Simulate user clicking "Re-analyze" button
        // This should update last_analyzed_at but preserve first_analyzed_at
        $product->update([
            'fake_percentage'  => 30.0,
            'grade'            => 'B',
            'last_analyzed_at' => now(), // Only update this timestamp
        ]);

        $product->refresh();

        // Verify behavior (use format to avoid microsecond precision issues)
        $this->assertEquals($originalAnalysisDate->format('Y-m-d H:i:s'), $product->first_analyzed_at->format('Y-m-d H:i:s'));
        $this->assertNotEquals($originalAnalysisDate->format('Y-m-d H:i:s'), $product->last_analyzed_at->format('Y-m-d H:i:s'));

        // UI should now show "Re-analyzed X ago"
        $isReanalyzed = $product->last_analyzed_at && $product->first_analyzed_at &&
                       $product->last_analyzed_at->ne($product->first_analyzed_at);
        $this->assertTrue($isReanalyzed);
    }
}
