<?php

namespace App\Http\Controllers;

use App\Services\LoggingService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UrlExpansionController extends Controller
{
    /**
     * Expand a shortened URL to its final destination.
     */
    public function expandUrl(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $shortUrl = $request->input('url');

        // Only expand Amazon short URLs for security
        if (!$this->isAmazonShortUrl($shortUrl)) {
            return response()->json([
                'success' => false,
                'error'   => 'Only Amazon URLs are supported',
            ], 400);
        }

        try {
            LoggingService::log('Expanding short URL', [
                'short_url' => $shortUrl,
            ]);

            // Follow redirects manually to get final URL
            $finalUrl = $this->followRedirects($shortUrl);

            LoggingService::log('URL expansion successful', [
                'short_url' => $shortUrl,
                'final_url' => $finalUrl,
            ]);

            return response()->json([
                'success'      => true,
                'original_url' => $shortUrl,
                'expanded_url' => $finalUrl,
            ]);
        } catch (GuzzleException $e) {
            LoggingService::log('URL expansion failed', [
                'short_url' => $shortUrl,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Unable to expand URL: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Follow redirects manually to get final URL without validating the final destination.
     */
    private function followRedirects(string $url, int $maxRedirects = 5): string
    {
        $client = new Client([
            'timeout'         => 5,
            'connect_timeout' => 2,
            'allow_redirects' => false,
            'headers'         => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            ],
        ]);

        $currentUrl = $url;
        $redirectCount = 0;

        while ($redirectCount < $maxRedirects) {
            try {
                $response = $client->get($currentUrl);
                $statusCode = $response->getStatusCode();

                LoggingService::log('Redirect step', [
                    'url'            => $currentUrl,
                    'status'         => $statusCode,
                    'redirect_count' => $redirectCount,
                ]);

                if (in_array($statusCode, [301, 302, 303, 307, 308])) {
                    $locationHeaders = $response->getHeader('Location');
                    if (empty($locationHeaders)) {
                        LoggingService::log('No location header found, stopping redirects');
                        break;
                    }

                    $newUrl = $locationHeaders[0];

                    // If we've reached an Amazon product URL, stop here - don't try to validate it
                    if (strpos($newUrl, 'amazon.com/dp/') !== false || strpos($newUrl, 'amazon.com/gp/product/') !== false) {
                        LoggingService::log('Reached Amazon product URL, stopping redirects', [
                            'final_url' => $newUrl,
                        ]);

                        return $newUrl;
                    }

                    $currentUrl = $newUrl;
                    $redirectCount++;
                } else {
                    // Got a non-redirect response, stop here
                    break;
                }
            } catch (\Exception $e) {
                LoggingService::log('Redirect failed', [
                    'url'   => $currentUrl,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        }

        return $currentUrl;
    }

    /**
     * Check if URL is an Amazon short URL.
     */
    private function isAmazonShortUrl(string $url): bool
    {
        $amazonShortDomains = [
            'a.co',
            'amzn.to',
            'amazon.com',
        ];

        $host = parse_url($url, PHP_URL_HOST);

        foreach ($amazonShortDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
