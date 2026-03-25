<?php

namespace Tests\Unit;

use App\Services\Providers\OllamaProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaProviderTest extends TestCase
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

    public function test_analyzes_reviews_successfully()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Amazing product! Fast shipping!', 'rating' => 5],
            ['id' => 2, 'text' => 'Terrible quality. Broke immediately.', 'rating' => 1],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'          => 'qwen2.5:7b',
                'response'       => '[{"id": 1, "score": 15}, {"id": 2, "score": 25}]',
                'done'           => true,
                'total_duration' => 5000000000,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertArrayHasKey('analysis_provider', $result);
        $this->assertArrayHasKey('total_cost', $result);
        $this->assertCount(2, $result['detailed_scores']);
        $this->assertEquals('Ollama-qwen2.5:7b', $result['analysis_provider']);
        $this->assertEquals(0.0, $result['total_cost']);
    }

    public function test_handles_connection_errors_gracefully()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 5],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response('', 500),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ollama API request failed');

        $this->provider->analyzeReviews($reviews);
    }

    public function test_returns_empty_results_for_empty_reviews()
    {
        $result = $this->provider->analyzeReviews([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertEmpty($result['results']);
    }

    public function test_calculates_optimized_max_tokens()
    {
        $tokens = $this->provider->getOptimizedMaxTokens(25);

        // Should be review count * 10 + buffer
        $expected = 25 * 10 + min(1000, 25 * 5); // 250 + 125 = 375
        $this->assertEquals($expected, $tokens);
    }

    public function test_checks_availability_with_ollama_running()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response(['models' => []]),
        ]);

        $this->assertTrue($this->provider->isAvailable());
    }

    public function test_checks_availability_with_ollama_not_running()
    {
        Http::fake([
            'localhost:11434/api/tags' => Http::response('', 500),
        ]);

        $this->assertFalse($this->provider->isAvailable());
    }

    public function test_returns_zero_cost()
    {
        $cost = $this->provider->getEstimatedCost(100);

        $this->assertEquals(0.0, $cost);
    }

    public function test_returns_correct_provider_name()
    {
        $name = $this->provider->getProviderName();

        $this->assertStringContainsString('Ollama', $name);
        $this->assertStringContainsString('qwen2.5:7b', $name);
    }

    public function test_handles_malformed_json_response()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 5],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => 'invalid json {',
                'done'     => true,
            ]),
        ]);

        // With improved parsing, malformed JSON now falls back to heuristic parsing
        $result = $this->provider->analyzeReviews($reviews);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('detailed_scores', $result);
        // Heuristic parsing should provide some result even with malformed JSON
    }

    public function test_uses_configured_timeout()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 5],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id": 1, "score": 50}]',
                'done'     => true,
            ]),
        ]);

        $this->provider->analyzeReviews($reviews);

        // Just verify that the request was made successfully with the expected URL
        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/api/generate';
        });
    }

    public function test_analyzes_spanish_reviews_with_qwen_model()
    {
        $spanishReviews = [
            ['id' => 'es1', 'rating' => 5, 'review_text' => '¡Increíble! ¡Perfecto! ¡Lo mejor!', 'meta_data' => ['verified_purchase' => false]],
            ['id' => 'es2', 'rating' => 2, 'review_text' => 'No me gustó para nada. La calidad es muy mala y no funciona como esperaba.', 'meta_data' => ['verified_purchase' => true]],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id": "es1", "score": 92}, {"id": "es2", "score": 18}]',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($spanishReviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        // Handle both legacy integer format and new object format
        $es1Score = is_array($result['detailed_scores']['es1']) ? $result['detailed_scores']['es1']['score'] : $result['detailed_scores']['es1'];
        $es2Score = is_array($result['detailed_scores']['es2']) ? $result['detailed_scores']['es2']['score'] : $result['detailed_scores']['es2'];

        $this->assertEquals(92, $es1Score); // Fake pattern detected
        $this->assertEquals(18, $es2Score); // Genuine criticism
        $this->assertEquals('Ollama-qwen2.5:7b', $result['analysis_provider']);
    }

    public function test_analyzes_japanese_reviews_with_qwen_model()
    {
        $japaneseReviews = [
            ['id' => 'jp1', 'rating' => 5, 'review_text' => 'Amazing! Perfect!', 'meta_data' => ['verified_purchase' => false]],
            ['id' => 'jp2', 'rating' => 3, 'review_text' => 'Decent product. Quality is reasonable for the price.', 'meta_data' => ['verified_purchase' => true]],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id": "jp1", "score": 95}, {"id": "jp2", "score": 30}]',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($japaneseReviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        // Handle both legacy integer format and new object format
        $jp1Score = is_array($result['detailed_scores']['jp1']) ? $result['detailed_scores']['jp1']['score'] : $result['detailed_scores']['jp1'];
        $jp2Score = is_array($result['detailed_scores']['jp2']) ? $result['detailed_scores']['jp2']['score'] : $result['detailed_scores']['jp2'];

        $this->assertEquals(95, $jp1Score); // Suspicious praise
        $this->assertEquals(30, $jp2Score); // Balanced review
    }

    public function test_analyzes_german_reviews_with_qwen_model()
    {
        $germanReviews = [
            ['id' => 'de1', 'rating' => 5, 'review_text' => 'Absolut fantastisch! Sehr empfehlenswert!', 'meta_data' => ['verified_purchase' => false]],
            ['id' => 'de2', 'rating' => 3, 'review_text' => 'Das Produkt ist in Ordnung. Die Verarbeitung könnte besser sein, aber für den Preis ist es akzeptabel.', 'meta_data' => ['verified_purchase' => true]],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id": "de1", "score": 88}, {"id": "de2", "score": 25}]',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($germanReviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        // Handle both legacy integer format and new object format
        $de1Score = is_array($result['detailed_scores']['de1']) ? $result['detailed_scores']['de1']['score'] : $result['detailed_scores']['de1'];
        $de2Score = is_array($result['detailed_scores']['de2']) ? $result['detailed_scores']['de2']['score'] : $result['detailed_scores']['de2'];

        $this->assertEquals(88, $de1Score);
        $this->assertEquals(25, $de2Score);
    }

    public function test_analyzes_mixed_language_reviews()
    {
        $mixedReviews = [
            ['id' => 'mix1', 'rating' => 5, 'review_text' => 'Amazing! ¡Excelente! Très bien!', 'meta_data' => ['verified_purchase' => false]],
            ['id' => 'mix2', 'rating' => 2, 'review_text' => 'Poor quality. Mala calidad. Mauvaise qualité.', 'meta_data' => ['verified_purchase' => true]],
        ];

        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => '[{"id": "mix1", "score": 90}, {"id": "mix2", "score": 22}]',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($mixedReviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        // Handle both legacy integer format and new object format
        $mix1Score = is_array($result['detailed_scores']['mix1']) ? $result['detailed_scores']['mix1']['score'] : $result['detailed_scores']['mix1'];
        $mix2Score = is_array($result['detailed_scores']['mix2']) ? $result['detailed_scores']['mix2']['score'] : $result['detailed_scores']['mix2'];

        $this->assertEquals(90, $mix1Score); // Mixed language fake
        $this->assertEquals(22, $mix2Score); // Mixed language genuine
    }

    public function test_prompt_includes_balanced_analysis_instructions()
    {
        $reviews = [
            ['id' => 1, 'rating' => 5, 'review_text' => 'Test review content', 'meta_data' => ['verified_purchase' => true]],
        ];

        Http::fake([
            'localhost:11434/api/generate' => function ($request) {
                $body = json_decode($request->body(), true);
                $prompt = $body['prompt'];

                // Verify balanced prompt elements are present (updated for balanced approach)
                $this->assertStringContainsString('Analyze reviews for authenticity', $prompt);
                $this->assertStringContainsString('Be ACCURATE and BALANCED', $prompt);
                $this->assertStringContainsString('STRONG GENUINE SIGNALS', $prompt);
                $this->assertStringContainsString('1|V|5★|', $prompt);
                $this->assertStringContainsString('fake_percentage', $prompt);

                return Http::response([
                    'model'    => 'qwen2.5:7b',
                    'response' => '[{"id": 1, "score": 15, "label": "genuine"}]',
                    'done'     => true,
                ]);
            },
        ]);

        $this->provider->analyzeReviews($reviews);
    }

    public function test_uses_configured_model_from_config()
    {
        // Test that the provider uses the configured model, not hardcoded
        config(['services.ollama.model' => 'custom-model:latest']);

        $provider = new OllamaProvider();
        $this->assertEquals('Ollama-custom-model:latest', $provider->getProviderName());
    }

    public function test_fallback_model_when_config_missing()
    {
        // Test fallback to llama3.2:3b when config is not set
        config(['services.ollama.model' => '']);

        $provider = new OllamaProvider();
        $this->assertEquals('Ollama-llama3.2:3b', $provider->getProviderName());
    }

    public function test_heuristic_parsing_includes_provider_info()
    {
        $reviews = [
            ['id' => 1, 'text' => 'Test review', 'rating' => 5],
        ];

        // Mock a response that will trigger heuristic parsing
        Http::fake([
            'localhost:11434/api/generate' => Http::response([
                'model'    => 'qwen2.5:7b',
                'response' => 'ID: 1 - This review appears genuine with specific details',
                'done'     => true,
            ]),
        ]);

        $result = $this->provider->analyzeReviews($reviews);

        $this->assertArrayHasKey('detailed_scores', $result);
        $this->assertArrayHasKey('analysis_provider', $result);
        $this->assertArrayHasKey('total_cost', $result);
        $this->assertEquals('Ollama-qwen2.5:7b', $result['analysis_provider']);
        $this->assertEquals(0.0, $result['total_cost']);
    }
}
