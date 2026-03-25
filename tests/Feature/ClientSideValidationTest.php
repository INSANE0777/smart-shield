<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSideValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loads_with_validation_elements()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Check that validation elements are present
        $response->assertSee('id="productUrl"', false);
        $response->assertSee('id="url-validation-status"', false);
        $response->assertSee('id="analyze-button"', false);

        // Check that validation JavaScript functions are included
        $response->assertSee('validateAmazonUrl', false);
        $response->assertSee('tryMultipleValidationMethods', false);
        $response->assertSee('validateViaImageRequest', false);
        $response->assertSee('validateViaWebRequest', false);
        $response->assertSee('expandShortUrl', false);
    }

    public function test_amazon_url_patterns_are_supported()
    {
        $response = $this->get('/');

        // Check that URL pattern matching is included
        $response->assertSee('\/dp\/([A-Z0-9]{10})', false);
        $response->assertSee('\/gp\/product\/([A-Z0-9]{10})', false);
        $response->assertSee('\/exec\/obidos\/ASIN\/([A-Z0-9]{10})', false);
    }

    public function test_short_url_detection_is_present()
    {
        $response = $this->get('/');

        // Check that short URL detection is included
        $response->assertSee('a.co/', false);
        $response->assertSee('amzn.to/', false);
        $response->assertSee('expandShortUrl', false);
    }

    public function test_validation_status_messages_are_defined()
    {
        $response = $this->get('/');

        // Check that validation status messages are present
        $response->assertSee('Product verified on Amazon', false);
        $response->assertSee('Unable to verify - will check during analysis', false);
        $response->assertSee('Could not verify product - will check during analysis', false);
        $response->assertSee('Verification unavailable - will check during analysis', false);
        $response->assertSee('Invalid Amazon URL format', false);
        $response->assertSee('Short URL detected - will process server-side', false);
    }

    public function test_validation_methods_are_included()
    {
        $response = $this->get('/');

        // Check that all validation methods are present
        $response->assertSee('validateViaImageRequest', false);
        $response->assertSee('validateViaWebRequest', false);
        $response->assertSee('validateViaIframe', false);
        $response->assertSee('validateViaAlternateEndpoint', false);

        // Check image validation uses correct Amazon image URL format
        $response->assertSee('images-na.ssl-images-amazon.com/images/P/', false);

        // Check search endpoint validation
        $response->assertSee('amazon.com/s?k=', false);
    }

    public function test_privacy_features_are_implemented()
    {
        $response = $this->get('/');

        // Check that privacy-preserving features are included
        $response->assertSee('mode: \'no-cors\'', false);
        $response->assertSee('referrerPolicy: \'no-referrer\'', false);
        $response->assertSee('credentials: \'omit\'', false);
        $response->assertSee('cache: \'no-store\'', false);
        $response->assertSee('crossOrigin = \'anonymous\'', false);
    }

    public function test_debouncing_is_configured()
    {
        $response = $this->get('/');

        // Check that input debouncing is configured
        $response->assertSee('setTimeout(validateAmazonUrl, 1000)', false);
        $response->assertSee('addEventListener(\'input\'', false);
        $response->assertSee('addEventListener(\'paste\'', false);
    }

    public function test_livewire_integration_is_present()
    {
        $response = $this->get('/');

        // Check that Livewire integration is included
        $response->assertSee('wire:model', false);
        $response->assertSee('Livewire.emit', false);
        $response->assertSee('syncInputs', false);
    }

    public function test_validation_timeouts_are_configured()
    {
        $response = $this->get('/');

        // Check that timeouts are properly configured
        $response->assertSee('setTimeout(() => controller.abort(), 3000)', false);
        $response->assertSee('setTimeout(() => {', false);
        $response->assertSee('4000)', false); // iframe timeout
        $response->assertSee('5000', false);  // short URL timeout
    }

    public function test_console_logging_is_implemented()
    {
        $response = $this->get('/');

        // Check that detailed logging is present for debugging
        $response->assertSee('console.log', false);
        $response->assertSee('Trying', false);
        $response->assertSee('succeeded', false);
        $response->assertSee('failed', false);
    }
}
