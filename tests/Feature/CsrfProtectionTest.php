<?php

namespace Tests\Feature;

use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    /**
     * Test that homepage has CSRF token available for form submissions.
     */
    public function test_homepage_has_csrf_protection()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Verify CSRF token meta tag is present
        $response->assertSee('<meta name="csrf-token"', false);

        // Verify session is started (required for CSRF)
        $this->assertNotNull(session()->getId());
    }

    /**
     * Test that contact page has CSRF protection.
     */
    public function test_contact_page_has_csrf_protection()
    {
        $response = $this->get('/contact');

        $response->assertStatus(200);

        // Verify CSRF token meta tag is present
        $response->assertSee('<meta name="csrf-token"', false);

        // Verify session is started
        $this->assertNotNull(session()->getId());
    }

    /**
     * Test that pages with forms maintain session middleware.
     * This test verifies that cookie headers are set, which indicates
     * session middleware is active.
     */
    public function test_pages_with_forms_have_session_middleware()
    {
        $pagesWithForms = [
            '/',        // Homepage with review analyzer form + CAPTCHA
            '/contact', // Contact form
        ];

        foreach ($pagesWithForms as $page) {
            $response = $this->get($page);

            $response->assertStatus(200);

            // Verify session cookie is set (indicates session middleware is active)
            $response->assertCookie(config('session.cookie'));

            // Verify CSRF token is available in the response
            $response->assertSee('csrf-token', false);
        }
    }

    /**
     * Test that CSRF middleware is active by verifying session exists.
     * CSRF protection requires session middleware to be active.
     */
    public function test_csrf_middleware_is_active_for_forms()
    {
        // Make request to homepage (which has forms)
        $response = $this->get('/');

        $response->assertStatus(200);

        // Verify session was started (CSRF requires session)
        $this->assertNotNull(session()->getId());

        // Verify CSRF token was generated
        $this->assertNotEmpty(csrf_token());

        // Verify session cookie was set in response
        $response->assertCookie(config('session.cookie'));
    }

    /**
     * Test that form submission with valid CSRF token succeeds (or fails validation, not CSRF).
     * This verifies CSRF middleware is working correctly.
     */
    public function test_form_submission_with_csrf_token_passes_csrf_check()
    {
        // Get CSRF token
        $response = $this->get('/contact');
        $csrfToken = csrf_token();

        // Submit form with CSRF token (will fail validation but not CSRF check)
        $response = $this->withSession(['_token' => $csrfToken])
            ->post('/contact', [
                '_token'  => $csrfToken,
                'name'    => '',
                'email'   => '',
                'message' => '',
            ]);

        // Should NOT be 419 (CSRF error)
        // Will be 302 redirect with validation errors or 422 validation error
        $this->assertNotEquals(419, $response->status());
    }

    /**
     * Test that static informational pages (without forms) can have
     * different caching headers, but pages with forms cannot.
     */
    public function test_pages_with_forms_have_no_cache_headers()
    {
        $pagesWithForms = [
            '/',        // Homepage with form
            '/contact', // Contact form
        ];

        foreach ($pagesWithForms as $page) {
            $response = $this->get($page);

            $response->assertStatus(200);

            // Pages with forms should have no-cache headers to prevent CSRF issues
            $cacheControl = $response->headers->get('Cache-Control');

            // Should contain 'no-cache' or 'private' (indicates session middleware is active)
            $this->assertTrue(
                str_contains($cacheControl, 'no-cache') || str_contains($cacheControl, 'private'),
                "Page {$page} with forms should have no-cache or private Cache-Control header. Got: {$cacheControl}"
            );
        }
    }

    /**
     * Test that static pages without forms can have public cache headers.
     * This is the safe pattern for CDN caching.
     */
    public function test_static_pages_without_forms_can_have_public_cache()
    {
        $staticPages = [
            '/privacy', // No forms, pure informational
        ];

        foreach ($staticPages as $page) {
            $response = $this->get($page);

            $response->assertStatus(200);

            // These pages should have cache headers that prevent CSRF issues
            // Either no-cache (with session middleware) OR no CSRF token if middleware excluded
            $cacheControl = $response->headers->get('Cache-Control');
            $content = $response->getContent();

            // If page has public cache, it should NOT have CSRF token
            if (str_contains($cacheControl, 'public')) {
                $this->assertStringNotContainsString(
                    'csrf-token',
                    $content,
                    "Page {$page} with public caching should not have CSRF token (indicates session middleware removed)"
                );
            }
        }
    }

    /**
     * Test that removing session middleware from pages with forms would be caught.
     * This is a regression test for the 2025-12-21 production incident.
     */
    public function test_homepage_captcha_form_has_csrf_protection()
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Homepage has review analyzer form with CAPTCHA
        // Verify it has all necessary CSRF protection components

        // 1. CSRF token meta tag
        $response->assertSee('<meta name="csrf-token"', false);

        // 2. Session cookie is set
        $response->assertCookie(config('session.cookie'));

        // 3. Cache headers indicate session middleware is active
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertTrue(
            str_contains($cacheControl, 'no-cache') || str_contains($cacheControl, 'private'),
            "Homepage with CAPTCHA form must have no-cache headers. Got: {$cacheControl}"
        );

        // 4. Livewire scripts should be loaded (indicates Livewire components present)
        $response->assertSee('livewire', false);
    }

    /**
     * Test that all routes are properly categorized and documented.
     * This helps prevent accidentally removing CSRF from wrong pages.
     */
    public function test_form_pages_are_documented()
    {
        // These pages MUST have session middleware and CSRF protection
        $pagesRequiringCsrf = [
            '/'        => 'Homepage with review analyzer form and CAPTCHA',
            '/contact' => 'Contact form',
        ];

        // These pages are SAFE to optimize for CDN (no forms)
        $safeStaticPages = [
            '/privacy'              => 'Privacy policy - no forms',
            '/faq'                  => 'FAQ - no forms',
            '/how-it-works'         => 'How It Works - no forms',
            '/fakespot-alternative' => 'Fakespot Alternative - no forms',
        ];

        // Verify pages requiring CSRF actually have it
        foreach ($pagesRequiringCsrf as $page => $description) {
            $response = $this->get($page);
            $response->assertStatus(200);

            $content = $response->getContent();
            $this->assertStringContainsString(
                'csrf-token',
                $content,
                "Page {$page} ({$description}) must have CSRF protection"
            );
        }

        // Verify safe static pages don't have forms
        foreach ($safeStaticPages as $page => $description) {
            $response = $this->get($page);
            $response->assertStatus(200);

            $content = $response->getContent();

            // These pages should not have POST forms (GET forms are ok)
            $hasPostForm = preg_match('/<form[^>]*method=["\']post["\']/i', $content);
            $this->assertEquals(
                0,
                $hasPostForm,
                "Page {$page} ({$description}) should not have POST forms"
            );
        }
    }
}

