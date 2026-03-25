<?php

namespace App\Services\Amazon;

use App\Services\LoggingService;
use Illuminate\Support\Facades\Http;

class AmazonUrlService
{
    /**
     * Extract ASIN from various Amazon URL formats.
     */
    public function extractAsinFromUrl(string $url): string
    {
        // Handle short URLs (a.co redirects)
        if (preg_match('/^https?:\/\/a\.co\//', $url)) {
            $url = $this->followRedirect($url);
            LoggingService::log("Followed redirect to: {$url}");
        }

        // Extract ASIN from various Amazon URL patterns
        $patterns = [
            '/\/dp\/([A-Z0-9]{10})/',           // /dp/ASIN
            '/\/product\/([A-Z0-9]{10})/',      // /product/ASIN
            '/\/product-reviews\/([A-Z0-9]{10})/', // /product-reviews/ASIN
            '/\/gp\/product\/([A-Z0-9]{10})/',  // /gp/product/ASIN
            '/ASIN=([A-Z0-9]{10})/',            // ASIN=ASIN parameter
            '/\/([A-Z0-9]{10})(?:\/|\?|$)/',    // ASIN in path
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                LoggingService::log("Extracted ASIN '{$matches[1]}' using pattern: {$pattern}");

                return $matches[1];
            }
        }

        // If it's already just an ASIN
        if (preg_match('/^[A-Z0-9]{10}$/', $url)) {
            LoggingService::log("Input is already an ASIN: {$url}");

            return $url;
        }

        throw new \InvalidArgumentException("Could not extract ASIN from URL: {$url}");
    }

    /**
     * Extract country code from Amazon URL.
     */
    public function extractCountryFromUrl(string $url): string
    {
        // Country domain mapping (ordered by specificity)
        $domainMapping = [
            'amazon.com.mx' => 'mx',
            'amazon.com.br' => 'br',
            'amazon.com.au' => 'au',
            'amazon.com'    => 'us',
            'amazon.co.uk'  => 'gb',
            'amazon.ca'     => 'ca',
            'amazon.de'     => 'de',
            'amazon.fr'     => 'fr',
            'amazon.it'     => 'it',
            'amazon.es'     => 'es',
            'amazon.ie'     => 'ie',
            'amazon.co.jp'  => 'jp',
            'amazon.in'     => 'in',
            'amazon.sg'     => 'sg',
            'amazon.nl'     => 'nl',
            'amazon.com.tr' => 'tr',
            'amazon.ae'     => 'ae',
            'amazon.sa'     => 'sa',
            'amazon.se'     => 'se',
            'amazon.pl'     => 'pl',
            'amazon.eg'     => 'eg',
            'amazon.be'     => 'be',
        ];

        foreach ($domainMapping as $domain => $country) {
            if (strpos($url, $domain) !== false) {
                return $country;
            }
        }

        // Default to US if no match found
        return 'us';
    }

    /**
     * Check if a product exists at the given URL.
     */
    public function checkProductExists(string $productUrl): array
    {
        try {
            $response = Http::timeout(10)->get($productUrl);

            if ($response->successful()) {
                $content = $response->body();

                // Check for product not found indicators
                if (strpos($content, 'Page Not Found') !== false ||
                    strpos($content, 'Looking for something?') !== false ||
                    strpos($content, 'Sorry, we couldn\'t find that page') !== false) {
                    return ['exists' => false, 'reason' => 'Product page not found'];
                }

                return ['exists' => true];
            }

            return ['exists' => false, 'reason' => "HTTP {$response->status()}"];
        } catch (\Exception $e) {
            LoggingService::handleException($e, "Failed to check product existence for URL: {$productUrl}");

            return ['exists' => false, 'reason' => 'Connection error: '.$e->getMessage()];
        }
    }

    /**
     * Build Amazon product URL from ASIN and country.
     */
    public function buildProductUrl(string $asin, string $country): string
    {
        $domainMapping = [
            'us' => 'amazon.com',
            'gb' => 'amazon.co.uk',
            'ca' => 'amazon.ca',
            'de' => 'amazon.de',
            'fr' => 'amazon.fr',
            'it' => 'amazon.it',
            'es' => 'amazon.es',
            'jp' => 'amazon.co.jp',
            'au' => 'amazon.com.au',
            'mx' => 'amazon.com.mx',
            'in' => 'amazon.in',
            'sg' => 'amazon.sg',
            'br' => 'amazon.com.br',
            'nl' => 'amazon.nl',
            'tr' => 'amazon.com.tr',
            'ae' => 'amazon.ae',
            'sa' => 'amazon.sa',
            'se' => 'amazon.se',
            'pl' => 'amazon.pl',
            'eg' => 'amazon.eg',
            'be' => 'amazon.be',
        ];

        $domain = $domainMapping[$country] ?? 'amazon.com';

        return "https://{$domain}/dp/{$asin}";
    }

    /**
     * Follow redirect for short URLs.
     */
    private function followRedirect(string $url): string
    {
        try {
            $response = Http::withOptions(['allow_redirects' => false])->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location) {
                    return $location;
                }
            }

            return $url;
        } catch (\Exception $e) {
            LoggingService::handleException($e, "Failed to follow redirect for URL: {$url}");

            return $url;
        }
    }
}
