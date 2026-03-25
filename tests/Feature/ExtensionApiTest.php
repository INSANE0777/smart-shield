<?php

namespace Tests\Feature;

use App\Models\AnalysisSession;
use App\Models\AsinData;
use App\Services\LLMServiceManager;
use App\Services\MetricsCalculationService;
use App\Services\ReviewAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExtensionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $validApiKey = 'test-api-key-12345';
    private array $sampleReviewData;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test API key
        Config::set('services.extension.api_key', $this->validApiKey);

        // Mock external services
        Http::fake();
        Queue::fake();

        // Sample review data from user's example
        $this->sampleReviewData = [
            'asin'                 => 'B0FFVTPRQY',
            'country'              => 'ca',
            'product_url'          => 'https://www.amazon.ca/Victorias-Secret-Wireless-Smoothing-Adjustable/dp/B0FFVTPRQY/ref=ast_sto_dp_puis?th=1&psc=1',
            'extraction_timestamp' => '2025-01-04T21:22:00.123Z',
            'extension_version'    => '1.4.4',
            'total_reviews'        => 10,
            'product_info'         => [
                'title'                   => 'Victorias Secret Wireless Smoothing Adjustable Bra',
                'description'             => 'Comfortable wireless bra with adjustable straps and smooth fabric for all-day comfort.',
                'image_url'               => 'https://m.media-amazon.com/images/I/61234567890._AC_SL1500_.jpg',
                'amazon_rating'           => 4.2,
                'total_reviews_on_amazon' => 1247,
                'price'                   => '$29.99',
                'availability'            => 'In Stock',
            ],
            'reviews' => [
                [
                    'author'            => 'beth',
                    'content'           => 'Great shape, fabric, and quality for a wireless bra. I started going wireless in Covid and can never go back. This offers great shape with comfort.',
                    'date'              => '2025-08-31',
                    'extraction_index'  => 1,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'RGEOLHTBB5CYJ',
                    'title'             => 'Great shape for a wireless bra!',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'Lisa G. Saylor',
                    'content'           => "Not the 1st time I have bought this particular bra. No underwire is a big plus! They last a long time. Shoulder straps tend to loosen over time, but I find other bras do the same. I have rounded shoulders so things like pocketbooks and straps just don't stay up. I find them comfortable with light padding. I will buy again!",
                    'date'              => '2025-07-26',
                    'extraction_index'  => 2,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'R11YBCQWYYRT26',
                    'title'             => 'Comfortable',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'Michael Henry',
                    'content'           => 'Straps are super uncomfortable and dig in your skin.',
                    'date'              => '2025-07-07',
                    'extraction_index'  => 3,
                    'helpful_votes'     => 0,
                    'rating'            => 1,
                    'review_id'         => 'RP32FVJ4JBXP2',
                    'title'             => 'Straps suck',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'LILIAN',
                    'content'           => 'excelente material, muy comodo la talla es exacta, me parece una muy buena compra',
                    'date'              => '2025-08-27',
                    'extraction_index'  => 4,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'R29V37XQIDEFZO',
                    'title'             => 'talla exacta',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'E J',
                    'content'           => 'Will be buying more!',
                    'date'              => '2025-08-06',
                    'extraction_index'  => 5,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'R3U3O42RW55SII',
                    'title'             => 'Repurchasing!',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'carl mackenstein',
                    'content'           => 'bought this bra before and it is comfortable and no wires.',
                    'date'              => '2025-07-30',
                    'extraction_index'  => 6,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'R29BVKXQJO2AF2',
                    'title'             => 'comfortable no wires',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'Judith Guerra',
                    'content'           => 'Súper cómodos para mi los mejores',
                    'date'              => '2025-07-25',
                    'extraction_index'  => 7,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'R3J7WGOZ20SVOQ',
                    'title'             => 'Súper cómodos',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'Stacia',
                    'content'           => 'The clasp broke after a few months. But it was very comfortable. And true to size based on my size with Victoria secret sizing.',
                    'date'              => '2025-06-23',
                    'extraction_index'  => 8,
                    'helpful_votes'     => 0,
                    'rating'            => 3,
                    'review_id'         => 'RNPP2WDMDIYT1',
                    'title'             => 'Comfortable but flimsy',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'Mav647',
                    'content'           => "My daughter's favorite bra. She said it's comfortable and very pretty.",
                    'date'              => '2025-07-02',
                    'extraction_index'  => 9,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'R3QM6I21LTFUEU',
                    'title'             => 'Comfortable and pretty',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
                [
                    'author'            => 'Kat',
                    'content'           => 'True to size. I ordered more in different colors and some for my daughter.',
                    'date'              => '2025-05-10',
                    'extraction_index'  => 10,
                    'helpful_votes'     => 0,
                    'rating'            => 5,
                    'review_id'         => 'R13IMF560NF3P5',
                    'title'             => 'True to size.',
                    'verified_purchase' => true,
                    'vine_customer'     => false,
                ],
            ],
        ];
    }

    #[Test]
    public function it_successfully_processes_valid_extension_data()
    {
        // Mock the LLM service to return analysis results
        $this->mockLLMAnalysis();

        $response = $this->postJson('/api/extension/submit-reviews', $this->sampleReviewData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'asin'              => 'B0FFVTPRQY',
                'country'           => 'ca',
                'processed_reviews' => 10,
                'analysis_complete' => true,
            ])
            ->assertJsonStructure([
                'success',
                'asin',
                'country',
                'analysis_id',
                'processed_reviews',
                'analysis_complete',
                'results' => [
                    'fake_percentage',
                    'grade',
                    'explanation',
                    'amazon_rating',
                    'adjusted_rating',
                    'rating_difference',
                ],
                'statistics' => [
                    'total_reviews_on_amazon',
                    'reviews_analyzed',
                    'genuine_reviews',
                    'fake_reviews',
                ],
                'product_info' => [
                    'title',
                    'description',
                    'image_url',
                ],
                'view_url',
                'redirect_url',
            ]);

        // Verify data was saved to database
        $this->assertDatabaseHas('asin_data', [
            'asin'                    => 'B0FFVTPRQY',
            'country'                 => 'ca',
            'source'                  => 'chrome_extension',
            'extension_version'       => '1.4.4',
            'total_reviews_on_amazon' => 1247, // From product_info.total_ratings
            'product_title'           => 'Victorias Secret Wireless Smoothing Adjustable Bra',
            'amazon_rating'           => 4.2,
        ]);

        // Verify reviews were saved correctly
        $asinData = AsinData::where('asin', 'B0FFVTPRQY')->first();
        $savedReviews = json_decode($asinData->reviews, true);
        $this->assertCount(10, $savedReviews);
        $this->assertEquals('RGEOLHTBB5CYJ', $savedReviews[0]['id']);
        $this->assertEquals('beth', $savedReviews[0]['author']);
    }

    #[Test]
    public function it_rejects_requests_without_api_key_in_production()
    {
        // Set to production environment to test API key validation
        $this->app['env'] = 'production';

        $response = $this->postJson('/api/extension/submit-reviews', $this->sampleReviewData);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid or missing API key',
            ]);
    }

    #[Test]
    public function it_rejects_requests_with_invalid_api_key_in_production()
    {
        // Set to production environment to test API key validation
        $this->app['env'] = 'production';

        $response = $this->postJson('/api/extension/submit-reviews', $this->sampleReviewData, [
            'X-API-Key' => 'invalid-key',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid or missing API key',
            ]);
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $invalidData = $this->sampleReviewData;
        unset($invalidData['asin']);

        $response = $this->postJson('/api/extension/submit-reviews', $invalidData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error'   => 'Invalid data format',
            ])
            ->assertJsonStructure([
                'success',
                'error',
                'details',
            ]);
    }

    #[Test]
    public function it_validates_asin_format()
    {
        $invalidData = $this->sampleReviewData;
        $invalidData['asin'] = 'INVALID'; // Invalid ASIN format (too short)

        $response = $this->postJson('/api/extension/submit-reviews', $invalidData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_validates_country_code_format()
    {
        $invalidData = $this->sampleReviewData;
        $invalidData['country'] = 'USA'; // Should be 2 characters

        $response = $this->postJson('/api/extension/submit-reviews', $invalidData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_validates_review_structure()
    {
        $invalidData = $this->sampleReviewData;
        unset($invalidData['reviews'][0]['author']); // Remove required field

        $response = $this->postJson('/api/extension/submit-reviews', $invalidData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_validates_rating_range()
    {
        $invalidData = $this->sampleReviewData;
        $invalidData['reviews'][0]['rating'] = 6; // Invalid rating (should be 1-5)

        $response = $this->postJson('/api/extension/submit-reviews', $invalidData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_handles_empty_reviews_array()
    {
        $this->mockLLMAnalysisForNoReviews();

        $emptyReviewsData = $this->sampleReviewData;
        $emptyReviewsData['reviews'] = [];
        $emptyReviewsData['total_reviews'] = 0;

        $response = $this->postJson('/api/extension/submit-reviews', $emptyReviewsData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('processed_reviews', 0);
    }

    #[Test]
    public function it_gets_analysis_status_successfully()
    {
        // Create existing analysis data with sample reviews
        $sampleReviews = [
            ['id' => 1, 'text' => 'Great product!'],
            ['id' => 2, 'text' => 'Good quality'],
        ];

        $asinData = AsinData::create([
            'asin'            => 'B0FFVTPRQY',
            'country'         => 'ca',
            'status'          => 'completed',
            'fake_percentage' => 25.5,
            'grade'           => 'B',
            'amazon_rating'   => 4.2,
            'adjusted_rating' => 3.8,
            'explanation'     => 'Some reviews appear suspicious but overall trustworthy.',
            'reviews'         => json_encode($sampleReviews),
        ]);

        $response = $this->getJson('/api/extension/analysis/B0FFVTPRQY/ca', [
            'X-API-Key' => $this->validApiKey,
        ]);

        // Response is returned DIRECTLY without wrapper (extension client adds wrapper)
        $response->assertStatus(200)
            ->assertJson([
                'asin'              => 'B0FFVTPRQY',
                'country'           => 'ca',
                'status'            => 'completed',
                'analysis_complete' => true,
                'grade'             => 'B',
                'fake_percentage'   => 25.5,
                'amazon_rating'     => 4.2,
            ])
            ->assertJsonStructure([
                'asin',
                'country',
                'status',
                'analysis_complete',
                'redirect_url',
                'view_url',
                'fake_percentage',
                'grade',
                'adjusted_rating',
                'amazon_rating',
                'explanation',
                'analyzed_at',
            ]);

        // Verify the critical fields that Chrome extension checks
        $responseData = $response->json();
        $this->assertTrue($responseData['analysis_complete']);
        $this->assertEquals('completed', $responseData['status']);
        $this->assertEquals('B', $responseData['grade']);
        $this->assertEquals(25.5, $responseData['fake_percentage']);
        $this->assertNotEmpty($responseData['redirect_url']);
        $this->assertNotEmpty($responseData['view_url']);
    }

    #[Test]
    public function it_returns_in_progress_status_for_incomplete_analysis()
    {
        // Create analysis data that's not yet complete (missing required fields)
        $asinData = AsinData::create([
            'asin'            => 'B0INCOMPLETE',
            'country'         => 'us',
            'status'          => 'processing',
            'fake_percentage' => null, // Missing - makes isAnalyzed() return false
            'grade'           => null, // Missing - makes isAnalyzed() return false
            'reviews'         => json_encode([['id' => 1, 'text' => 'Sample review']]),
        ]);

        $response = $this->getJson('/api/extension/analysis/B0INCOMPLETE/us', [
            'X-API-Key' => $this->validApiKey,
        ]);

        // Response is returned DIRECTLY without wrapper
        $response->assertStatus(200)
            ->assertJson([
                'asin'              => 'B0INCOMPLETE',
                'country'           => 'us',
                'status'            => 'processing',
                'analysis_complete' => false,
            ]);

        // Verify the critical fields that Chrome extension checks for in-progress state
        $responseData = $response->json();
        $this->assertFalse($responseData['analysis_complete']);
        $this->assertEquals('processing', $responseData['status']);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_analysis()
    {
        $response = $this->getJson('/api/extension/analysis/NONEXISTENT/us', [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error'   => 'No analysis found',
                'asin'    => 'NONEXISTENT',
                'country' => 'us',
            ]);
    }

    #[Test]
    public function it_accepts_api_key_as_parameter()
    {
        $this->mockLLMAnalysis();

        $dataWithApiKey = array_merge($this->sampleReviewData, [
            'api_key' => $this->validApiKey,
        ]);

        $response = $this->postJson('/api/extension/submit-reviews', $dataWithApiKey);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function it_handles_llm_analysis_failure_gracefully()
    {
        // Mock LLM service to throw exception
        $this->mock(LLMServiceManager::class, function ($mock) {
            $mock->shouldReceive('analyzeReviews')
                ->andThrow(new \Exception('LLM service unavailable'));
        });

        $response = $this->postJson('/api/extension/submit-reviews', $this->sampleReviewData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'error',
            ]);
    }

    #[Test]
    public function it_updates_existing_asin_data()
    {
        // Create existing ASIN data
        $existingAsinData = AsinData::create([
            'asin'    => 'B0FFVTPRQY',
            'country' => 'ca',
            'status'  => 'pending',
            'reviews' => json_encode([]),
        ]);

        $this->mockLLMAnalysis();

        $response = $this->postJson('/api/extension/submit-reviews', $this->sampleReviewData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200);

        // Verify the existing record was updated, not duplicated
        $this->assertEquals(1, AsinData::where('asin', 'B0FFVTPRQY')->count());

        $updatedAsinData = AsinData::where('asin', 'B0FFVTPRQY')->first();
        $this->assertEquals('chrome_extension', $updatedAsinData->source);
        $this->assertEquals('1.4.4', $updatedAsinData->extension_version);
    }

    private function mockLLMAnalysis(): void
    {
        $this->mock(ReviewAnalysisService::class, function ($mock) {
            $mock->shouldReceive('analyzeWithLLM')
                ->andReturnUsing(function ($asinData) {
                    // Update the model with analysis results
                    $asinData->update([
                        'openai_result' => [
                            'detailed_scores' => [
                                'R1'  => ['score' => 25, 'label' => 'genuine'],
                                'R2'  => ['score' => 30, 'label' => 'genuine'],
                                'R3'  => ['score' => 35, 'label' => 'genuine'],
                                'R4'  => ['score' => 20, 'label' => 'genuine'],
                                'R5'  => ['score' => 28, 'label' => 'genuine'],
                                'R6'  => ['score' => 32, 'label' => 'genuine'],
                                'R7'  => ['score' => 22, 'label' => 'genuine'],
                                'R8'  => ['score' => 38, 'label' => 'genuine'],
                                'R9'  => ['score' => 26, 'label' => 'genuine'],
                                'R10' => ['score' => 31, 'label' => 'genuine'],
                            ],
                        ],
                        'status' => 'analyzed',
                    ]);

                    return $asinData->fresh();
                });

            $mock->shouldReceive('calculateFinalMetrics')
                ->andReturnUsing(function ($asinData) {
                    // Update the model with final metrics
                    $asinData->update([
                        'fake_percentage' => 0.0, // All reviews are genuine (scores < 85)
                        'grade'           => 'A',
                        'explanation'     => 'Test analysis summary',
                        'adjusted_rating' => 4.2,
                        'status'          => 'completed',
                    ]);

                    return [
                        'fake_percentage' => 0.0,
                        'grade'           => 'A',
                        'summary'         => 'Test analysis summary',
                    ];
                });
        });
    }

    private function mockLLMAnalysisForNoReviews(): void
    {
        $this->mock(ReviewAnalysisService::class, function ($mock) {
            $mock->shouldReceive('analyzeWithLLM')
                ->andReturnUsing(function ($asinData) {
                    $asinData->update([
                        'fake_percentage' => 0,
                        'grade'           => 'U',
                        'summary'         => 'No reviews available for analysis',
                        'status'          => 'completed',
                    ]);

                    return $asinData->fresh();
                });

            $mock->shouldReceive('calculateFinalMetrics')
                ->andReturn([
                    'fake_percentage' => 0,
                    'grade'           => 'U',
                    'explanation'     => 'No reviews available for analysis',
                    'amazon_rating'   => 0,
                    'adjusted_rating' => 0,
                    'total_reviews'   => 0,
                    'fake_count'      => 0,
                ]);
        });

        $this->mock(MetricsCalculationService::class, function ($mock) {
            $mock->shouldReceive('calculateFinalMetrics')
                ->andReturn([
                    'fake_percentage' => 0,
                    'grade'           => 'U',
                    'summary'         => 'No reviews available for analysis',
                ]);
        });
    }

    #[Test]
    public function it_includes_detailed_statistics_in_response(): void
    {
        $this->mockLLMAnalysis();

        $response = $this->postJson('/api/extension/submit-reviews', $this->sampleReviewData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        // Verify detailed statistics are calculated correctly
        $this->assertEquals(10, $data['statistics']['reviews_analyzed']);
        $this->assertEquals(10, $data['statistics']['genuine_reviews']); // 0% fake = all genuine
        $this->assertEquals(0, $data['statistics']['fake_reviews']); // 0% fake
        $this->assertArrayHasKey('total_reviews_on_amazon', $data['statistics']);

        // Verify results structure
        $this->assertArrayHasKey('amazon_rating', $data['results']);
        $this->assertArrayHasKey('adjusted_rating', $data['results']);
        $this->assertArrayHasKey('rating_difference', $data['results']);
        $this->assertArrayHasKey('explanation', $data['results']);

        // Verify product info
        $this->assertArrayHasKey('title', $data['product_info']);
        $this->assertArrayHasKey('description', $data['product_info']);
        $this->assertArrayHasKey('image_url', $data['product_info']);

        // Verify URLs
        $this->assertStringContainsString('/amazon/ca/B0FFVTPRQY', $data['view_url']);
        $this->assertStringContainsString('/amazon/ca/B0FFVTPRQY', $data['redirect_url']);
    }

    #[Test]
    public function it_handles_products_with_no_reviews_gracefully(): void
    {
        $emptyReviewsData = $this->sampleReviewData;
        $emptyReviewsData['reviews'] = [];
        $emptyReviewsData['total_reviews'] = 0;

        $this->mockLLMAnalysisForNoReviews();

        $response = $this->postJson('/api/extension/submit-reviews', $emptyReviewsData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(0, $data['processed_reviews']);
        $this->assertEquals(0, $data['statistics']['reviews_analyzed']);
        $this->assertEquals(0, $data['statistics']['genuine_reviews']);
        $this->assertEquals(0, $data['statistics']['fake_reviews']);
        $this->assertEquals('U', $data['results']['grade']);
    }

    #[Test]
    public function it_preserves_data_for_subsequent_searches(): void
    {
        $this->mockLLMAnalysis();

        // Submit via extension API
        $response = $this->postJson('/api/extension/submit-reviews', $this->sampleReviewData, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200);
        $analysisId = $response->json('analysis_id');

        // Verify data is saved and can be retrieved
        $asinData = AsinData::find($analysisId);
        $this->assertNotNull($asinData);
        $this->assertEquals('B0FFVTPRQY', $asinData->asin);
        $this->assertEquals('ca', $asinData->country);
        $this->assertEquals('chrome_extension', $asinData->source);
        $this->assertEquals('1.4.4', $asinData->extension_version);
        $this->assertCount(10, $asinData->getReviewsArray());
    }

    #[Test]
    public function it_supports_chrome_extension_check_analysis_route_alias(): void
    {
        // Create existing analysis data with sample reviews
        $sampleReviews = [
            ['id' => 1, 'text' => 'Great product!'],
            ['id' => 2, 'text' => 'Good quality'],
        ];

        AsinData::create([
            'asin'                    => 'B0F2H3W2JR',
            'country'                 => 'ca',
            'status'                  => 'completed',
            'fake_percentage'         => 28.0,
            'grade'                   => 'C',
            'amazon_rating'           => 4.3,
            'adjusted_rating'         => 3.7,
            'explanation'             => 'This product shows some suspicious review patterns.',
            'product_title'           => 'Full Size Wired Keyboard for Mac',
            'product_image_url'       => 'https://m.media-amazon.com/images/I/61n1I3fdbuL._AC_SL1500_.jpg',
            'total_reviews_on_amazon' => 27,
            'reviews'                 => json_encode($sampleReviews),
        ]);

        // Test the chrome-extension route alias (different from /api/extension/analysis)
        $response = $this->getJson('/api/chrome-extension/check-analysis/B0F2H3W2JR/ca', [
            'X-API-Key' => $this->validApiKey,
        ]);

        // Response is returned DIRECTLY without wrapper
        $response->assertStatus(200)
            ->assertJson([
                'asin'              => 'B0F2H3W2JR',
                'country'           => 'ca',
                'status'            => 'completed',
                'analysis_complete' => true,
                'fake_percentage'   => 28.0,
                'grade'             => 'C',
            ]);

        // Verify critical fields
        $responseData = $response->json();
        $this->assertTrue($responseData['analysis_complete']);
        $this->assertEquals(28.0, $responseData['fake_percentage']);
        $this->assertEquals('C', $responseData['grade']);
        $this->assertEquals('Full Size Wired Keyboard for Mac', $responseData['product_title']);
        $this->assertStringContainsString('/amazon/ca/B0F2H3W2JR', $responseData['redirect_url']);
    }

    #[Test]
    public function it_returns_404_from_chrome_extension_route_for_nonexistent_analysis(): void
    {
        $response = $this->getJson('/api/chrome-extension/check-analysis/NONEXISTENT/us', [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error'   => 'No analysis found',
                'asin'    => 'NONEXISTENT',
                'country' => 'us',
            ]);
    }

    #[Test]
    public function it_returns_in_progress_from_chrome_extension_route(): void
    {
        AsinData::create([
            'asin'            => 'B0PROCESSING',
            'country'         => 'ca',
            'status'          => 'processing',
            'fake_percentage' => null,
            'grade'           => null,
            'reviews'         => json_encode([['id' => 1, 'text' => 'Sample review']]),
        ]);

        $response = $this->getJson('/api/chrome-extension/check-analysis/B0PROCESSING/ca', [
            'X-API-Key' => $this->validApiKey,
        ]);

        // Response is returned DIRECTLY without wrapper
        $response->assertStatus(200)
            ->assertJson([
                'asin'              => 'B0PROCESSING',
                'country'           => 'ca',
                'status'            => 'processing',
                'analysis_complete' => false,
            ]);

        // Verify the extension will see analysis_complete = false
        $responseData = $response->json();
        $this->assertFalse($responseData['analysis_complete']);
    }

    #[Test]
    public function progress_endpoint_returns_analysis_complete_during_processing(): void
    {
        $session = AnalysisSession::create([
            'user_session'        => 'test_session_123',
            'asin'                => 'B0TEST12345',
            'product_url'         => 'https://www.amazon.com/dp/B0TEST12345/',
            'status'              => 'processing',
            'current_step'        => 2,
            'total_steps'         => 5,
            'progress_percentage' => 40.0,
            'current_message'     => 'Analyzing reviews with AI...',
        ]);

        $response = $this->getJson('/api/extension/progress/'.$session->id, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'status'            => 'processing',
                'analysis_complete' => false, // CRITICAL: Must be present even during processing
            ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('analysis_complete', $responseData);
        $this->assertFalse($responseData['analysis_complete']);
    }

    #[Test]
    public function progress_endpoint_returns_analysis_complete_true_when_completed(): void
    {
        $session = AnalysisSession::create([
            'user_session'        => 'test_session_456',
            'asin'                => 'B0TEST67890',
            'product_url'         => 'https://www.amazon.com/dp/B0TEST67890/',
            'status'              => 'completed',
            'current_step'        => 5,
            'total_steps'         => 5,
            'progress_percentage' => 100.0,
            'current_message'     => 'Analysis complete!',
            'result'              => [
                'success'      => true,
                'redirect_url' => 'https://nullfake.com/amazon/us/B0TEST67890/test-product',
            ],
            'completed_at' => now(),
        ]);

        $response = $this->getJson('/api/extension/progress/'.$session->id, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'status'            => 'completed',
                'analysis_complete' => true,
            ]);

        $responseData = $response->json();
        $this->assertTrue($responseData['analysis_complete']);
        $this->assertArrayHasKey('redirect_url', $responseData);
    }

    #[Test]
    public function progress_endpoint_returns_analysis_complete_false_when_failed(): void
    {
        $session = AnalysisSession::create([
            'user_session'  => 'test_session_789',
            'asin'          => 'B0TESTFAIL1',
            'product_url'   => 'https://www.amazon.com/dp/B0TESTFAIL1/',
            'status'        => 'failed',
            'error_message' => 'LLM service unavailable',
            'completed_at'  => now(),
        ]);

        $response = $this->getJson('/api/extension/progress/'.$session->id, [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'           => true,
                'status'            => 'failed',
                'analysis_complete' => false,
                'error'             => 'LLM service unavailable',
            ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['analysis_complete']);
        $this->assertArrayHasKey('error', $responseData);
    }

    #[Test]
    public function progress_endpoint_returns_404_with_analysis_complete_for_nonexistent_session(): void
    {
        $response = $this->getJson('/api/extension/progress/nonexistent-session-id', [
            'X-API-Key' => $this->validApiKey,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success'           => false,
                'error'             => 'Analysis session not found',
                'analysis_complete' => false,
            ]);
    }
}
