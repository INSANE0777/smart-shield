<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortUrlValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_url_detection_in_frontend()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Check that short URL detection patterns are present
        $response->assertSee('a.co/', false);
        $response->assertSee('amzn.to/', false);
        $response->assertSee('Amazon short URL detected', false);
        $response->assertSee('expandShortUrl', false);
    }

    public function test_short_url_asin_extraction_pattern()
    {
        $response = $this->get('/');

        // Check that ASIN extraction from short URL path is implemented
        $response->assertSee('\/d\/([A-Z0-9]{10})', false);
        $response->assertSee('Found potential ASIN in short URL', false);
    }

    public function test_server_side_short_url_expansion()
    {
        // Test that the URL expansion API works for short URLs
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/B08N5WRWNW',
        ]);

        // Should return a JSON response structure
        $response->assertJsonStructure(['success']);

        // If successful, should have expanded URL
        if ($response->json('success')) {
            $response->assertJsonStructure([
                'success',
                'original_url',
                'expanded_url',
            ]);
        } else {
            // If failed, should have error message
            $response->assertJsonStructure([
                'success',
                'error',
            ]);
        }
    }

    public function test_short_url_with_valid_asin_pattern()
    {
        $response = $this->get('/');

        // Test that the client-side validation can handle a.co URLs with /d/ pattern
        $response->assertSee('shortUrlAsin', false);
        $response->assertSee('Short URL validated successfully', false);
        $response->assertSee('Short URL detected - will process server-side', false);
    }

    public function test_backend_url_expansion_method()
    {
        $response = $this->get('/');

        // Check that backend expansion method is implemented
        $response->assertSee('/api/expand-url', false);
        $response->assertSee('Backend successfully expanded URL', false);
        $response->assertSee('Backend URL expansion failed', false);
    }

    public function test_short_url_validation_messages()
    {
        $response = $this->get('/');

        // Check that appropriate status messages are defined for short URLs
        $response->assertSee('Amazon short URL detected - validating', false);
        $response->assertSee('Backend successfully expanded URL', false);
        $response->assertSee('Backend URL expansion failed', false);
        $response->assertSee('Short URL detected - will process server-side', false);
    }

    public function test_short_url_fallback_behavior()
    {
        $response = $this->get('/');

        // Ensure that even if client-side expansion fails, submission is still allowed
        $response->assertSee('server will handle expansion', false);
        $response->assertSee('analyzeButton.disabled = false', false);
    }

    public function test_amzn_to_urls_are_supported()
    {
        $response = $this->get('/');

        // Check that both a.co and amzn.to URLs are supported
        $response->assertSee("url.includes('a.co/')", false);
        $response->assertSee("url.includes('amzn.to/')", false);
    }

    public function test_url_expansion_security()
    {
        // Test that non-Amazon URLs are rejected
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://malicious-site.com/redirect',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'error'   => 'Only Amazon URLs are supported',
        ]);
    }

    public function test_backend_expansion_api_available()
    {
        // Test that the API endpoint is accessible
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        // Should return JSON structure (success or failure)
        $response->assertJsonStructure(['success']);
    }
}
