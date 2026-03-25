<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\SEOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StructuredDataValidationTest extends TestCase
{
    use RefreshDatabase;

    private SEOService $seoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seoService = new SEOService();
    }

    #[Test]
    public function product_schema_follows_schema_org_specification()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0SCHEMA123',
            'product_title'     => 'Schema Validation Product',
            'product_image_url' => 'https://example.com/product.jpg',
            'adjusted_rating'   => 4.2,
            'fake_percentage'   => 20,
            'grade'             => 'B',
            'reviews'           => json_encode([
                ['text' => 'Good product', 'rating' => 4],
                ['text' => 'Great quality', 'rating' => 5],
            ]),
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $schema = $seoData['product_schema'];

        // Test required Product schema properties
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Product', $schema['@type']);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertArrayHasKey('image', $schema);
        $this->assertArrayHasKey('sku', $schema);
        $this->assertArrayHasKey('brand', $schema);

        // Test Brand schema
        $this->assertEquals('Brand', $schema['brand']['@type']);
        $this->assertArrayHasKey('name', $schema['brand']);

        // Test AggregateRating schema
        $this->assertArrayHasKey('aggregateRating', $schema);
        $aggregateRating = $schema['aggregateRating'];
        $this->assertEquals('AggregateRating', $aggregateRating['@type']);
        $this->assertArrayHasKey('ratingValue', $aggregateRating);
        $this->assertArrayHasKey('bestRating', $aggregateRating);
        $this->assertArrayHasKey('worstRating', $aggregateRating);
        $this->assertArrayHasKey('ratingCount', $aggregateRating);

        // Test PropertyValue schemas
        $this->assertArrayHasKey('additionalProperty', $schema);
        $this->assertIsArray($schema['additionalProperty']);

        foreach ($schema['additionalProperty'] as $property) {
            $this->assertEquals('PropertyValue', $property['@type']);
            $this->assertArrayHasKey('name', $property);
            $this->assertArrayHasKey('value', $property);
        }
    }

    #[Test]
    public function analysis_schema_follows_news_article_specification()
    {
        $asinData = AsinData::factory()->create([
            'product_title' => 'Analysis Schema Test',
            'explanation'   => 'Detailed analysis explanation',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $schema = $seoData['analysis_schema'];

        // Test AnalysisNewsArticle schema
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('AnalysisNewsArticle', $schema['@type']);
        $this->assertArrayHasKey('headline', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertArrayHasKey('author', $schema);
        $this->assertArrayHasKey('publisher', $schema);
        $this->assertArrayHasKey('datePublished', $schema);
        $this->assertArrayHasKey('dateModified', $schema);

        // Test Organization schemas
        $this->assertEquals('Organization', $schema['author']['@type']);
        $this->assertEquals('Organization', $schema['publisher']['@type']);

        // Test ImageObject schema in publisher
        $this->assertArrayHasKey('logo', $schema['publisher']);
        $this->assertEquals('ImageObject', $schema['publisher']['logo']['@type']);

        // Test mentions array
        $this->assertArrayHasKey('mentions', $schema);
        $this->assertIsArray($schema['mentions']);

        foreach ($schema['mentions'] as $mention) {
            $this->assertEquals('Thing', $mention['@type']);
            $this->assertArrayHasKey('name', $mention);
        }
    }

    #[Test]
    public function dataset_schema_follows_specification()
    {
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0DATASET123',
            'country'         => 'ca',
            'product_title'   => 'Dataset Schema Test',
            'fake_percentage' => 35,
            'grade'           => 'C',
            'adjusted_rating' => 3.5,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $schema = $seoData['dataset_schema'];

        // Test Dataset schema
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('Dataset', $schema['@type']);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertArrayHasKey('creator', $schema);
        $this->assertArrayHasKey('temporalCoverage', $schema);
        $this->assertArrayHasKey('spatialCoverage', $schema);
        $this->assertArrayHasKey('variableMeasured', $schema);
        $this->assertArrayHasKey('license', $schema);

        // Test creator Organization
        $this->assertEquals('Organization', $schema['creator']['@type']);

        // Test variableMeasured array
        $this->assertIsArray($schema['variableMeasured']);
        $this->assertGreaterThan(0, count($schema['variableMeasured']));

        foreach ($schema['variableMeasured'] as $variable) {
            $this->assertEquals('PropertyValue', $variable['@type']);
            $this->assertArrayHasKey('name', $variable);
            $this->assertArrayHasKey('value', $variable);
            $this->assertArrayHasKey('description', $variable);
        }

        // Test spatial coverage format
        $this->assertEquals('CA', $schema['spatialCoverage']);
    }

    #[Test]
    public function faq_schema_follows_specification()
    {
        $asinData = AsinData::factory()->create([
            'fake_percentage' => 25,
            'grade'           => 'B',
            'adjusted_rating' => 4.1,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $schema = $seoData['faq_schema'];

        // Test FAQPage schema
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('FAQPage', $schema['@type']);
        $this->assertArrayHasKey('mainEntity', $schema);
        $this->assertIsArray($schema['mainEntity']);
        $this->assertGreaterThan(0, count($schema['mainEntity']));

        // Test Question schemas
        foreach ($schema['mainEntity'] as $question) {
            $this->assertEquals('Question', $question['@type']);
            $this->assertArrayHasKey('name', $question);
            $this->assertArrayHasKey('acceptedAnswer', $question);

            // Test Answer schema
            $answer = $question['acceptedAnswer'];
            $this->assertEquals('Answer', $answer['@type']);
            $this->assertArrayHasKey('text', $answer);
        }
    }

    #[Test]
    public function how_to_schema_follows_specification()
    {
        $asinData = AsinData::factory()->create();

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $schema = $seoData['how_to_schema'];

        // Test HowTo schema
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('HowTo', $schema['@type']);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertArrayHasKey('step', $schema);
        $this->assertArrayHasKey('totalTime', $schema);
        $this->assertArrayHasKey('tool', $schema);

        // Test HowToStep schemas
        $this->assertIsArray($schema['step']);
        $this->assertGreaterThan(0, count($schema['step']));

        foreach ($schema['step'] as $step) {
            $this->assertEquals('HowToStep', $step['@type']);
            $this->assertArrayHasKey('name', $step);
            $this->assertArrayHasKey('text', $step);
        }

        // Test HowToTool schemas
        $this->assertIsArray($schema['tool']);
        foreach ($schema['tool'] as $tool) {
            $this->assertEquals('HowToTool', $tool['@type']);
            $this->assertArrayHasKey('name', $tool);
        }

        // Test totalTime format (ISO 8601 duration)
        $this->assertMatchesRegularExpression('/^PT\d+[MH]$/', $schema['totalTime']);
    }

    #[Test]
    public function home_page_web_application_schema_is_valid()
    {
        $seoData = $this->seoService->generateHomeSEOData();
        $schema = $seoData['structured_data'];

        // Test WebApplication schema
        $this->assertEquals('https://schema.org', $schema['@context']);
        $this->assertEquals('WebApplication', $schema['@type']);
        $this->assertArrayHasKey('name', $schema);
        $this->assertArrayHasKey('description', $schema);
        $this->assertArrayHasKey('url', $schema);
        $this->assertArrayHasKey('applicationCategory', $schema);
        $this->assertArrayHasKey('operatingSystem', $schema);
        $this->assertArrayHasKey('offers', $schema);
        $this->assertArrayHasKey('featureList', $schema);
        $this->assertArrayHasKey('creator', $schema);

        // Test Offer schema
        $offer = $schema['offers'];
        $this->assertEquals('Offer', $offer['@type']);
        $this->assertArrayHasKey('price', $offer);
        $this->assertArrayHasKey('priceCurrency', $offer);

        // Test feature list
        $this->assertIsArray($schema['featureList']);
        $this->assertGreaterThan(0, count($schema['featureList']));

        // Test creator Organization
        $this->assertEquals('Organization', $schema['creator']['@type']);
    }

    #[Test]
    public function all_schemas_have_valid_iso_dates()
    {
        $asinData = AsinData::factory()->create([
            'updated_at' => now()->subDays(5),
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        $schemasWithDates = [
            'analysis_schema' => ['datePublished', 'dateModified'],
            'dataset_schema'  => ['temporalCoverage'],
        ];

        foreach ($schemasWithDates as $schemaKey => $dateFields) {
            $schema = $seoData[$schemaKey];

            foreach ($dateFields as $dateField) {
                if (isset($schema[$dateField])) {
                    $dateValue = $schema[$dateField];

                    // Test ISO 8601 format (allowing microseconds)
                    $this->assertMatchesRegularExpression(
                        '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{3,6})?Z$/',
                        $dateValue,
                        "Date field {$dateField} in {$schemaKey} should be in ISO 8601 format"
                    );

                    // Test that date can be parsed (try multiple formats)
                    $parsedDate = \DateTime::createFromFormat(\DateTime::ATOM, $dateValue);
                    if ($parsedDate === false) {
                        // Try with microseconds format
                        $parsedDate = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $dateValue);
                    }
                    $this->assertNotFalse($parsedDate, "Date field {$dateField} should be parseable");
                }
            }
        }
    }

    #[Test]
    public function schemas_handle_null_values_gracefully()
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

        // All schemas should still be valid arrays
        $schemaKeys = ['product_schema', 'analysis_schema', 'dataset_schema', 'faq_schema', 'how_to_schema'];

        foreach ($schemaKeys as $schemaKey) {
            $this->assertIsArray($seoData[$schemaKey]);
            $this->assertArrayHasKey('@context', $seoData[$schemaKey]);
            $this->assertArrayHasKey('@type', $seoData[$schemaKey]);
        }

        // Product schema should handle null image
        $productSchema = $seoData['product_schema'];
        $this->assertNull($productSchema['image']);
        $this->assertEquals('Amazon Product', $productSchema['name']);
    }

    #[Test]
    public function property_values_have_correct_data_types()
    {
        $asinData = AsinData::factory()->create([
            'fake_percentage' => 42,
            'adjusted_rating' => 3.7,
            'reviews'         => json_encode([
                ['text' => 'Review 1', 'rating' => 4],
                ['text' => 'Review 2', 'rating' => 5],
            ]),
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Test numeric values in product schema
        $productSchema = $seoData['product_schema'];
        $this->assertIsFloat($productSchema['aggregateRating']['ratingValue']);
        $this->assertIsInt($productSchema['aggregateRating']['ratingCount']);

        // Test numeric values in dataset schema
        $datasetSchema = $seoData['dataset_schema'];
        foreach ($datasetSchema['variableMeasured'] as $variable) {
            if ($variable['name'] === 'fake_review_percentage') {
                $this->assertIsInt($variable['value']);
            } elseif ($variable['name'] === 'adjusted_rating') {
                $this->assertIsFloat($variable['value']);
            } elseif ($variable['name'] === 'reviews_analyzed') {
                $this->assertIsInt($variable['value']);
            }
        }
    }

    #[Test]
    public function urls_are_properly_formatted()
    {
        $asinData = AsinData::factory()->create([
            'asin'          => 'B0URLTEST123',
            'country'       => 'de',
            'product_title' => 'Test Product Title For URL',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Test canonical URL format - should use SEO-friendly /amazon/{country}/{asin}/{slug} format
        $this->assertStringStartsWith('/amazon/de/B0URLTEST123/', $seoData['canonical_url']);
        $this->assertStringContainsString('test-product-title', $seoData['canonical_url']);

        // Test URLs in schemas
        $analysisSchema = $seoData['analysis_schema'];
        $this->assertStringStartsWith('http', $analysisSchema['author']['url']);
        $this->assertStringStartsWith('http', $analysisSchema['publisher']['logo']['url']);
    }

    #[Test]
    public function canonical_url_without_product_title_uses_fallback()
    {
        $asinData = AsinData::factory()->create([
            'asin'          => 'B0FALLBACK99',
            'country'       => 'ca',
            'product_title' => null,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Without product title, should use simple /amazon/{country}/{asin} format
        $this->assertEquals('/amazon/ca/B0FALLBACK99', $seoData['canonical_url']);
    }

    #[Test]
    public function license_urls_are_valid()
    {
        $asinData = AsinData::factory()->create();
        $seoData = $this->seoService->generateProductSEOData($asinData);

        $datasetSchema = $seoData['dataset_schema'];
        $this->assertEquals('https://creativecommons.org/licenses/by/4.0/', $datasetSchema['license']);

        // Verify it's a valid URL format
        $this->assertMatchesRegularExpression('/^https:\/\//', $datasetSchema['license']);
    }
}
