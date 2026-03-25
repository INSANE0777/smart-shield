<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InformationalPagesTest extends TestCase
{
    #[Test]
    public function how_it_works_page_loads_successfully()
    {
        $response = $this->get('/how-it-works');

        $response->assertStatus(200);
        $response->assertSee('How SMART SHIELD UI Detects Fake Reviews');
        $response->assertSee('The Analysis Process');
        $response->assertSee('Data Collection');
        $response->assertSee('Natural Language Processing');
    }

    #[Test]
    public function fakespot_alternative_page_loads_successfully()
    {
        $response = $this->get('/fakespot-alternative');

        $response->assertStatus(200);
        $response->assertSee('Fakespot Alternative');
        $response->assertSee('Why Choose SMART SHIELD UI');
        $response->assertSee('Feature Comparison');
    }

    #[Test]
    public function faq_page_loads_successfully()
    {
        $response = $this->get('/faq');

        $response->assertStatus(200);
        $response->assertSee('Frequently Asked Questions');
        $response->assertSee('Is SMART SHIELD UI really free?');
        $response->assertSee('How accurate is SMART SHIELD UI?');
    }

    #[Test]
    public function all_informational_pages_include_json_ld_structured_data()
    {
        $pages = [
            '/how-it-works',
            '/fakespot-alternative',
            '/faq',
        ];

        foreach ($pages as $page) {
            $response = $this->get($page);

            $response->assertStatus(200);
            $response->assertSee('application/ld+json', false);
            $response->assertSee('@context', false);
            $response->assertSee('schema.org', false);
        }
    }

    #[Test]
    public function all_informational_pages_include_comprehensive_meta_tags()
    {
        $pages = [
            '/how-it-works'         => ['How SMART SHIELD UI Detects', 'Analysis Process'],
            '/fakespot-alternative' => ['Fakespot Alternative', 'Why Choose'],
            '/faq'                  => ['Frequently Asked Questions', 'Is SMART SHIELD UI really free'],
        ];

        foreach ($pages as $url => $expectedContent) {
            $response = $this->get($url);

            $response->assertStatus(200);

            // Verify core content is present
            foreach ($expectedContent as $content) {
                $response->assertSee($content);
            }

            // Verify standard meta tags
            $response->assertSee('<meta name="description"', false);
            $response->assertSee('<meta name="keywords"', false);
            $response->assertSee('<link rel="canonical"', false);
        }
    }

    #[Test]
    public function all_informational_pages_are_included_in_sitemap()
    {
        $response = $this->get('/sitemap-static.xml');

        $response->assertStatus(200);
        $response->assertSee('/how-it-works');
        $response->assertSee('/fakespot-alternative');
        $response->assertSee('/faq');
    }

    #[Test]
    public function all_informational_pages_include_proper_seo_meta_tags()
    {
        $pages = [
            '/how-it-works'         => 'How It Works',
            '/fakespot-alternative' => 'Fakespot Alternative',
            '/faq'                  => 'FAQ',
        ];

        foreach ($pages as $url => $expectedTitle) {
            $response = $this->get($url);

            $response->assertStatus(200);
            $response->assertSee('<meta name="description"', false);
            $response->assertSee('<meta name="robots"', false);
            $response->assertSee('<link rel="canonical"', false);
            $response->assertSee('og:title', false);
            $response->assertSee($expectedTitle);
        }
    }

    #[Test]
    public function informational_pages_include_header_and_footer()
    {
        $pages = [
            '/how-it-works',
            '/fakespot-alternative',
            '/faq',
        ];

        foreach ($pages as $page) {
            $response = $this->get($page);

            $response->assertStatus(200);
            // Check for header navigation
            $response->assertSee('Home');
            $response->assertSee('All Products');
            $response->assertSee('Contact');

            // Check for footer
            $response->assertSee('Built with');
            $response->assertSee('shift8web.ca');
        }
    }

    #[Test]
    public function faq_page_includes_proper_faq_schema()
    {
        $response = $this->get('/faq');

        $response->assertStatus(200);
        $response->assertSee('"@type": "FAQPage"', false);
        $response->assertSee('"@type": "Question"', false);
        $response->assertSee('acceptedAnswer', false);
    }

    #[Test]
    public function how_it_works_page_includes_howto_schema()
    {
        $response = $this->get('/how-it-works');

        $response->assertStatus(200);
        $response->assertSee('"@type": "HowTo"', false);
        $response->assertSee('"@type": "HowToStep"', false);
    }
}
