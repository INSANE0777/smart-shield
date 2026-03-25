<?php

namespace Tests\Unit;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AsinDataEditorialContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function has_editorial_content_returns_true_when_completed(): void
    {
        $asinData = AsinData::factory()->create([
            'editorial_status'       => 'completed',
            'editorial_content'      => ['buyers_guide' => ['headline' => 'Test']],
            'editorial_generated_at' => now(),
        ]);

        $this->assertTrue($asinData->hasEditorialContent());
    }

    #[Test]
    public function has_editorial_content_returns_false_when_pending(): void
    {
        $asinData = AsinData::factory()->create([
            'editorial_status'  => 'pending',
            'editorial_content' => null,
        ]);

        $this->assertFalse($asinData->hasEditorialContent());
    }

    #[Test]
    public function is_editorial_content_processing_returns_true_when_processing(): void
    {
        $asinData = AsinData::factory()->create([
            'editorial_status' => 'processing',
        ]);

        $this->assertTrue($asinData->isEditorialContentProcessing());
    }

    #[Test]
    public function needs_editorial_content_returns_true_for_eligible_product(): void
    {
        $asinData = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'pending',
        ]);

        $this->assertTrue($asinData->needsEditorialContent());
    }

    #[Test]
    public function needs_editorial_content_returns_false_when_already_completed(): void
    {
        $asinData = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'completed',
            'editorial_content' => ['buyers_guide' => ['headline' => 'Test']],
        ]);

        $this->assertFalse($asinData->needsEditorialContent());
    }

    #[Test]
    public function needs_editorial_content_returns_false_when_processing(): void
    {
        $asinData = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'processing',
        ]);

        $this->assertFalse($asinData->needsEditorialContent());
    }

    #[Test]
    public function needs_editorial_content_returns_false_when_review_analysis_incomplete(): void
    {
        $asinData = AsinData::factory()->create([
            'status'            => 'pending',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => null,
            'fake_percentage'   => null,
            'editorial_status'  => 'pending',
        ]);

        $this->assertFalse($asinData->needsEditorialContent());
    }

    #[Test]
    public function scope_pending_editorial_content_finds_eligible_products(): void
    {
        // Create an eligible product (failed status - retry candidate)
        AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product Failed',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'failed',
        ]);

        // Create an eligible product (pending status - default for unprocessed)
        AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product Pending',
            'grade'             => 'C',
            'fake_percentage'   => 25.0,
            'editorial_status'  => 'pending',
        ]);

        // Create an ineligible product (already completed)
        AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product Completed',
            'grade'             => 'A',
            'fake_percentage'   => 5.0,
            'editorial_status'  => 'completed',
            'editorial_content' => ['test' => 'data'],
        ]);

        // Create an ineligible product (currently processing)
        AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product Processing',
            'grade'             => 'B',
            'fake_percentage'   => 20.0,
            'editorial_status'  => 'processing',
        ]);

        $pending = AsinData::pendingEditorialContent()->get();

        // Should find 'pending' and 'failed', but not 'completed' or 'processing'
        $this->assertCount(2, $pending);
        $statuses = $pending->pluck('editorial_status')->toArray();
        $this->assertContains('pending', $statuses);
        $this->assertContains('failed', $statuses);
        $this->assertNotContains('completed', $statuses);
        $this->assertNotContains('processing', $statuses);
    }

    #[Test]
    public function editorial_content_is_cast_to_array(): void
    {
        $content = [
            'buyers_guide' => [
                'headline'     => 'Test Headline',
                'introduction' => 'Test intro.',
            ],
        ];

        $asinData = AsinData::factory()->create([
            'editorial_content' => $content,
        ]);

        $asinData->refresh();

        $this->assertIsArray($asinData->editorial_content);
        $this->assertEquals('Test Headline', $asinData->editorial_content['buyers_guide']['headline']);
    }

    #[Test]
    public function editorial_generated_at_is_cast_to_datetime(): void
    {
        $asinData = AsinData::factory()->create([
            'editorial_generated_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $asinData->editorial_generated_at);
    }
}

