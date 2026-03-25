<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expand_url_endpoint_exists()
    {
        // Mock HTTP response to avoid real network calls
        Http::fake([
            'a.co/d/test123' => Http::response('', 200),
        ]);

        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        // Should return a JSON response (success or failure)
        $response->assertJsonStructure(['success']);
    }

    public function test_expand_url_requires_valid_url()
    {
        $response = $this->postJson('/api/expand-url', [
            'url' => 'not-a-url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }

    public function test_expand_url_only_accepts_amazon_urls()
    {
        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://google.com',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'error'   => 'Only Amazon URLs are supported',
        ]);
    }

    public function test_expand_url_accepts_amazon_short_domains()
    {
        // Mock HTTP responses for all test URLs to avoid real network calls
        Http::fake([
            'a.co/d/test123'           => Http::response('', 200),
            'amzn.to/test123'          => Http::response('', 200),
            'amazon.com/dp/B123456789' => Http::response('', 200),
        ]);

        $amazonDomains = [
            'https://a.co/d/test123',
            'https://amzn.to/test123',
            'https://amazon.com/dp/B123456789',
        ];

        foreach ($amazonDomains as $url) {
            $response = $this->postJson('/api/expand-url', [
                'url' => $url,
            ]);

            // Should not reject these URLs (will attempt expansion)
            $response->assertJsonStructure(['success']);

            if ($response->json('success') === false) {
                // If expansion fails, it should be due to network issues, not domain rejection
                $this->assertStringNotContainsString('Only Amazon URLs are supported', $response->json('error'));
            }
        }
    }

    public function test_expand_url_returns_proper_structure()
    {
        // Mock a successful expansion
        Http::fake([
            'a.co/*' => Http::response('', 301, [
                'Location' => 'https://www.amazon.com/dp/B088KGQCFF?ref=test',
            ]),
        ]);

        $response = $this->postJson('/api/expand-url', [
            'url' => 'https://a.co/d/test123',
        ]);

        $response->assertJsonStructure([
            'success',
            'original_url',
            'expanded_url',
        ]);
    }

    public function test_frontend_uses_backend_expansion()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Check that frontend calls our new API endpoint
        $response->assertSee('/api/expand-url', false);
        $response->assertSee('expandShortUrl', false);
        $response->assertSee('Backend successfully expanded URL', false);
    }

    public function test_frontend_includes_csrf_token()
    {
        $response = $this->get('/');

        // Check that CSRF token is available in the frontend
        $response->assertSee('csrf-token', false);
        $response->assertSee('X-CSRF-TOKEN', false);
    }
}
