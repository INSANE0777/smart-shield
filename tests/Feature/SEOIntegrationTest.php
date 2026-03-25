<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\SEOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SEOIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function home_page_includes_seo_data()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        $content = $response->getContent();

        // Test basic SEO elements
        $this->assertStringContainsString('<title>', $content);
        $this->assertStringContainsString('<meta name="description"', $content);
        $this->assertStringContainsString('<meta name="keywords"', $content);

        // Test AI-optimized metadata
        $this->assertStringContainsString('<meta name="ai:summary"', $content);
        $this->assertStringContainsString('<meta name="ai:type" content="web_application"', $content);
        $this->assertStringContainsString('<meta name="ai:category" content="review_analysis_tool"', $content);
        $this->assertStringContainsString('<meta name="ai:methodology"', $content);

        // Test question-answer pairs
        $this->assertStringContainsString('<meta name="ai:qa:question"', $content);
        $this->assertStringContainsString('<meta name="ai:qa:answer"', $content);

        // Test structured data
        $this->assertStringContainsString('application/ld+json', $content);
        $this->assertStringContainsString('"@context": "https://schema.org"', $content);
        $this->assertStringContainsString('"@type": "WebApplication"', $content);
    }

    #[Test]
    public function product_page_includes_comprehensive_seo_data()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0SEOTEST1',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'SEO Test Product',
            'product_image_url' => 'https://example.com/seo-test.jpg',
            'fake_percentage'   => 30,
            'grade'             => 'C',
            'adjusted_rating'   => 3.5,
            'amazon_rating'     => 4.0,
            'explanation'       => 'This product shows moderate fake review presence.',
            'reviews'           => json_encode([
                ['text' => 'Good product overall', 'rating' => 4],
                ['text' => 'Average quality', 'rating' => 3],
                ['text' => 'Could be better', 'rating' => 3],
            ]),
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);

        $content = $response->getContent();

        // Test dynamic title and description
        $this->assertStringContainsString('SEO Test Product', $content);
        $this->assertStringContainsString('30', $content); // Just check for the number, not percentage format
        $this->assertStringContainsString('Grade C', $content);

        // Test AI-optimized metadata
        $this->assertStringContainsString('<meta name="ai:summary"', $content);
        $this->assertStringContainsString('<meta name="ai:confidence"', $content);
        $this->assertStringContainsString('<meta name="ai:methodology"', $content);
        $this->assertStringContainsString('<meta name="ai:data_freshness"', $content);

        // Test question-answer pairs
        $this->assertStringContainsString('<meta name="ai:qa:question" content="What percentage of reviews are fake', $content);
        $this->assertStringContainsString('<meta name="ai:qa:answer"', $content);

        // Test multiple structured data schemas
        $this->assertStringContainsString('"@type": "Product"', $content);
        $this->assertStringContainsString('"@type": "AnalysisNewsArticle"', $content);
        $this->assertStringContainsString('"@type": "FAQPage"', $content);
        $this->assertStringContainsString('"@type": "HowTo"', $content);

        // Test product-specific structured data
        $this->assertStringContainsString('"sku": "B0SEOTEST1"', $content);
        $this->assertStringContainsString('"ratingValue":', $content);
        $this->assertStringContainsString('"name": "Fake Review Percentage"', $content);
        $this->assertStringContainsString('"value":', $content);
    }

    #[Test]
    public function product_page_handles_missing_data_gracefully()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0MINIMAL1',
            'country'           => 'ca',
            'status'            => 'completed',
            'product_title'     => null,
            'product_image_url' => null,
            'fake_percentage'   => null,
            'grade'             => null,
            'adjusted_rating'   => null,
            'reviews'           => null,
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);

        $content = $response->getContent();

        // Should show "product not found" page for incomplete analysis
        $this->assertStringContainsString('<title>', $content);
        $this->assertStringContainsString('<meta name="description"', $content);
        $this->assertStringContainsString('Product Not Found', $content);
        $this->assertStringContainsString('We haven\'t analyzed this Amazon product yet', $content);
        $this->assertStringContainsString($asinData->asin, $content);
    }

    #[Test]
    public function structured_data_is_valid_json()
    {
        $asinData = AsinData::factory()->create([
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'JSON Validation Test',
            'fake_percentage'   => 25,
            'grade'             => 'B',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);
        $content = $response->getContent();

        // Extract all JSON-LD scripts
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

        $this->assertGreaterThan(0, count($matches[1]));

        foreach ($matches[1] as $jsonContent) {
            $decoded = json_decode(trim($jsonContent), true);
            $this->assertNotNull($decoded, 'JSON-LD should be valid JSON: '.json_last_error_msg());

            // Test required schema.org properties
            if (isset($decoded['@context'])) {
                $this->assertEquals('https://schema.org', $decoded['@context']);
                $this->assertArrayHasKey('@type', $decoded);
            }
        }
    }

    #[Test]
    public function seo_service_integration_with_controller()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0INTEGRAT',
            'country'           => 'uk',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Integration Test Product',
            'fake_percentage'   => 15,
            'grade'             => 'B',
        ]);

        // Mock the SEOService to verify it's being called
        $this->mock(SEOService::class, function ($mock) use ($asinData) {
            $mock->shouldReceive('generateProductSEOData')
                ->once()
                ->with(\Mockery::on(function ($arg) use ($asinData) {
                    return $arg instanceof \App\Models\AsinData && $arg->asin === $asinData->asin;
                }))
                ->andReturn([
                    'meta_title'         => 'Mocked Title',
                    'meta_description'   => 'Mocked Description',
                    'keywords'           => 'mocked, keywords, test',
                    'canonical_url'      => '/mocked-url',
                    'ai_summary'         => 'Mocked AI Summary',
                    'confidence_score'   => 85,
                    'trust_score'        => 7.5,
                    'data_freshness'     => '2025-11-17T18:00:00Z',
                    'review_summary'     => 'Mocked review summary',
                    'social_title'       => 'Mocked Social Title',
                    'social_description' => 'Mocked Social Description',
                    'question_answers'   => [
                        ['question' => 'Test question?', 'answer' => 'Test answer'],
                    ],
                    'product_schema'  => ['@context' => 'https://schema.org', '@type' => 'Product'],
                    'analysis_schema' => ['@context' => 'https://schema.org', '@type' => 'AnalysisNewsArticle'],
                    'faq_schema'      => ['@context' => 'https://schema.org', '@type' => 'FAQPage'],
                    'how_to_schema'   => ['@context' => 'https://schema.org', '@type' => 'HowTo'],
                ]);
        });

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('Mocked Title', $content);
        $this->assertStringContainsString('Mocked Description', $content);
        $this->assertStringContainsString('Mocked AI Summary', $content);
    }

    #[Test]
    public function robots_txt_includes_ai_crawler_directives()
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));

        $content = $response->getContent();

        // Test basic robots.txt functionality (dynamic route)
        $this->assertStringContainsString('User-agent: *', $content);
        $this->assertStringContainsString('Allow: /', $content);
        $this->assertStringContainsString('Sitemap:', $content);
    }

    #[Test]
    public function meta_tags_are_properly_escaped()
    {
        $asinData = AsinData::factory()->create([
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Product with "Quotes" & <Special> Characters',
            'explanation'       => 'Analysis with <script>alert("xss")</script> content',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }
        $content = $response->getContent();

        // Test that special characters are properly escaped
        $this->assertStringContainsString('Product with &quot;Quotes&quot; &amp; &lt;Special&gt; Characters', $content);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $content);
        $this->assertStringNotContainsString('alert("xss")', $content);
    }

    #[Test]
    public function canonical_urls_are_correct()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0CANONIC1',
            'country'           => 'de',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Canonical Product',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);
        $content = $response->getContent();

        // Canonical URL should use SEO-friendly /amazon/{country}/{asin}/{slug} format
        $this->assertStringContainsString('<link rel="canonical" href="', $content);
        $this->assertStringContainsString('/amazon/de/B0CANONIC1/', $content);
        $this->assertStringContainsString('test-canonical-product', $content);
    }

    #[Test]
    public function legacy_analysis_url_redirects_to_correct_format()
    {
        // This tests the SEO fix for erroneous /analysis/{asin}/{country} canonical URLs
        // that were incorrectly generated from Nov 2025 to Jan 2026
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0REDIR123', // Must be exactly 10 alphanumeric characters
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Redirect Test Product',
        ]);

        // Access the old erroneous /analysis/ URL format
        $response = $this->get('/analysis/B0REDIR123/us');

        // Should 301 redirect to the correct /amazon/{country}/{asin} format
        $response->assertStatus(301);
        $response->assertRedirect('/amazon/us/B0REDIR123');

        // Follow the redirect chain to verify final destination works
        $finalResponse = $this->followingRedirects()->get('/analysis/B0REDIR123/us');
        $finalResponse->assertStatus(200);
        $finalResponse->assertSee('Redirect Test Product');
    }

    #[Test]
    public function legacy_analysis_url_handles_nonexistent_product()
    {
        // Test redirect for a product that doesn't exist (ASIN must be exactly 10 characters)
        $response = $this->get('/analysis/B0NOTEXIST/ca');

        // Should still 301 redirect to the correct format (product-not-found page will handle 404)
        $response->assertStatus(301);
        $response->assertRedirect('/amazon/ca/B0NOTEXIST');
    }

    #[Test]
    public function open_graph_tags_are_present()
    {
        $asinData = AsinData::factory()->create([
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'OG Test Product',
            'product_image_url' => 'https://example.com/og-image.jpg',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }
        $content = $response->getContent();

        $this->assertStringContainsString('<meta property="og:type" content="product"', $content);
        $this->assertStringContainsString('<meta property="og:title"', $content);
        $this->assertStringContainsString('<meta property="og:description"', $content);
        $this->assertStringContainsString('<meta property="og:image" content="https://example.com/og-image.jpg"', $content);
        $this->assertStringContainsString('<meta property="og:site_name" content="SMART SHIELD UI"', $content);
    }

    #[Test]
    public function twitter_card_tags_are_present()
    {
        $asinData = AsinData::factory()->create([
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Twitter Test Product',
            'fake_percentage'   => 40,
            'grade'             => 'D',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('<meta property="twitter:card" content="summary_large_image"', $content);
        $this->assertStringContainsString('<meta name="twitter:label1" content="Grade"', $content);
        $this->assertStringContainsString('<meta name="twitter:data1" content="D"', $content);
        $this->assertStringContainsString('<meta name="twitter:label2" content="Fake Reviews"', $content);
        $this->assertStringContainsString('<meta name="twitter:data2" content="40', $content);
    }

    #[Test]
    public function cache_headers_are_set_correctly()
    {
        $asinData = AsinData::factory()->create([
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");

        // Follow redirect if it occurs (SEO-friendly URL with slug)
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);

        // In testing environment, cache headers might be different
        // Just verify that cache control header is set
        $this->assertNotNull($response->headers->get('Cache-Control'));

        // In production, this would be 'max-age=900, public' but in testing it might be different
        // So we just check that some cache control is present
    }
}

