<?php

namespace Tests\Feature;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AmazonAffiliateLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The required Amazon Associates disclosure text.
     */
    private const AFFILIATE_DISCLOSURE = 'As an Amazon Associate I earn from qualifying purchases.';

    protected function setUp(): void
    {
        parent::setUp();

        // Reset affiliate config to clean state before each test
        config([
            'amazon.affiliate.enabled' => false,
            'amazon.affiliate.tags.us' => null,
            'amazon.affiliate.tags.ca' => null,
            'amazon.affiliate.tags.de' => null,
            'app.amazon_affiliate_tag' => null,
        ]);
    }

    // =========================================================================
    // AFFILIATE URL TESTS
    // =========================================================================

    #[Test]
    public function amazon_urls_include_affiliate_tag_when_configured()
    {
        // Enable affiliate links and set US tag
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-20',
        ]);

        $asinData = AsinData::factory()->create([
            'asin'              => 'B08N5WRWNW',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}/{$asinData->slug}");

        $response->assertStatus(200);
        $response->assertSee('https://www.amazon.com/dp/B08N5WRWNW?tag=nullfake-20');
    }

    #[Test]
    public function amazon_urls_work_without_affiliate_tag()
    {
        // Affiliate disabled
        config(['amazon.affiliate.enabled' => false]);

        $asinData = AsinData::factory()->create([
            'asin'              => 'B08N5WRWNW',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}/{$asinData->slug}");

        $response->assertStatus(200);
        $response->assertSee('https://www.amazon.com/dp/B08N5WRWNW');
        $response->assertDontSee('?tag=');
    }

    #[Test]
    public function product_not_found_page_includes_affiliate_tag_when_configured()
    {
        // Enable affiliate with US tag
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-20',
        ]);

        // Visit non-existent product (defaults to US)
        $response = $this->get('/amazon/B08N5NONEX');

        $response->assertStatus(200);
        $response->assertSee('https://www.amazon.com/dp/B08N5NONEX?tag=nullfake-20');
    }

    #[Test]
    public function country_specific_affiliate_tags_are_used()
    {
        // Enable affiliate with different tags for each country
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-us-20',
            'amazon.affiliate.tags.ca' => 'nullfake-ca-20',
        ]);

        // Create US product
        $usProduct = AsinData::factory()->create([
            'asin'              => 'B08N5USAAA',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'US Product',
        ]);

        // Create CA product
        $caProduct = AsinData::factory()->create([
            'asin'              => 'B08N5CAAAA',
            'country'           => 'ca',
            'have_product_data' => true,
            'product_title'     => 'CA Product',
        ]);

        // Verify US product uses US tag
        $usResponse = $this->get("/amazon/us/{$usProduct->asin}/{$usProduct->slug}");
        $usResponse->assertStatus(200);
        $usResponse->assertSee('https://www.amazon.com/dp/B08N5USAAA?tag=nullfake-us-20');

        // Verify CA product uses CA tag
        $caResponse = $this->get("/amazon/ca/{$caProduct->asin}/{$caProduct->slug}");
        $caResponse->assertStatus(200);
        $caResponse->assertSee('https://www.amazon.ca/dp/B08N5CAAAA?tag=nullfake-ca-20');
    }

    #[Test]
    public function no_affiliate_tag_for_countries_without_configured_tag()
    {
        // Enable affiliate but only configure US tag
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-20',
            'amazon.affiliate.tags.de' => null, // No DE tag
        ]);

        $deProduct = AsinData::factory()->create([
            'asin'              => 'B08N5DEAAA',
            'country'           => 'de',
            'have_product_data' => true,
            'product_title'     => 'German Product',
        ]);

        $response = $this->get("/amazon/de/{$deProduct->asin}/{$deProduct->slug}");

        $response->assertStatus(200);
        // Should have Amazon.de link but NO affiliate tag
        $response->assertSee('https://www.amazon.de/dp/B08N5DEAAA');
        $response->assertDontSee('https://www.amazon.de/dp/B08N5DEAAA?tag=');
    }

    // =========================================================================
    // AFFILIATE DISCLOSURE TESTS
    // =========================================================================

    #[Test]
    public function affiliate_disclosure_appears_when_affiliate_enabled_and_tag_configured()
    {
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-20',
        ]);

        $asinData = AsinData::factory()->create([
            'asin'              => 'B08N5DISCL',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        $response = $this->get("/amazon/us/{$asinData->asin}/{$asinData->slug}");

        $response->assertStatus(200);
        $response->assertSee(self::AFFILIATE_DISCLOSURE);
    }

    #[Test]
    public function affiliate_disclosure_does_not_appear_when_affiliate_disabled()
    {
        config([
            'amazon.affiliate.enabled' => false,
            'amazon.affiliate.tags.us' => 'nullfake-20', // Tag configured but disabled
        ]);

        $asinData = AsinData::factory()->create([
            'asin'              => 'B08N5NODIS',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        $response = $this->get("/amazon/us/{$asinData->asin}/{$asinData->slug}");

        $response->assertStatus(200);
        $response->assertDontSee(self::AFFILIATE_DISCLOSURE);
    }

    #[Test]
    public function affiliate_disclosure_does_not_appear_when_no_tag_for_country()
    {
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-20',
            'amazon.affiliate.tags.de' => null, // No DE tag
        ]);

        $deProduct = AsinData::factory()->create([
            'asin'              => 'B08N5DENOD',
            'country'           => 'de',
            'have_product_data' => true,
            'product_title'     => 'German Product',
        ]);

        $response = $this->get("/amazon/de/{$deProduct->asin}/{$deProduct->slug}");

        $response->assertStatus(200);
        $response->assertDontSee(self::AFFILIATE_DISCLOSURE);
    }

    #[Test]
    public function affiliate_disclosure_appears_on_product_not_found_page_when_configured()
    {
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-20',
        ]);

        // Non-existent product defaults to US
        $response = $this->get('/amazon/B08N5NOTFN');

        $response->assertStatus(200);
        $response->assertSee(self::AFFILIATE_DISCLOSURE);
    }

    #[Test]
    public function affiliate_disclosure_does_not_appear_on_product_not_found_when_disabled()
    {
        config([
            'amazon.affiliate.enabled' => false,
        ]);

        $response = $this->get('/amazon/B08N5NOTFN');

        $response->assertStatus(200);
        $response->assertDontSee(self::AFFILIATE_DISCLOSURE);
    }

    // =========================================================================
    // LEGACY CONFIG FALLBACK TESTS
    // =========================================================================

    #[Test]
    public function legacy_app_amazon_affiliate_tag_config_works_for_us()
    {
        // Use legacy config (app.amazon_affiliate_tag) instead of new config
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => null, // No new config
            'app.amazon_affiliate_tag' => 'legacy-tag-20', // Legacy config
        ]);

        $asinData = AsinData::factory()->create([
            'asin'              => 'B08N5LEGCY',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        $response = $this->get("/amazon/us/{$asinData->asin}/{$asinData->slug}");

        $response->assertStatus(200);
        $response->assertSee('https://www.amazon.com/dp/B08N5LEGCY?tag=legacy-tag-20');
        $response->assertSee(self::AFFILIATE_DISCLOSURE);
    }

    #[Test]
    public function legacy_config_only_works_for_us_not_other_countries()
    {
        // Legacy config should NOT be used for non-US countries
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.ca' => null,
            'app.amazon_affiliate_tag' => 'legacy-tag-20',
        ]);

        $caProduct = AsinData::factory()->create([
            'asin'              => 'B08N5CALGY',
            'country'           => 'ca',
            'have_product_data' => true,
            'product_title'     => 'CA Product',
        ]);

        $response = $this->get("/amazon/ca/{$caProduct->asin}/{$caProduct->slug}");

        $response->assertStatus(200);
        // Should NOT have affiliate tag since legacy only works for US
        $response->assertSee('https://www.amazon.ca/dp/B08N5CALGY');
        $response->assertDontSee('?tag=');
        $response->assertDontSee(self::AFFILIATE_DISCLOSURE);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    #[Test]
    public function new_config_takes_precedence_over_legacy()
    {
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'new-tag-20',
            'app.amazon_affiliate_tag' => 'legacy-tag-20',
        ]);

        $asinData = AsinData::factory()->create([
            'asin'              => 'B08N5PRECE',
            'country'           => 'us',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        $response = $this->get("/amazon/us/{$asinData->asin}/{$asinData->slug}");

        $response->assertStatus(200);
        // New config should take precedence
        $response->assertSee('https://www.amazon.com/dp/B08N5PRECE?tag=new-tag-20');
        $response->assertDontSee('legacy-tag-20');
    }

    #[Test]
    public function affiliate_disclosure_on_processing_product_page()
    {
        config([
            'amazon.affiliate.enabled' => true,
            'amazon.affiliate.tags.us' => 'nullfake-20',
        ]);

        // Create a product that's still processing (not fully analyzed)
        $asinData = AsinData::factory()->create([
            'asin'              => 'B08N5PROCG',
            'country'           => 'us',
            'status'            => 'processing',
            'have_product_data' => true,
            'product_title'     => 'Processing Product',
            'fake_percentage'   => null,
            'grade'             => null,
        ]);

        // Visit the processing product page (should show product-not-found)
        $response = $this->get("/amazon/us/{$asinData->asin}");

        $response->assertStatus(200);
        // Should still show affiliate tag and disclosure
        $response->assertSee('https://www.amazon.com/dp/B08N5PROCG?tag=nullfake-20');
        $response->assertSee(self::AFFILIATE_DISCLOSURE);
    }
}

