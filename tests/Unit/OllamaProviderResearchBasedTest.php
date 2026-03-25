<?php

namespace Tests\Unit;

use App\Services\Providers\OllamaProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaProviderResearchBasedTest extends TestCase
{
    private OllamaProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ollama.base_url' => 'http://localhost:11434',
            'services.ollama.model'    => 'qwen2.5:7b',
            'services.ollama.timeout'  => 300,
        ]);

        $this->provider = new OllamaProvider();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function research_based_prompt_includes_scientific_methodology()
    {
        $reviews = [
            [
                'id'          => 'TEST001',
                'rating'      => 5,
                'review_text' => 'Amazing product! Perfect! Incredible quality!',
                'meta_data'   => ['verified_purchase' => false],
            ],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id":"TEST001","score":85,"label":"fake","confidence":0.9}]',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        // Verify the request was made with balanced prompt elements
        Http::assertSent(function ($request) {
            $body = $request->data();
            $prompt = $body['prompt'];

            // Check for key balanced prompt elements (updated for balanced approach)
            $this->assertStringContainsString('Analyze reviews for authenticity', $prompt);
            $this->assertStringContainsString('Be ACCURATE and BALANCED', $prompt);
            $this->assertStringContainsString('STRONG GENUINE SIGNALS', $prompt);

            // Verify temperature is set for consistency
            $this->assertEquals(0.1, $body['options']['temperature']);

            // Verify increased context and output settings (updated for large review sets)
            $this->assertEquals(4096, $body['options']['num_ctx']);
            $this->assertEquals(2048, $body['options']['num_predict']);

            return true;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handles_new_response_format_with_labels_and_confidence()
    {
        $reviews = [
            [
                'id'          => 'TEST001',
                'rating'      => 4,
                'review_text' => 'Good product, works as expected after 3 months of use.',
                'meta_data'   => ['verified_purchase' => true],
            ],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id":"TEST001","score":35,"label":"genuine","confidence":0.8}]',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertArrayHasKey('TEST001', $result['detailed_scores']);

        $scoreData = $result['detailed_scores']['TEST001'];
        $this->assertIsArray($scoreData);
        $this->assertEquals(35, $scoreData['score']);
        $this->assertEquals('genuine', $scoreData['label']); // Label comes from response
        $this->assertEquals(0.8, $scoreData['confidence']);
        $this->assertNotEmpty($scoreData['explanation']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function maintains_backward_compatibility_with_numeric_scores()
    {
        $reviews = [
            [
                'id'          => 'TEST001',
                'rating'      => 5,
                'review_text' => 'Test review',
                'meta_data'   => ['verified_purchase' => false],
            ],
        ];

        // Mock old-style numeric response
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id":"TEST001","score":75}]',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertArrayHasKey('TEST001', $result['detailed_scores']);

        $scoreData = $result['detailed_scores']['TEST001'];
        $this->assertIsArray($scoreData);
        $this->assertEquals(75, $scoreData['score']);
        $this->assertEquals('suspicious', $scoreData['label']); // Generated from score (75 is in 60-79 suspicious range)
        $this->assertGreaterThan(0, $scoreData['confidence']); // Generated from score
        $this->assertNotEmpty($scoreData['explanation']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generates_correct_labels_from_scores()
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('generateLabel');
        $method->setAccessible(true);

        // Test label generation thresholds (balanced approach with more granular labels)
        $this->assertEquals('genuine', $method->invoke($this->provider, 20));
        $this->assertEquals('likely_genuine', $method->invoke($this->provider, 35));
        $this->assertEquals('uncertain', $method->invoke($this->provider, 50));
        $this->assertEquals('suspicious', $method->invoke($this->provider, 70));
        $this->assertEquals('fake', $method->invoke($this->provider, 85));
        $this->assertEquals('fake', $method->invoke($this->provider, 95));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function calculates_confidence_based_on_score_extremes()
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('calculateConfidenceFromScore');
        $method->setAccessible(true);

        // High confidence for extreme scores
        $this->assertEquals(0.8, $method->invoke($this->provider, 15)); // Very genuine
        $this->assertEquals(0.8, $method->invoke($this->provider, 85)); // Very fake

        // Lower confidence for uncertain range
        $this->assertEquals(0.4, $method->invoke($this->provider, 50)); // Uncertain

        // Moderate confidence for borderline cases
        $this->assertEquals(0.6, $method->invoke($this->provider, 65)); // Borderline suspicious
        $this->assertEquals(0.7, $method->invoke($this->provider, 25)); // Borderline genuine
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generates_appropriate_explanations_for_each_label()
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('generateExplanationFromLabel');
        $method->setAccessible(true);

        $genuineExplanation = $method->invoke($this->provider, 'genuine', 15);
        $this->assertStringContainsString('authentic', $genuineExplanation);

        $uncertainExplanation = $method->invoke($this->provider, 'uncertain', 50);
        $this->assertStringContainsString('Mixed signals', $uncertainExplanation);

        $fakeExplanation = $method->invoke($this->provider, 'fake', 90);
        $this->assertStringContainsString('inauthentic', $fakeExplanation);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function clamps_scores_to_valid_range()
    {
        $reviews = [
            [
                'id'          => 'TEST001',
                'rating'      => 5,
                'review_text' => 'Test review',
                'meta_data'   => ['verified_purchase' => false],
            ],
        ];

        // Mock response with out-of-range scores
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id":"TEST001","score":150}]', // Over 100
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $scoreData = $result['detailed_scores']['TEST001'];
        $this->assertEquals(100, $scoreData['score']); // Should be clamped to 100
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handles_missing_meta_data_gracefully()
    {
        $reviews = [
            [
                'id'          => 'TEST001',
                'rating'      => 5,
                'review_text' => 'Test review without meta_data',
                // No meta_data key
            ],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id":"TEST001","score":60}]',
                'done'     => true,
            ]),
        ]);

        // Should not throw an exception
        $result = $this->provider->analyzeReviews($reviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertArrayHasKey('TEST001', $result['detailed_scores']);

        // Verify the request was made with balanced format
        Http::assertSent(function ($request) {
            $body = $request->data();
            $this->assertStringContainsString('TEST001|U|5★|Test review without meta_data', $body['prompt']);

            return true;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function includes_research_based_bias_guardrails_in_prompt()
    {
        $reviews = [
            [
                'id'          => 'TEST001',
                'rating'      => 2,
                'review_text' => 'Product broke after one week. Poor quality control.',
                'meta_data'   => ['verified_purchase' => true],
            ],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id":"TEST001","score":25,"label":"genuine","confidence":0.8}]',
                'done'     => true,
            ]),
        ]);

        $this->provider->analyzeReviews($reviews);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['prompt'];

            // Verify balanced prompt format (updated for balanced approach)
            $this->assertStringContainsString('Analyze reviews for authenticity', $prompt);
            $this->assertStringContainsString('DEFAULT TO GENUINE when uncertain', $prompt);
            $this->assertStringContainsString('TEST001|V|2★|Product broke after one week', $prompt);

            return true;
        });
    }
}
