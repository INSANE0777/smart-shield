<?php

namespace Tests\Unit;

use App\Services\Amazon\BrightDataScraperService;
use App\Services\ReviewAnalysisService;
use App\Services\ReviewService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternationalUrlSupportTest extends TestCase
{
    private ReviewService $reviewService;
    private ReviewAnalysisService $analysisService;

    public function setUp(): void
    {
        parent::setUp();
        $this->reviewService = new ReviewService();
        $this->analysisService = new ReviewAnalysisService($this->reviewService, $this->createMock(\App\Services\OpenAIService::class));
    }

    #[Test]
    public function it_extracts_asin_from_international_urls()
    {
        $testCases = [
            'https://www.amazon.de/-/en/TRAPANO-BATTERIA-PERCUSSIONE-GSB12-BOSCH/dp/B00YYBBUBY/'  => 'B00YYBBUBY',
            'https://www.amazon.ca/Dr-Scholls-Womens-Sneaker-Pebbled/dp/B0F5RQDJD3/'              => 'B0F5RQDJD3',
            'https://www.amazon.co.uk/Vax-Smartwash-Pet-Design/dp/B0BHF3NKLK/'                    => 'B0BHF3NKLK',
            'https://www.amazon.fr/-/en/Bluetooth-Earphones-Wireless/dp/B0FCS5ZRB4'               => 'B0FCS5ZRB4',
            'https://www.amazon.in/Daikin-Inverter-Display-Technology-MTKL50U/dp/B0BK1KS6ZD/'     => 'B0BK1KS6ZD',
            'https://www.amazon.ie/Celestron-11069-Computerised-Schmidt-Cassegrain/dp/B000GUFOC8' => 'B000GUFOC8',
            'https://www.amazon.co.jp/-/en/Bagasin-Shockproof-Laptop/dp/B0BLNHG168'               => 'B0BLNHG168',
            'https://www.amazon.com.mx/SAMSUNG-Galaxy-Negro-Onyx/dp/B0CQ84BYDC/'                  => 'B0CQ84BYDC',
            'https://www.amazon.com.br/Controle-Dualshock-PlayStation-4-Preto/dp/B07FN1MZBH/'     => 'B07FN1MZBH',
            'https://www.amazon.es/-/en/Cordless-Cleaner-Filtration/dp/B0CZRTHM6T/'               => 'B0CZRTHM6T',
        ];

        foreach ($testCases as $url => $expectedAsin) {
            $extractedAsin = $this->analysisService->extractAsinFromUrl($url);
            $this->assertEquals($expectedAsin, $extractedAsin, "Failed to extract ASIN from: {$url}");
        }
    }

    #[Test]
    public function it_detects_country_from_international_urls()
    {
        $testCases = [
            'https://www.amazon.de/some-product/dp/B00YYBBUBY/'     => 'de',
            'https://www.amazon.ca/some-product/dp/B0F5RQDJD3/'     => 'ca',
            'https://www.amazon.co.uk/some-product/dp/B0BHF3NKLK/'  => 'gb',
            'https://www.amazon.fr/some-product/dp/B0FCS5ZRB4'      => 'fr',
            'https://www.amazon.in/some-product/dp/B0BK1KS6ZD/'     => 'in',
            'https://www.amazon.ie/some-product/dp/B000GUFOC8/'     => 'ie',
            'https://www.amazon.co.jp/some-product/dp/B0BLNHG168'   => 'jp',
            'https://www.amazon.com.mx/some-product/dp/B0CQ84BYDC/' => 'mx',
            'https://www.amazon.com.br/some-product/dp/B07FN1MZBH/' => 'br',
            'https://www.amazon.es/some-product/dp/B0CZRTHM6T/'     => 'es',
            'https://www.amazon.sg/some-product/dp/B0TESTSING/'     => 'sg',
            'https://www.amazon.com.au/some-product/dp/B0TESTAUST/' => 'au',
        ];

        foreach ($testCases as $url => $expectedCountry) {
            $detectedCountry = $this->reviewService->extractCountryFromUrl($url);
            $this->assertEquals($expectedCountry, $detectedCountry, "Failed to detect country from: {$url}");
        }
    }

    #[Test]
    public function brightdata_service_builds_correct_international_urls()
    {
        $service = new BrightDataScraperService();
        $reflection = new \ReflectionClass($service);
        $buildUrlMethod = $reflection->getMethod('buildAmazonUrl');
        $buildUrlMethod->setAccessible(true);

        $testCases = [
            ['B00YYBBUBY', 'de', 'https://www.amazon.de/dp/B00YYBBUBY/'],
            ['B0F5RQDJD3', 'ca', 'https://www.amazon.ca/dp/B0F5RQDJD3/'],
            ['B0BHF3NKLK', 'gb', 'https://www.amazon.co.uk/dp/B0BHF3NKLK/'],
            ['B0FCS5ZRB4', 'fr', 'https://www.amazon.fr/dp/B0FCS5ZRB4/'],
            ['B0BK1KS6ZD', 'in', 'https://www.amazon.in/dp/B0BK1KS6ZD/'],
            ['B000GUFOC8', 'ie', 'https://www.amazon.ie/dp/B000GUFOC8/'],
            ['B0BLNHG168', 'jp', 'https://www.amazon.co.jp/dp/B0BLNHG168/'],
            ['B0CQ84BYDC', 'mx', 'https://www.amazon.com.mx/dp/B0CQ84BYDC/'],
            ['B07FN1MZBH', 'br', 'https://www.amazon.com.br/dp/B07FN1MZBH/'],
            ['B0CZRTHM6T', 'es', 'https://www.amazon.es/dp/B0CZRTHM6T/'],
        ];

        foreach ($testCases as [$asin, $country, $expectedUrl]) {
            $builtUrl = $buildUrlMethod->invoke($service, $asin, $country);
            $this->assertEquals($expectedUrl, $builtUrl, "Failed to build correct URL for {$country}");
        }
    }

    #[Test]
    public function it_handles_edge_cases_in_country_detection()
    {
        // Test that more specific domains are matched first (e.g., amazon.com.mx vs amazon.com)
        $this->assertEquals('mx', $this->reviewService->extractCountryFromUrl('https://www.amazon.com.mx/product/dp/B0123456789/'));
        $this->assertEquals('br', $this->reviewService->extractCountryFromUrl('https://www.amazon.com.br/product/dp/B0123456789/'));
        $this->assertEquals('au', $this->reviewService->extractCountryFromUrl('https://www.amazon.com.au/product/dp/B0123456789/'));
        $this->assertEquals('us', $this->reviewService->extractCountryFromUrl('https://www.amazon.com/product/dp/B0123456789/'));
    }

    #[Test]
    public function it_supports_all_documented_countries()
    {
        $supportedCountries = [
            'us', 'gb', 'ca', 'de', 'fr', 'it', 'es', 'ie', 'jp', 'au',
            'mx', 'in', 'sg', 'br', 'nl', 'tr', 'ae', 'sa', 'se', 'pl', 'eg', 'be',
        ];

        $service = new BrightDataScraperService();
        $reflection = new \ReflectionClass($service);
        $buildUrlMethod = $reflection->getMethod('buildAmazonUrl');
        $buildUrlMethod->setAccessible(true);

        foreach ($supportedCountries as $country) {
            $url = $buildUrlMethod->invoke($service, 'B0TEST1234', $country);
            $this->assertStringContainsString('amazon.', $url, "Should build valid Amazon URL for country: {$country}");
            $this->assertStringContainsString('B0TEST1234', $url, "Should include ASIN in URL for country: {$country}");
        }
    }
}
