<?php

namespace Tests\Unit;

use App\Jobs\ProcessEditorialContent;
use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessEditorialContentJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_dispatches_to_editorial_content_queue(): void
    {
        Queue::fake();

        $asinData = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'pending',
        ]);

        ProcessEditorialContent::dispatch($asinData->id);

        Queue::assertPushedOn('editorial-content', ProcessEditorialContent::class);
    }

    #[Test]
    public function it_skips_products_with_existing_editorial_content(): void
    {
        Http::fake(); // Should not be called

        $asinData = AsinData::factory()->create([
            'status'                 => 'completed',
            'have_product_data'      => true,
            'product_title'          => 'Test Product',
            'grade'                  => 'B',
            'fake_percentage'        => 15.5,
            'editorial_status'       => 'completed',
            'editorial_content'      => ['buyers_guide' => ['headline' => 'Test']],
            'editorial_generated_at' => now(),
        ]);

        $job = new ProcessEditorialContent($asinData->id);
        $job->handle(app(\App\Services\EditorialContentService::class));

        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_products_without_completed_review_analysis(): void
    {
        Http::fake(); // Should not be called

        $asinData = AsinData::factory()->create([
            'status'            => 'pending',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => null,
            'fake_percentage'   => null,
            'editorial_status'  => 'pending',
        ]);

        $job = new ProcessEditorialContent($asinData->id);
        $job->handle(app(\App\Services\EditorialContentService::class));

        Http::assertNothingSent();
    }

    #[Test]
    public function it_processes_eligible_products(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'buyers_guide' => [
                                    'headline'           => 'Test Headline',
                                    'introduction'       => 'Test intro.',
                                    'key_considerations' => ['One', 'Two', 'Three'],
                                    'what_to_look_for'   => 'Look for quality.',
                                ],
                                'category_context' => [
                                    'market_overview'    => 'Market overview.',
                                    'common_issues'      => 'Common issues.',
                                    'quality_indicators' => 'Quality indicators.',
                                ],
                                'authenticity_insights' => [
                                    'grade_interpretation' => 'Grade B means...',
                                    'trust_recommendation' => 'Trust recommendation.',
                                    'review_reading_tips'  => 'Review reading tips.',
                                ],
                                'expert_perspective' => [
                                    'overall_assessment'      => 'Overall assessment.',
                                    'purchase_considerations' => 'Purchase considerations.',
                                    'alternatives_note'       => 'Consider alternatives.',
                                ],
                                'content_meta' => [
                                    'word_count'        => 450,
                                    'expertise_signals' => ['signal1', 'signal2'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $asinData = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'pending',
        ]);

        $job = new ProcessEditorialContent($asinData->id);
        $job->handle(app(\App\Services\EditorialContentService::class));

        $asinData->refresh();
        $this->assertEquals('completed', $asinData->editorial_status);
        $this->assertNotNull($asinData->editorial_content);
    }

    #[Test]
    public function it_handles_missing_asin_data_gracefully(): void
    {
        $job = new ProcessEditorialContent(99999); // Non-existent ID
        $job->handle(app(\App\Services\EditorialContentService::class));

        // Should complete without exception
        $this->assertTrue(true);
    }

    #[Test]
    public function it_marks_status_as_failed_on_permanent_failure(): void
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        $asinData = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'editorial_status'  => 'pending',
        ]);

        $job = new ProcessEditorialContent($asinData->id);

        // Simulate final attempt (job won't re-throw on final attempt)
        $job->failed(new \Exception('API Error'));

        $asinData->refresh();
        $this->assertEquals('failed', $asinData->editorial_status);
    }

    #[Test]
    public function it_has_correct_job_tags(): void
    {
        $asinData = AsinData::factory()->create();

        $job = new ProcessEditorialContent($asinData->id);

        $this->assertEquals(['editorial-content', 'asin:'.$asinData->id], $job->tags());
    }
}
