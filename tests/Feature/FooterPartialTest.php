<?php

namespace Tests\Feature;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterPartialTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_footer_partial_on_home_page()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $this->assertFooterContentPresent($response);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_footer_partial_on_product_show_page()
    {
        $asinData = AsinData::factory()->create([
            'country'         => 'us',
            'status'          => 'completed',
            'fake_percentage' => 25,
            'grade'           => 'B',
        ]);

        $response = $this->followingRedirects()->get(route('amazon.product.show', [
            'asin'    => $asinData->asin,
            'country' => $asinData->country,
        ]));

        $response->assertStatus(200);
        $this->assertFooterContentPresent($response);
    }

    #[\PHPUnit\Framework\attributes\Test]
    public function it_renders_footer_partial_on_product_not_found_page()
    {
        // Test with non-existent ASIN to trigger not-found page
        $response = $this->get(route('amazon.product.show', [
            'asin'    => 'NONEXISTENT',
            'country' => 'us',
        ]));

        // The not-found page returns 404 but still renders content
        $response->assertStatus(404);

        // Check that footer content is present even in 404 response
        $response->assertSee('built with');
        $response->assertSee('shift8 web');
        $response->assertSee('atomic edge firewall');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_footer_partial_on_products_index_page()
    {
        $response = $this->get(route('products.index'));

        $response->assertStatus(200);
        $this->assertFooterContentPresent($response);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_footer_partial_on_contact_page()
    {
        $response = $this->get('/contact');

        $response->assertStatus(200);
        $this->assertFooterContentPresent($response);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_footer_partial_on_privacy_page()
    {
        $response = $this->get('/privacy');

        $response->assertStatus(200);
        $this->assertFooterContentPresent($response);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function footer_partial_contains_all_required_elements()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Check for all footer elements
        $response->assertSee('Built with');
        $response->assertSee('Shift8 Web');
        $response->assertSee('https://shift8web.ca');
        $response->assertSee('Atomic Edge Firewall');
        $response->assertSee('https://atomicedge.io');
        $response->assertSee('Analyzed Products');
        $response->assertSee('GitHub');
        $response->assertSee('https://github.com/stardothosting/nullfake');
        $response->assertSee('MIT License');
        $response->assertSee('Privacy Policy');
        $response->assertSee('Contact');

        // Check for heart SVG (love icon)
        $response->assertSee('<svg xmlns="http://www.w3.org/2000/svg"', false);
        $response->assertSee('fill="currentColor"', false);

        // Check for proper link structure
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener"', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function footer_partial_has_consistent_styling()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Check for consistent CSS classes in the new footer design
        $response->assertSee('bg-gray-900');
        $response->assertSee('text-gray-300');
        $response->assertSee('text-indigo-400 hover:text-indigo-300');
        $response->assertSee('text-gray-400 hover:text-white');
        $response->assertSee('text-red-500'); // Heart color
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function footer_partial_includes_proper_accessibility_attributes()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Check for accessibility attributes
        $response->assertSee('aria-label="love"', false);
        $response->assertSee('title="love"', false);
        // New footer design uses different link structure
        $response->assertSee('rel="noopener"', false);
        $response->assertSee('target="_blank"', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function footer_partial_renders_without_duplication()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        $content = strtolower($response->getContent());

        // Count occurrences of key footer elements to ensure no duplication
        // Note: Case-insensitive check for footer elements
        $shiftWebCount = substr_count($content, 'shift8 web');
        $atomicEdgeCount = substr_count($content, 'atomic edge firewall');
        $githubLinkCount = substr_count($content, 'https://github.com/stardothosting/nullfake');

        $this->assertGreaterThanOrEqual(1, $shiftWebCount, 'Shift8 Web should appear at least once');
        $this->assertEquals(1, $atomicEdgeCount, 'Atomic Edge Firewall should appear exactly once');
        $this->assertGreaterThanOrEqual(1, $githubLinkCount, 'GitHub link should appear at least once');
    }

    /**
     * Helper method to assert footer content is present.
     */
    private function assertFooterContentPresent($response)
    {
        $response->assertSee('Built with');
        $response->assertSee('Shift8 Web');
        $response->assertSee('Atomic Edge Firewall');
        $response->assertSee('Analyzed Products');
        $response->assertSee('GitHub');
        $response->assertSee('MIT License');
        $response->assertSee('Privacy Policy');
        $response->assertSee('Contact');
        // Informational page links in footer
        $response->assertSee('How It Works');
        $response->assertSee('FAQ');
        $response->assertSee('Fakespot Alternative');
    }
}
