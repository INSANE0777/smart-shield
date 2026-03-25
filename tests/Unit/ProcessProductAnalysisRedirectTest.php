<?php

namespace Tests\Unit;

use App\Jobs\ProcessProductAnalysis;
use App\Models\AnalysisSession;
use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessProductAnalysisRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_when_analysis_complete_even_without_product_data()
    {
        // Create analysis session
        $session = AnalysisSession::create([
            'user_session' => 'test-session',
            'asin'         => 'B08B39N5CC',
            'product_url'  => 'https://amazon.com/dp/B08B39N5CC',
            'status'       => 'pending',
            'total_steps'  => 8,
        ]);

        // Create AsinData with completed analysis but no product data
        $asinData = AsinData::create([
            'asin'    => 'B08B39N5CC',
            'country' => 'us',
            'reviews' => json_encode([
                ['id' => 1, 'rating' => 5, 'review_text' => 'Great product', 'author' => 'John'],
                ['id' => 2, 'rating' => 4, 'review_text' => 'Good quality', 'author' => 'Jane'],
            ]),
            'status'            => 'completed',
            'fake_percentage'   => 0.0,
            'grade'             => 'A',
            'amazon_rating'     => 4.5,
            'adjusted_rating'   => 4.5,
            'explanation'       => 'Test explanation',
            'have_product_data' => false, // Key: no product data
            'product_title'     => 'Test Product from BrightData',
        ]);

        // Test determineRedirectUrl using reflection
        $job = new ProcessProductAnalysis($session->id, $session->product_url);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('determineRedirectUrl');
        $method->setAccessible(true);

        $redirectUrl = $method->invoke($job, $asinData);

        // Should redirect even without product data because analysis is complete with reviews
        $this->assertNotNull($redirectUrl);
        $this->assertStringContainsString('/amazon/us/B08B39N5CC', $redirectUrl);
    }

    #[Test]
    public function it_does_not_redirect_when_no_reviews()
    {
        // Create analysis session
        $session = AnalysisSession::create([
            'user_session' => 'test-session',
            'asin'         => 'B08NOREVIEWS',
            'product_url'  => 'https://amazon.com/dp/B08NOREVIEWS',
            'status'       => 'pending',
            'total_steps'  => 8,
        ]);

        // Create AsinData with no reviews
        $asinData = AsinData::create([
            'asin'              => 'B08NOREVIEWS',
            'country'           => 'us',
            'reviews'           => json_encode([]), // No reviews
            'status'            => 'completed',
            'fake_percentage'   => null,
            'grade'             => null,
            'have_product_data' => true,
            'product_title'     => 'Product with no reviews',
        ]);

        // Test determineRedirectUrl using reflection
        $job = new ProcessProductAnalysis($session->id, $session->product_url);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('determineRedirectUrl');
        $method->setAccessible(true);

        $redirectUrl = $method->invoke($job, $asinData);

        // Should not redirect because no meaningful analysis (no reviews)
        $this->assertNull($redirectUrl);
    }

    #[Test]
    public function it_does_not_redirect_when_analysis_incomplete()
    {
        // Create analysis session
        $session = AnalysisSession::create([
            'user_session' => 'test-session',
            'asin'         => 'B08INCOMPLETE',
            'product_url'  => 'https://amazon.com/dp/B08INCOMPLETE',
            'status'       => 'pending',
            'total_steps'  => 8,
        ]);

        // Create AsinData with reviews but incomplete analysis
        $asinData = AsinData::create([
            'asin'    => 'B08INCOMPLETE',
            'country' => 'us',
            'reviews' => json_encode([
                ['id' => 1, 'rating' => 5, 'review_text' => 'Great product', 'author' => 'John'],
            ]),
            'status'            => 'processing', // Not completed
            'fake_percentage'   => null, // Analysis not done
            'grade'             => null, // Analysis not done
            'have_product_data' => true,
            'product_title'     => 'Product with incomplete analysis',
        ]);

        // Test determineRedirectUrl using reflection
        $job = new ProcessProductAnalysis($session->id, $session->product_url);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('determineRedirectUrl');
        $method->setAccessible(true);

        $redirectUrl = $method->invoke($job, $asinData);

        // Should not redirect because analysis is not complete
        $this->assertNull($redirectUrl);
    }

    #[Test]
    public function it_redirects_to_slug_url_when_available()
    {
        // Create analysis session
        $session = AnalysisSession::create([
            'user_session' => 'test-session',
            'asin'         => 'B08WITHSLUG',
            'product_url'  => 'https://amazon.com/dp/B08WITHSLUG',
            'status'       => 'pending',
            'total_steps'  => 8,
        ]);

        // Create AsinData with product title that generates a slug
        $asinData = AsinData::create([
            'asin'    => 'B08WITHSLUG',
            'country' => 'us',
            'reviews' => json_encode([
                ['id' => 1, 'rating' => 5, 'review_text' => 'Amazing product', 'author' => 'John'],
            ]),
            'status'            => 'completed',
            'fake_percentage'   => 0.0,
            'grade'             => 'A',
            'amazon_rating'     => 5.0,
            'adjusted_rating'   => 5.0,
            'explanation'       => 'Test explanation',
            'have_product_data' => false,
            'product_title'     => 'Amazing Test Product Title', // Will generate slug
        ]);

        // Test determineRedirectUrl using reflection
        $job = new ProcessProductAnalysis($session->id, $session->product_url);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('determineRedirectUrl');
        $method->setAccessible(true);

        $redirectUrl = $method->invoke($job, $asinData);

        // Should redirect to slug URL
        $this->assertNotNull($redirectUrl);
        $this->assertStringContainsString('/amazon/us/B08WITHSLUG/amazing-test-product-title', $redirectUrl);
    }

    #[Test]
    public function it_redirects_to_basic_url_when_no_slug()
    {
        // Create analysis session
        $session = AnalysisSession::create([
            'user_session' => 'test-session',
            'asin'         => 'B08NOSLUG',
            'product_url'  => 'https://amazon.com/dp/B08NOSLUG',
            'status'       => 'pending',
            'total_steps'  => 8,
        ]);

        // Create AsinData without product title (no slug)
        $asinData = AsinData::create([
            'asin'    => 'B08NOSLUG',
            'country' => 'us',
            'reviews' => json_encode([
                ['id' => 1, 'rating' => 5, 'review_text' => 'Good product', 'author' => 'John'],
            ]),
            'status'            => 'completed',
            'fake_percentage'   => 0.0,
            'grade'             => 'A',
            'amazon_rating'     => 5.0,
            'adjusted_rating'   => 5.0,
            'explanation'       => 'Test explanation',
            'have_product_data' => false,
            'product_title'     => null, // No title, no slug
        ]);

        // Test determineRedirectUrl using reflection
        $job = new ProcessProductAnalysis($session->id, $session->product_url);
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('determineRedirectUrl');
        $method->setAccessible(true);

        $redirectUrl = $method->invoke($job, $asinData);

        // Should redirect to basic URL
        $this->assertNotNull($redirectUrl);
        $this->assertStringContainsString('/amazon/us/B08NOSLUG', $redirectUrl);
        $this->assertStringNotContainsString('/amazon/us/B08NOSLUG/', $redirectUrl); // No trailing slash for slug
    }
}
