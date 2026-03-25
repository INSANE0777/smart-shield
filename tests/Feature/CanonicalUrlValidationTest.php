<?php

namespace Tests\Feature;

use App\Models\AsinData;
use App\Services\SEOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Critical SEO validation tests to prevent canonical URL mismatches.
 *
 * Background: From Nov 2025 to Jan 2026, SEOService generated canonical URLs
 * using /analysis/{asin}/{country} format, but this route only existed as an
 * API endpoint (returning JSON). Google indexed these as 404 errors, causing
 * a significant drop in search impressions (~1,000/day).
 *
 * These tests ensure:
 * 1. Canonical URLs point to actual web routes (not API routes)
 * 2. og:url and twitter:url match the canonical URL
 * 3. All SEO URLs use the correct /amazon/{country}/{asin}/{slug} format
 * 4. Legacy /analysis/ URLs redirect properly (301)
 */
class CanonicalUrlValidationTest extends TestCase
{
    use RefreshDatabase;

    private SEOService $seoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seoService = new SEOService();
    }

    // =========================================================================
    // CRITICAL: Canonical URL Format Validation
    // =========================================================================

    #[Test]
    public function canonical_url_uses_amazon_route_format_not_analysis()
    {
        // This is the CRITICAL test that would have caught the Nov 2025 bug
        $asinData = AsinData::factory()->create([
            'asin'          => 'B0CRITICAL',
            'country'       => 'us',
            'product_title' => 'Critical Test Product',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // MUST use /amazon/ format, NEVER /analysis/
        $this->assertStringStartsWith('/amazon/', $seoData['canonical_url']);
        $this->assertStringNotContainsString('/analysis/', $seoData['canonical_url']);

        // Verify the format is /amazon/{country}/{asin}/{slug}
        $this->assertMatchesRegularExpression(
            '#^/amazon/[a-z]{2}/[A-Z0-9]{10}/[a-z0-9-]+$#',
            $seoData['canonical_url'],
            'Canonical URL must match /amazon/{country}/{asin}/{slug} format'
        );
    }

    #[Test]
    public function canonical_url_matches_actual_web_route()
    {
        $asinData = AsinData::factory()->create([
            'asin'          => 'B0ROUTEVAL',
            'country'       => 'de',
            'product_title' => 'Route Validation Product',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $canonicalPath = $seoData['canonical_url'];

        // The canonical URL path should be a valid web route
        $response = $this->get($canonicalPath);

        // Should return 200 (or redirect to same URL with trailing content)
        $this->assertTrue(
            in_array($response->status(), [200, 301, 302]),
            "Canonical URL {$canonicalPath} should be a valid route, got HTTP {$response->status()}"
        );

        // If it's a redirect, follow it and verify we get 200
        if ($response->status() === 301 || $response->status() === 302) {
            $finalResponse = $this->followingRedirects()->get($canonicalPath);
            $finalResponse->assertStatus(200);
        }
    }

    #[Test]
    public function canonical_url_does_not_return_404()
    {
        // This test specifically validates that canonical URLs don't point to non-existent routes
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0NO404TST',
            'country'           => 'ca',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'No 404 Test Product',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);
        $canonicalPath = $seoData['canonical_url'];

        // Follow all redirects and verify we never get a 404
        $response = $this->followingRedirects()->get($canonicalPath);
        $this->assertNotEquals(404, $response->status(), "Canonical URL {$canonicalPath} returned 404");
    }

    // =========================================================================
    // og:url and twitter:url Consistency
    // =========================================================================

    #[Test]
    public function og_url_matches_canonical_url()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0OGMATCH1',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'OG URL Match Test',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $content = $response->getContent();

        // Extract canonical URL
        preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $canonicalMatch);
        $this->assertNotEmpty($canonicalMatch, 'Canonical URL not found in page');
        $canonicalUrl = $canonicalMatch[1];

        // Extract og:url
        preg_match('/<meta property="og:url" content="([^"]+)"/', $content, $ogMatch);
        $this->assertNotEmpty($ogMatch, 'og:url not found in page');
        $ogUrl = $ogMatch[1];

        // They MUST match
        $this->assertEquals(
            $canonicalUrl,
            $ogUrl,
            'og:url must match canonical URL to prevent SEO issues'
        );
    }

    #[Test]
    public function twitter_url_matches_canonical_url()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0TWMATCH1',
            'country'           => 'gb',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Twitter URL Match Test',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $content = $response->getContent();

        // Extract canonical URL
        preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $canonicalMatch);
        $this->assertNotEmpty($canonicalMatch, 'Canonical URL not found in page');
        $canonicalUrl = $canonicalMatch[1];

        // Extract twitter:url
        preg_match('/<meta property="twitter:url" content="([^"]+)"/', $content, $twitterMatch);
        $this->assertNotEmpty($twitterMatch, 'twitter:url not found in page');
        $twitterUrl = $twitterMatch[1];

        // They MUST match
        $this->assertEquals(
            $canonicalUrl,
            $twitterUrl,
            'twitter:url must match canonical URL to prevent SEO issues'
        );
    }

    #[Test]
    public function all_seo_urls_are_consistent()
    {
        // Comprehensive test: canonical, og:url, twitter:url, and JSON-LD url must all match
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0ALLURLS1',
            'country'           => 'fr',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'All URLs Consistent Test',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $content = $response->getContent();

        // Extract all URL references
        preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $canonicalMatch);
        preg_match('/<meta property="og:url" content="([^"]+)"/', $content, $ogMatch);
        preg_match('/<meta property="twitter:url" content="([^"]+)"/', $content, $twitterMatch);

        $this->assertNotEmpty($canonicalMatch, 'Canonical URL not found');
        $this->assertNotEmpty($ogMatch, 'og:url not found');
        $this->assertNotEmpty($twitterMatch, 'twitter:url not found');

        $canonicalUrl = $canonicalMatch[1];
        $ogUrl = $ogMatch[1];
        $twitterUrl = $twitterMatch[1];

        // All must match
        $this->assertEquals($canonicalUrl, $ogUrl, 'og:url does not match canonical');
        $this->assertEquals($canonicalUrl, $twitterUrl, 'twitter:url does not match canonical');

        // All must use /amazon/ format
        $this->assertStringContainsString('/amazon/', $canonicalUrl);
        $this->assertStringNotContainsString('/analysis/', $canonicalUrl);
    }

    // =========================================================================
    // SEOService Unit Tests for Canonical URL Generation
    // =========================================================================

    #[Test]
    public function seo_service_generates_correct_canonical_with_product_title()
    {
        $asinData = AsinData::factory()->create([
            'asin'          => 'B0WITHTITL',
            'country'       => 'us',
            'product_title' => 'My Amazing Product Title',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Should include slug derived from title
        $this->assertEquals('/amazon/us/B0WITHTITL/my-amazing-product-title', $seoData['canonical_url']);
    }

    #[Test]
    public function seo_service_generates_correct_canonical_without_product_title()
    {
        $asinData = AsinData::factory()->create([
            'asin'          => 'B0NOTITLE1',
            'country'       => 'ca',
            'product_title' => null,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Should fallback to simple format without slug
        $this->assertEquals('/amazon/ca/B0NOTITLE1', $seoData['canonical_url']);
    }

    #[Test]
    public function seo_service_handles_all_supported_countries()
    {
        $countries = ['us', 'gb', 'ca', 'de', 'fr', 'it', 'es', 'jp', 'au', 'mx', 'in'];

        foreach ($countries as $country) {
            $asinData = AsinData::factory()->create([
                'asin'          => 'B0COUNTRY'.strtoupper(substr($country, 0, 1)),
                'country'       => $country,
                'product_title' => 'Country Test Product',
            ]);

            $seoData = $this->seoService->generateProductSEOData($asinData);

            $this->assertStringStartsWith("/amazon/{$country}/", $seoData['canonical_url']);
            $this->assertStringNotContainsString('/analysis/', $seoData['canonical_url']);
        }
    }

    #[Test]
    public function seo_service_truncates_long_titles_in_canonical_slug()
    {
        $longTitle = str_repeat('Very Long Product Title That Exceeds Fifty Characters ', 3);

        $asinData = AsinData::factory()->create([
            'asin'          => 'B0LONGTTL1',
            'country'       => 'us',
            'product_title' => $longTitle,
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        // Slug should be truncated but still valid
        $this->assertStringStartsWith('/amazon/us/B0LONGTTL1/', $seoData['canonical_url']);
        $this->assertLessThan(100, strlen($seoData['canonical_url']), 'Canonical URL should be reasonably short');
    }

    // =========================================================================
    // Legacy /analysis/ URL Redirect Tests
    // =========================================================================

    #[Test]
    public function analysis_url_redirects_with_301_status()
    {
        $asinData = AsinData::factory()->create([
            'asin'    => 'B0301REDIR',
            'country' => 'us',
        ]);

        $response = $this->get('/analysis/B0301REDIR/us');

        // MUST be 301 (permanent redirect) for SEO
        $this->assertEquals(301, $response->status(), 'Legacy /analysis/ URLs must return 301 for SEO');
    }

    #[Test]
    public function analysis_url_redirects_to_amazon_format()
    {
        $response = $this->get('/analysis/B0TOAMAZON/de');

        $response->assertStatus(301);
        $response->assertRedirect('/amazon/de/B0TOAMAZON');
    }

    #[Test]
    public function analysis_url_redirect_works_for_all_countries()
    {
        // Test ASINs must be exactly 10 alphanumeric characters
        $testCases = [
            'us' => 'B0COUNTRUS',
            'gb' => 'B0COUNTRGB',
            'ca' => 'B0COUNTRCA',
            'de' => 'B0COUNTRDE',
            'fr' => 'B0COUNTRFR',
        ];

        foreach ($testCases as $country => $asin) {
            $response = $this->get("/analysis/{$asin}/{$country}");

            $response->assertStatus(301);
            $this->assertStringContainsString("/amazon/{$country}/", $response->headers->get('Location'));
        }
    }

    // =========================================================================
    // JSON-LD Structured Data URL Validation
    // =========================================================================

    #[Test]
    public function json_ld_url_matches_canonical()
    {
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0JSONLD01',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'JSON-LD URL Test',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $content = $response->getContent();

        // Extract canonical URL
        preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $canonicalMatch);
        $canonicalUrl = $canonicalMatch[1] ?? '';

        // Extract JSON-LD Product schema URL
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $jsonMatches);

        foreach ($jsonMatches[1] as $jsonContent) {
            $data = json_decode(trim($jsonContent), true);
            if (isset($data['@type']) && $data['@type'] === 'Product' && isset($data['url'])) {
                $this->assertEquals(
                    $canonicalUrl,
                    $data['url'],
                    'JSON-LD Product schema URL must match canonical URL'
                );
            }
        }
    }

    // =========================================================================
    // Regression Prevention Tests
    // =========================================================================

    #[Test]
    public function canonical_url_never_contains_api_patterns()
    {
        // Ensure canonical URLs never accidentally use API route patterns
        $apiPatterns = ['/api/', '/analysis/', '/extension/', '/chrome-extension/'];

        $asinData = AsinData::factory()->create([
            'asin'          => 'B0NOAPIPAT',
            'country'       => 'us',
            'product_title' => 'No API Pattern Test',
        ]);

        $seoData = $this->seoService->generateProductSEOData($asinData);

        foreach ($apiPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $seoData['canonical_url'],
                "Canonical URL must never contain API pattern: {$pattern}"
            );
        }
    }

    #[Test]
    public function rendered_page_canonical_never_contains_analysis()
    {
        // Test the actual rendered HTML to ensure /analysis/ never appears in canonical
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0RENDERED',
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Rendered Page Test',
        ]);

        $response = $this->get("/amazon/{$asinData->country}/{$asinData->asin}");
        if ($response->status() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $content = $response->getContent();

        // Extract canonical URL from rendered HTML
        preg_match('/<link rel="canonical" href="([^"]+)"/', $content, $match);
        $this->assertNotEmpty($match, 'Canonical URL not found in rendered HTML');

        $canonicalUrl = $match[1];

        // The rendered canonical must NEVER contain /analysis/
        $this->assertStringNotContainsString(
            '/analysis/',
            $canonicalUrl,
            'CRITICAL: Rendered canonical URL contains /analysis/ - this caused the Nov 2025 SEO incident'
        );
    }
}

