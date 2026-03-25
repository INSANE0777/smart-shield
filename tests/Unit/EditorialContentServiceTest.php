<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\EditorialContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EditorialContentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_editorial_content_for_valid_product(): void
    {
        // Mock the API response
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'buyers_guide' => [
                                    'headline'           => 'Test Headline for Product',
                                    'introduction'       => 'Test introduction text.',
                                    'key_considerations' => ['Consideration 1', 'Consideration 2', 'Consideration 3'],
                                    'what_to_look_for'   => 'Test what to look for.',
                                ],
                                'category_context' => [
                                    'market_overview'    => 'Test market overview.',
                                    'common_issues'      => 'Test common issues.',
                                    'quality_indicators' => 'Test quality indicators.',
                                ],
                                'authenticity_insights' => [
                                    'grade_interpretation' => 'Test grade interpretation.',
                                    'trust_recommendation' => 'Test trust recommendation.',
                                    'review_reading_tips'  => 'Test review reading tips.',
                                ],
                                'expert_perspective' => [
                                    'overall_assessment'      => 'Test overall assessment.',
                                    'purchase_considerations' => 'Test purchase considerations.',
                                    'alternatives_note'       => 'Test alternatives note.',
                                ],
                                'content_meta' => [
                                    'word_count'        => 450,
                                    'expertise_signals' => ['signal 1', 'signal 2'],
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
            'product_title'     => 'Test Blender 1000W',
            'grade'             => 'B',
            'fake_percentage'   => 15.5,
            'amazon_rating'     => 4.5,
            'adjusted_rating'   => 4.2,
            'editorial_status'  => 'pending',
        ]);

        $service = new EditorialContentService();
        $result = $service->generateEditorialContent($asinData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('buyers_guide', $result);
        $this->assertArrayHasKey('category_context', $result);
        $this->assertArrayHasKey('authenticity_insights', $result);
        $this->assertArrayHasKey('expert_perspective', $result);

        $asinData->refresh();
        $this->assertEquals('completed', $asinData->editorial_status);
        $this->assertNotNull($asinData->editorial_generated_at);
        $this->assertNotNull($asinData->editorial_content);
    }

    #[Test]
    public function it_throws_exception_for_product_without_title(): void
    {
        $asinData = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => null,
        ]);

        $service = new EditorialContentService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product title is required');

        $service->generateEditorialContent($asinData);
    }

    #[Test]
    public function it_handles_api_failure_gracefully(): void
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

        $service = new EditorialContentService();

        $this->expectException(\Exception::class);

        try {
            $service->generateEditorialContent($asinData);
        } finally {
            $asinData->refresh();
            $this->assertEquals('failed', $asinData->editorial_status);
        }
    }

    #[Test]
    public function it_handles_malformed_json_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'not valid json',
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

        $service = new EditorialContentService();
        $result = $service->generateEditorialContent($asinData);

        // Should return fallback content
        $this->assertIsArray($result);
        $this->assertArrayHasKey('buyers_guide', $result);

        $asinData->refresh();
        $this->assertEquals('completed', $asinData->editorial_status);
    }

    #[Test]
    public function it_checks_service_availability(): void
    {
        $service = new EditorialContentService();

        // Service should be available if API key is configured (or using Ollama)
        $this->assertIsBool($service->isAvailable());
    }
}
