<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\MetricsCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancedExplanationGenerationTest extends TestCase
{
    use RefreshDatabase;

    private MetricsCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetricsCalculationService();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_multi_paragraph_explanations_for_excellent_products()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 20, ['text' => 'Great product!', 'rating' => 5])),
        ]);

        // Set up openai_result with excellent authenticity (5% fake)
        $asinData->update([
            'openai_result' => json_encode([
                'fake_percentage' => 5,
                'confidence'      => 'high',
                'fake_examples'   => [],
                'key_patterns'    => [],
            ]),
        ]);

        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        // Should contain multiple paragraphs separated by \n\n
        $paragraphs = explode("\n\n", $explanation);
        $this->assertGreaterThanOrEqual(3, count($paragraphs), 'Should have at least 3 paragraphs');

        // Check content for excellent products (balanced language)
        $this->assertStringContainsString('excellent review authenticity', $explanation);
        $this->assertStringContainsString('authentic signals', $explanation);
        $this->assertStringContainsString('genuine customer feedback', $explanation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_multi_paragraph_explanations_for_good_products()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 15, ['text' => 'Good product', 'rating' => 4])),
        ]);

        // Set up openai_result with good authenticity (25% fake)
        $asinData->update([
            'openai_result' => json_encode([
                'fake_percentage' => 25,
                'confidence'      => 'medium',
                'fake_examples'   => [],
                'key_patterns'    => [],
            ]),
        ]);

        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        $paragraphs = explode("\n\n", $explanation);
        $this->assertGreaterThanOrEqual(3, count($paragraphs), 'Should have at least 3 paragraphs');

        // Check content for good products (balanced language)
        $this->assertStringContainsString('good review authenticity', $explanation);
        $this->assertStringContainsString('predominantly genuine', $explanation);
        $this->assertStringContainsString('reliable for', $explanation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_multi_paragraph_explanations_for_moderate_products()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 12, ['text' => 'Okay product', 'rating' => 3])),
        ]);

        // Set up openai_result with moderate concerns (40% fake)
        $asinData->update([
            'openai_result' => json_encode([
                'fake_percentage' => 40,
                'confidence'      => 'medium',
                'fake_examples'   => [],
                'key_patterns'    => [],
            ]),
        ]);

        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        $paragraphs = explode("\n\n", $explanation);
        $this->assertGreaterThanOrEqual(3, count($paragraphs), 'Should have at least 3 paragraphs');

        // Check content for moderate products (balanced language)
        $this->assertStringContainsString('mixed review profile', $explanation);
        $this->assertStringContainsString('genuine', $explanation);
        $this->assertStringContainsString('verified purchase', $explanation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_multi_paragraph_explanations_for_high_risk_products()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 10, ['text' => 'Amazing!', 'rating' => 5])),
        ]);

        // Set up openai_result with high fake activity (65% fake)
        $asinData->update([
            'openai_result' => json_encode([
                'fake_percentage' => 65,
                'confidence'      => 'high',
                'fake_examples'   => [],
                'key_patterns'    => [],
            ]),
        ]);

        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        $paragraphs = explode("\n\n", $explanation);
        $this->assertGreaterThanOrEqual(3, count($paragraphs), 'Should have at least 3 paragraphs');

        // Check content for high risk products (balanced language)
        $this->assertStringContainsString('concerning review patterns', $explanation);
        $this->assertStringContainsString('authenticity', $explanation);
        $this->assertStringContainsString('caution', $explanation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_multi_paragraph_explanations_for_very_high_risk_products()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 8, ['text' => 'Perfect!', 'rating' => 5])),
        ]);

        // Set up openai_result with very high fake activity (85% fake)
        $asinData->update([
            'openai_result' => json_encode([
                'fake_percentage' => 85,
                'confidence'      => 'high',
                'fake_examples'   => [],
                'key_patterns'    => [],
            ]),
        ]);

        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        $paragraphs = explode("\n\n", $explanation);
        $this->assertGreaterThanOrEqual(3, count($paragraphs), 'Should have at least 3 paragraphs');

        // Check content for very high risk products (balanced language)
        $this->assertStringContainsString('significant review authenticity concerns', $explanation);
        $this->assertStringContainsString('manipulation patterns', $explanation);
        $this->assertStringContainsString('research from multiple sources', $explanation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_preserves_llm_explanation_when_available()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 10, ['text' => 'Good product', 'rating' => 4])),
        ]);

        $llmExplanation = "LLM generated first paragraph.\n\nLLM generated second paragraph.\n\nLLM generated third paragraph.";

        // Set up openai_result with LLM explanation
        $asinData->update([
            'openai_result' => json_encode([
                'fake_percentage' => 30,
                'confidence'      => 'medium',
                'explanation'     => $llmExplanation,
                'fake_examples'   => [],
                'key_patterns'    => [],
            ]),
        ]);

        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        // Should use the LLM explanation
        $this->assertStringContainsString('LLM generated first paragraph', $explanation);
        $this->assertStringContainsString('LLM generated second paragraph', $explanation);
        $this->assertStringContainsString('LLM generated third paragraph', $explanation);

        // Should maintain paragraph structure
        $paragraphs = explode("\n\n", $explanation);
        $this->assertGreaterThanOrEqual(3, count($paragraphs));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_key_patterns_to_explanation_when_available()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 10, ['text' => 'Test review', 'rating' => 4])),
        ]);

        // Set up openai_result with key patterns
        $asinData->update([
            'openai_result' => json_encode([
                'fake_percentage' => 20,
                'confidence'      => 'medium',
                'fake_examples'   => [],
                'key_patterns'    => ['Generic praise', 'Short reviews', 'Timing clusters'],
            ]),
        ]);

        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        // Should include key patterns in explanation
        $this->assertStringContainsString('Key patterns identified', $explanation);
        $this->assertStringContainsString('Generic praise', $explanation);
        $this->assertStringContainsString('Short reviews', $explanation);
        $this->assertStringContainsString('Timing clusters', $explanation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_or_null_aggregate_data()
    {
        $asinData = AsinData::factory()->create([
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode(array_fill(0, 5, ['text' => 'Review', 'rating' => 3])),
            'openai_result'   => json_encode([
                'fake_percentage' => 35,
                'confidence'      => 'medium',
                'fake_examples'   => [],
                'key_patterns'    => [],
            ]),
        ]);

        // Test with minimal openai_result - should generate fallback explanation
        $result = $this->service->calculateFinalMetrics($asinData);

        $explanation = $asinData->fresh()->explanation;

        // Should generate fallback explanation with paragraph structure
        $this->assertNotNull($explanation);
        $paragraphs = explode("\n\n", $explanation);
        $this->assertGreaterThanOrEqual(3, count($paragraphs));
    }
}
