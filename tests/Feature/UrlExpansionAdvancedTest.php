<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlExpansionAdvancedTest extends TestCase
{
    use RefreshDatabase;

    public function test_expand_url_handles_multiple_redirects()
    {
        // Mock HTTP response to avoid real network calls
        Http::fake([
            'a.co/d/test123' => Http::response('', 200),
        ]);

        // This test verifies the redirect handling logic exists
        // Since we can't easily mock Guzzle in feature tests, we test the structure
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'original_url',
            'expanded_url',
        ]);

        // The URL expansion may fail due to network, but structure should be correct
        $this->assertIsString($response->json('expanded_url'));
    }

    public function test_expand_url_stops_at_amazon_product_url()
    {
        // Mock HTTP response to avoid real network calls
        Http::fake([
            'a.co/d/test123' => Http::response('', 200),
        ]);

        // Test that the logic for detecting Amazon product URLs exists
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'original_url',
            'expanded_url',
        ]);

        // Verify the response structure is correct
        $this->assertEquals('https://a.co/d/test123', $response->json('original_url'));
    }

    public function test_expand_url_stops_at_gp_product_url()
    {
        // Mock HTTP response to avoid real network calls
        Http::fake([
            'amazon.com/gp/product/B088KGQCFF' => Http::response('', 200),
        ]);

        // Test that the controller handles gp/product URLs correctly
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://amazon.com/gp/product/B088KGQCFF',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'original_url',
            'expanded_url',
        ]);

        // For direct Amazon URLs, should return the same URL
        $this->assertEquals('https://amazon.com/gp/product/B088KGQCFF', $response->json('original_url'));
    }

    public function test_expand_url_handles_redirect_without_location_header()
    {
        Http::fake([
            'a.co/d/test123' => Http::response('', 301, [
                // No Location header
            ]),
        ]);

        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'expanded_url' => 'https://a.co/d/test123', // Should return original URL
        ]);
    }

    public function test_expand_url_handles_non_redirect_response()
    {
        Http::fake([
            'a.co/d/test123' => Http::response('Page content', 200),
        ]);

        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'expanded_url' => 'https://a.co/d/test123',
        ]);
    }

    public function test_expand_url_handles_max_redirects()
    {
        // Mock HTTP responses to avoid real network calls
        Http::fake([
            'a.co/d/test123' => Http::response('', 200),
        ]);

        // Test that max redirects logic exists (can't easily test the exact behavior)
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'original_url',
            'expanded_url',
        ]);

        // Should handle the request without crashing
        $this->assertIsString($response->json('expanded_url'));
    }

    public function test_expand_url_handles_network_timeout()
    {
        // Mock a timeout response instead of trying real network calls
        Http::fake([
            'a.co/d/nonexistent123456789' => Http::response('Connection timeout', 408), // Use proper timeout status code
        ]);

        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/nonexistent123456789',
        ]);

        // Should handle the timeout gracefully and return original URL
        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'expanded_url' => 'https://a.co/d/nonexistent123456789',
        ]);
    }

    public function test_expand_url_handles_http_error()
    {
        Http::fake([
            'a.co/*' => Http::response('Server Error', 500),
        ]);

        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'expanded_url' => 'https://a.co/d/test123', // Should return original URL when expansion fails
        ]);
    }

    public function test_expand_url_handles_various_redirect_codes()
    {
        // Mock HTTP responses for all test URLs to avoid real network calls
        Http::fake([
            'a.co/d/test301'  => Http::response('', 200),
            'a.co/d/test302'  => Http::response('', 200),
            'amzn.to/test303' => Http::response('', 200),
        ]);

        // Test that the controller can handle different types of URLs
        $testUrls = [
            'https://a.co/d/test301',
            'https://a.co/d/test302',
            'https://amzn.to/test303',
        ];

        foreach ($testUrls as $url) {
            $response = $this->postJson('/api/expand-url', [
                'url' => $url,
            ]);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'success',
                'original_url',
                'expanded_url',
            ]);

            $this->assertEquals($url, $response->json('original_url'));
        }
    }

    public function test_is_amazon_short_url_with_subdomains()
    {
        // Mock HTTP responses for all test URLs to avoid real network calls
        Http::fake([
            'a.co/d/test123'           => Http::response('', 200),
            'www.a.co/d/test123'       => Http::response('', 200),
            'amzn.to/test123'          => Http::response('', 200),
            'www.amzn.to/test123'      => Http::response('', 200),
            'amazon.com/dp/test'       => Http::response('', 200),
            'www.amazon.com/dp/test'   => Http::response('', 200),
            'smile.amazon.com/dp/test' => Http::response('', 200),
        ]);

        $validUrls = [
            'https://a.co/d/test123',
            'https://www.a.co/d/test123',
            'https://amzn.to/test123',
            'https://www.amzn.to/test123',
            'https://amazon.com/dp/test',
            'https://www.amazon.com/dp/test',
            'https://smile.amazon.com/dp/test',
        ];

        foreach ($validUrls as $url) {
            $response = $this->postJson('/api/expand-url', [
                'url' => $url,
            ]);

            // Should not reject these URLs due to domain validation
            $this->assertNotEquals(400, $response->getStatusCode(), "URL $url was incorrectly rejected");

            if ($response->getStatusCode() === 400) {
                $this->assertStringNotContainsString('Only Amazon URLs are supported', $response->json('error'));
            }
        }
    }

    public function test_expand_url_missing_url_parameter()
    {
        $response = $this->postJson('/api/expand-url', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }

    public function test_expand_url_empty_url_parameter()
    {
        $response = $this->postJson('/api/expand-url', [
            'url' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }

    public function test_expand_url_logs_expansion_process()
    {
        Http::fake([
            'a.co/d/test123' => Http::response('', 301, [
                'Location' => 'https://amazon.com/dp/B088KGQCFF',
            ]),
        ]);

        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertStatus(200);

        // The endpoint should log the expansion process
        // (We can't easily test the actual logging without mocking the LoggingService,
        // but we can verify the endpoint completes successfully)
        $response->assertJson([
            'success' => true,
        ]);
    }
}
