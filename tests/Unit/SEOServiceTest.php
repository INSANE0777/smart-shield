<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\SEOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SEOServiceTest extends TestCase
{
    use RefreshDatabase;

    private SEOService $seoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seoService = new SEOService();
    }

    #[Test]
    public function it_generates_comprehensive_product_seo_data()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0123456789',
            'country'           => 'us',
            'product_title'     => 'Test Product Title',
            'fake_percentage'   => 25,
            'grade'             => 'B',
            'adjusted_rating'   => 4.2,
            'amazon_rating'     => 4.5,
            'status'            => 'completed',
            'have_product_data' => true,
            'product_image_url' => 'https://example.com/image.jpg',
            'explanation'       => 'This product has moderate fake review presence.',
            'reviews'           => json_encode([
                ['text' => 'Great product!', 'rating' => 5],
                ['text' => 'Good value', 'rating' => 4],
                ['text' => 'Average quality', 'rating' => 3],
            ]),
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Test basic SEO data structure
        $this->assertArrayHasKey('meta_title', $seoData);
        $this->assertArrayHasKey('meta_description', $seoData);
        $this->assertArrayHasKey('keywords', $seoData);
        $this->assertArrayHasKey('canonical_url', $seoData);

        // Test AI-optimized data
        $this->assertArrayHasKey('ai_summary', $seoData);
        $this->assertArrayHasKey('ai_keywords', $seoData);
        $this->assertArrayHasKey('question_answers', $seoData);
        $this->assertArrayHasKey('confidence_score', $seoData);

        // Test structured data schemas
        $this->assertArrayHasKey('product_schema', $seoData);
        $this->assertArrayHasKey('analysis_schema', $seoData);
        $this->assertArrayHasKey('dataset_schema', $seoData);
        $this->assertArrayHasKey('faq_schema', $seoData);
        $this->assertArrayHasKey('how_to_schema', $seoData);
    }

    #[Test]
    public function it_generates_optimized_title_based_on_fake_percentage()
    {
        // High fake percentage product
        $highFakeProduct = AsinData::factory()->create([
            'product_title'   => 'High Fake Product',
            'fake_percentage' => 60,
            'grade'           => 'F',
        ]);

        $seoData = $this->seoService->generateProductSEOData($highFakeProduct);
        $this->assertStringContainsString('60%', $seoData['meta_title']);
        $this->assertStringContainsString('Grade F', $seoData['meta_title']);

        // Low fake percentage product
        $lowFakeProduct = AsinData::factory()->create([
            'product_title'   => 'Trustworthy Product',
            'fake_percentage' => 5,
            'grade'           => 'A',
            'adjusted_rating' => 4.8,
        ]);

        $seoData = $this->seoService->generateProductSEOData($lowFakeProduct);
        $this->assertStringContainsString('5%', $seoData['meta_title']);
        $this->assertStringContainsString('Grade A', $seoData['meta_title']);
    }

    #[Test]
    public function it_generates_ai_optimized_summary()
    {
        $asinData = AsinData::factory()->create([
            'fake_percentage' => 30,
            'grade'           => 'C',
            'explanation'     => null, // Force fallback to basic summary
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        $this->assertStringContainsString('30%', $seoData['ai_summary']);
        $this->assertStringContainsString('C', $seoData['ai_summary']);
        $this->assertStringContainsString('AI techniques', $seoData['ai_summary']);
        $this->assertStringContainsString('informed purchasing decisions', $seoData['ai_summary']);
    }

    #[Test]
    public function it_generates_question_answer_pairs()
    {
        $asinData = AsinData::factory()->create([
            'fake_percentage' => 15,
            'grade'           => 'B',
            'adjusted_rating' => 4.1,
            'reviews'         => json_encode([
                ['text' => 'Good product', 'rating' => 4],
                ['text' => 'Excellent quality', 'rating' => 5],
            ]),
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        $this->assertIsArray($seoData['question_answers']);
        $this->assertGreaterThan(0, count($seoData['question_answers']));

        $firstQA = $seoData['question_answers'][0];
        $this->assertArrayHasKey('question', $firstQA);
        $this->assertArrayHasKey('answer', $firstQA);
        $this->assertStringContainsString('15%', $firstQA['answer']);
    }

    #[Test]
    public function it_calculates_trust_score_correctly()
    {
        // High trust product (Grade A, low fake percentage)
        $highTrustProduct = AsinData::factory()->create([
            'grade'           => 'A',
            'fake_percentage' => 5,
        ]);

        $seoData = $this->seoService->generateProductSEOData($highTrustProduct);
        $this->assertGreaterThan(85, $seoData['trust_score']);

        // Low trust product (Grade F, high fake percentage)
        $lowTrustProduct = AsinData::factory()->create([
            'grade'           => 'F',
            'fake_percentage' => 80,
        ]);

        $seoData = $this->seoService->generateProductSEOData($lowTrustProduct);
        $this->assertLessThan(20, $seoData['trust_score']);
    }

    #[Test]
    public function it_calculates_confidence_score_based_on_review_count()
    {
        // Product with many reviews
        $manyReviewsProduct = AsinData::factory()->create([
            'reviews'           => json_encode(array_fill(0, 150, ['text' => 'Review', 'rating' => 4])),
            'have_product_data' => true,
        ]);

        $seoData = $this->seoService->generateProductSEOData($manyReviewsProduct);
        $this->assertGreaterThan(90, $seoData['confidence_score']);

        // Product with few reviews
        $fewReviewsProduct = AsinData::factory()->create([
            'reviews'           => json_encode([['text' => 'Single review', 'rating' => 4]]),
            'have_product_data' => false,
        ]);

        $seoData = $this->seoService->generateProductSEOData($fewReviewsProduct);
        $this->assertLessThan(80, $seoData['confidence_score']);
    }

    #[Test]
    public function it_generates_valid_product_schema()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0987654321',
            'product_title'     => 'Schema Test Product',
            'product_image_url' => 'https://example.com/product.jpg',
            'adjusted_rating'   => 4.3,
            'fake_percentage'   => 20,
            'grade'             => 'B',
            'reviews'           => json_encode([
                ['text' => 'Good', 'rating' => 4],
                ['text' => 'Great', 'rating' => 5],
            ]),
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $schema = $seoData['product_schema'];

        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Product', $schema['@type']);
        $this->assertEquals('Schema Test Product', $schema['name']);
        $this->assertEquals('B0987654321', $schema['sku']);
        $this->assertEquals('https://example.com/product.jpg', $schema['image']);

        // Test aggregate rating
        $this->assertArrayHasKey('aggregateRating', $schema);
        $this->assertEquals(4.3, $schema['aggregateRating']['ratingValue']);
        $this->assertEquals(2, $schema['aggregateRating']['ratingCount']);

        // Test additional properties
        $this->assertArrayHasKey('additionalProperty', $schema);
        $this->assertCount(3, $schema['additionalProperty']);
    }

    #[Test]
    public function it_generates_valid_faq_schema()
    {
        $asinData = AsinData::factory()->create([
            'fake_percentage' => 35,
            'grade'           => 'D',
            'adjusted_rating' => 3.2,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $faqSchema = $seoData['faq_schema'];

        $this->assertEquals('https://schema.org', $faqSchema['@context']);
        $this->assertEquals('FAQPage', $faqSchema['@type']);
        $this->assertArrayHasKey('mainEntity', $faqSchema);
        $this->assertIsArray($faqSchema['mainEntity']);
        $this->assertGreaterThan(0, count($faqSchema['mainEntity']));

        $firstQuestion = $faqSchema['mainEntity'][0];
        $this->assertEquals('Question', $firstQuestion['@type']);
        $this->assertArrayHasKey('name', $firstQuestion);
        $this->assertArrayHasKey('acceptedAnswer', $firstQuestion);
        $this->assertEquals('Answer', $firstQuestion['acceptedAnswer']['@type']);
    }

    #[Test]
    public function it_generates_valid_dataset_schema()
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0DATASET123',
            'country'         => 'ca',
            'product_title'   => 'Dataset Test Product',
            'fake_percentage' => 42,
            'grade'           => 'C',
            'adjusted_rating' => 3.8,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $datasetSchema = $seoData['dataset_schema'];

        $this->assertEquals('https://schema.org', $datasetSchema['@context']);
        $this->assertEquals('Dataset', $datasetSchema['@type']);
        $this->assertStringContainsString('Dataset Test Product', $datasetSchema['description']);
        $this->assertEquals('CA', $datasetSchema['spatialCoverage']);

        // Test variable measurements
        $this->assertArrayHasKey('variableMeasured', $datasetSchema);
        $this->assertIsArray($datasetSchema['variableMeasured']);
        $this->assertCount(3, $datasetSchema['variableMeasured']);

        $fakePercentageVar = $datasetSchema['variableMeasured'][0];
        $this->assertEquals('fake_review_percentage', $fakePercentageVar['name']);
        $this->assertEquals(42, $fakePercentageVar['value']);
        $this->assertEquals('percent', $fakePercentageVar['unitText']);
    }

    #[Test]
    public function it_generates_home_seo_data()
    {
        $homeSeoData = $this->seoService->generateHomeSEOData();

        $this->assertArrayHasKey('meta_title', $homeSeoData);
        $this->assertArrayHasKey('meta_description', $homeSeoData);
        $this->assertArrayHasKey('keywords', $homeSeoData);
        $this->assertArrayHasKey('structured_data', $homeSeoData);
        $this->assertArrayHasKey('ai_summary', $homeSeoData);
        $this->assertArrayHasKey('question_answers', $homeSeoData);

        // Test structured data for home page
        $structuredData = $homeSeoData['structured_data'];
        $this->assertEquals('https://schema.org', $structuredData['@context']);
        $this->assertEquals('WebApplication', $structuredData['@type']);
        $this->assertStringContainsString('SMART SHIELD UI', $structuredData['name']);

        // Test question answers
        $this->assertIsArray($homeSeoData['question_answers']);
        $this->assertGreaterThan(0, count($homeSeoData['question_answers']));
    }

    #[Test]
    public function it_handles_missing_product_data_gracefully()
    {
        $asinData = AsinData::factory()->create([
            'product_title'     => null,
            'product_image_url' => null,
            'fake_percentage'   => null,
            'grade'             => null,
            'adjusted_rating'   => null,
            'reviews'           => null,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Should still generate valid SEO data with defaults
        $this->assertIsString($seoData['meta_title']);
        $this->assertIsString($seoData['meta_description']);
        $this->assertIsArray($seoData['question_answers']);
        $this->assertIsInt($seoData['trust_score']);
        $this->assertIsInt($seoData['confidence_score']);

        // Product schema should handle missing data
        $schema = $seoData['product_schema'];
        $this->assertEquals('Amazon Product', $schema['name']);
        $this->assertNull($schema['image']);
    }

    #[Test]
    public function it_generates_ai_keywords_array()
    {
        $asinData = AsinData::factory()->create();
        $seoData = $this->seoService->generateProductSEOData($asinData);

        $this->assertIsArray($seoData['ai_keywords']);
        $this->assertContains('artificial intelligence review analysis', $seoData['ai_keywords']);
        $this->assertContains('machine learning fake detection', $seoData['ai_keywords']);
        $this->assertContains('natural language processing reviews', $seoData['ai_keywords']);
    }

    #[Test]
    public function it_includes_analysis_methodology()
    {
        $asinData = AsinData::factory()->create();
        $seoData = $this->seoService->generateProductSEOData($asinData);

        $methodology = $seoData['analysis_methodology'];
        $this->assertIsArray($methodology);
        $this->assertArrayHasKey('techniques', $methodology);
        $this->assertArrayHasKey('data_sources', $methodology);
        $this->assertArrayHasKey('accuracy', $methodology);

        $this->assertContains('Natural Language Processing', $methodology['techniques']);
        $this->assertContains('Machine Learning Classification', $methodology['techniques']);
        $this->assertContains('Amazon product reviews', $methodology['data_sources']);
    }

    #[Test]
    public function it_includes_price_analysis_in_product_schema()
    {
        $asinData = AsinData::factory()->withPriceAnalysis()->create([
            'price'    => 29.99,
            'currency' => 'USD',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $productSchema = $seoData['product_schema'];

        // Check price/offers is included
        $this->assertArrayHasKey('offers', $productSchema);
        $this->assertEquals(29.99, $productSchema['offers']['price']);
        $this->assertEquals('USD', $productSchema['offers']['priceCurrency']);

        // Check price analysis properties are included
        $additionalProperties = $productSchema['additionalProperty'];
        $propertyNames = array_column($additionalProperties, 'name');

        $this->assertContains('Estimated MSRP', $propertyNames);
        $this->assertContains('Price Assessment', $propertyNames);
        $this->assertContains('Price Positioning', $propertyNames);
    }

    #[Test]
    public function it_includes_price_qa_when_price_analysis_available()
    {
        $asinData = AsinData::factory()->withPriceAnalysis()->create();

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $questions = $seoData['question_answers'];

        // Get all questions
        $questionTexts = array_column($questions, 'question');

        // Should include price-related questions
        $this->assertContains('Is this product priced fairly on Amazon?', $questionTexts);
        $this->assertContains('How is this product positioned in the market?', $questionTexts);
    }

    #[Test]
    public function it_excludes_price_data_when_not_analyzed()
    {
        $asinData = AsinData::factory()->create([
            'price_analysis_status' => 'pending',
            'price_analysis'        => null,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $productSchema = $seoData['product_schema'];

        // Check offers is NOT included without price
        $this->assertArrayNotHasKey('offers', $productSchema);

        // Check price analysis properties are NOT included
        $additionalProperties = $productSchema['additionalProperty'];
        $propertyNames = array_column($additionalProperties, 'name');

        $this->assertNotContains('Estimated MSRP', $propertyNames);
        $this->assertNotContains('Price Assessment', $propertyNames);
    }
}

