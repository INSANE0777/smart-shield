<?php

namespace Tests\Feature;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests to verify AdSense code only appears on product pages with editorial content.
 * This is critical for AdSense compliance - pages without editorial content
 * should not load the AdSense script.
 */
class ProductPageAdsenseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function product_page_without_editorial_content_does_not_include_adsense(): void
    {
        // Create a completed product WITHOUT editorial content
        $product = AsinData::factory()->create([
            'asin'              => 'B00TEST001',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product Without Editorial',
            'grade'             => 'B',
            'fake_percentage'   => 15.0,
            'amazon_rating'     => 4.5,
            'adjusted_rating'   => 4.2,
            'editorial_status'  => 'pending',
            'editorial_content' => null,
        ]);

        $this->assertFalse($product->hasEditorialContent());

        // Follow redirects to SEO-friendly URL
        $response = $this->followingRedirects()->get("/amazon/{$product->country}/{$product->asin}");

        $response->assertStatus(200);
        $response->assertDontSee('adsbygoogle.js', false);
        $response->assertDontSee('pagead2.googlesyndication.com', false);
    }

    #[Test]
    public function product_page_with_editorial_content_includes_adsense_when_enabled(): void
    {
        // Enable AdSense in config for this test
        config(['google.adsense.enabled' => true]);
        config(['google.adsense.publisher_id' => 'ca-pub-1234567890']);

        // Create a completed product WITH editorial content
        $product = AsinData::factory()->create([
            'asin'              => 'B00TEST002',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product With Editorial',
            'grade'             => 'A',
            'fake_percentage'   => 5.0,
            'amazon_rating'     => 4.8,
            'adjusted_rating'   => 4.7,
            'editorial_status'  => 'completed',
            'editorial_content' => [
                'buyers_guide' => [
                    'headline'           => 'Test Editorial Headline',
                    'introduction'       => 'Test introduction content.',
                    'key_considerations' => ['Point 1', 'Point 2', 'Point 3'],
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
            ],
            'editorial_generated_at' => now(),
        ]);

        $this->assertTrue($product->hasEditorialContent());

        // Follow redirects to SEO-friendly URL
        $response = $this->followingRedirects()->get("/amazon/{$product->country}/{$product->asin}");

        $response->assertStatus(200);
        // Should include AdSense script when editorial content exists and AdSense is enabled
        $response->assertSee('adsbygoogle.js', false);
        $response->assertSee('ca-pub-1234567890', false);
    }

    #[Test]
    public function product_page_with_editorial_but_adsense_disabled_does_not_include_adsense(): void
    {
        // Disable AdSense in config
        config(['google.adsense.enabled' => false]);

        // Create a completed product WITH editorial content
        $product = AsinData::factory()->create([
            'asin'                   => 'B00TEST003',
            'country'                => 'us',
            'status'                 => 'completed',
            'have_product_data'      => true,
            'product_title'          => 'Test Product With Editorial But Disabled',
            'grade'                  => 'A',
            'fake_percentage'        => 5.0,
            'editorial_status'       => 'completed',
            'editorial_content'      => ['buyers_guide' => ['headline' => 'Test']],
            'editorial_generated_at' => now(),
        ]);

        // Follow redirects to SEO-friendly URL
        $response = $this->followingRedirects()->get("/amazon/{$product->country}/{$product->asin}");

        $response->assertStatus(200);
        // Should NOT include AdSense even with editorial content when disabled
        $response->assertDontSee('adsbygoogle.js', false);
    }

    #[Test]
    public function product_not_found_page_does_not_include_adsense(): void
    {
        // Request a non-existent product - returns 404 with custom view
        $response = $this->get('/amazon/us/B00NONEXISTENT');

        // The response may be 404 or 200 depending on config, but should never have AdSense
        $response->assertDontSee('adsbygoogle.js', false);
        $response->assertDontSee('pagead2.googlesyndication.com', false);
    }

    #[Test]
    public function product_pending_analysis_does_not_include_adsense(): void
    {
        // Create a product that exists but hasn't been analyzed yet
        $product = AsinData::factory()->create([
            'asin'              => 'B00TEST004',
            'country'           => 'us',
            'status'            => 'pending',
            'have_product_data' => true,
            'product_title'     => 'Test Product Pending',
            'grade'             => null,
            'fake_percentage'   => null,
        ]);

        $response = $this->get("/amazon/{$product->country}/{$product->asin}");

        // Should show "not found" or "processing" page without AdSense
        $response->assertDontSee('adsbygoogle.js', false);
    }

    #[Test]
    public function editorial_section_hidden_when_no_editorial_content(): void
    {
        // Product WITHOUT editorial content
        $product = AsinData::factory()->create([
            'asin'              => 'B00TEST005',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Product Without Editorial',
            'grade'             => 'B',
            'fake_percentage'   => 15.0,
            'editorial_status'  => 'pending',
            'editorial_content' => null,
        ]);

        $this->assertFalse($product->hasEditorialContent());

        // Follow redirects to SEO-friendly URL
        $response = $this->followingRedirects()->get("/amazon/{$product->country}/{$product->asin}");
        $response->assertStatus(200);

        // The editors-analysis section ID should not be present
        $response->assertDontSee('id="editors-analysis"', false);
    }

    #[Test]
    public function editorial_section_shown_when_editorial_content_exists(): void
    {
        // Product WITH editorial content
        $product = AsinData::factory()->create([
            'asin'              => 'B00TEST006',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Product With Editorial',
            'grade'             => 'A',
            'fake_percentage'   => 5.0,
            'editorial_status'  => 'completed',
            'editorial_content' => [
                'buyers_guide' => ['headline' => 'Test Headline', 'introduction' => 'Test intro.'],
            ],
            'editorial_generated_at' => now(),
        ]);

        $this->assertTrue($product->hasEditorialContent());

        // Follow redirects to SEO-friendly URL
        $response = $this->followingRedirects()->get("/amazon/{$product->country}/{$product->asin}");
        $response->assertStatus(200);

        // The editors-analysis section should be present
        $response->assertSee('id="editors-analysis"', false);
        $response->assertSee('Test Headline', false);
    }
}
