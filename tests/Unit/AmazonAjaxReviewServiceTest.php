<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\Amazon\AmazonAjaxReviewService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AmazonAjaxReviewServiceTest extends TestCase
{
    private AmazonAjaxReviewService $service;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up mock environment
        config(['app.env' => 'testing']);
        putenv('AMAZON_COOKIES_1=test-cookie=test-value; session-id=test-session');

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);

        // Create service with mocked HTTP client
        $this->service = new AmazonAjaxReviewService();

        // Use reflection to inject mock client
        $reflection = new \ReflectionClass($this->service);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->service, new Client(['handler' => $handlerStack]));
    }

    protected function tearDown(): void
    {
        putenv('AMAZON_COOKIES_1');
        parent::tearDown();
    }

    #[Test]
    public function it_can_extract_csrf_token_from_html()
    {
        $html = '<span id="cr-state-object" data-state="{&quot;reviewsAjaxUrl&quot;:&quot;/hz/reviews-render/ajax/reviews/get/&quot;,&quot;reviewsCsrfToken&quot;:&quot;test-csrf-token&quot;,&quot;isArpPaginationDisabled&quot;:false}"></span>';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('extractCrStateObject');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $html);

        $this->assertNotNull($result);
        $this->assertEquals('/hz/reviews-render/ajax/reviews/get/', $result['reviewsAjaxUrl']);
        $this->assertEquals('test-csrf-token', $result['reviewsCsrfToken']);
        $this->assertFalse($result['isArpPaginationDisabled']);
    }

    #[Test]
    public function it_can_parse_reviews_from_html()
    {
        $html = '
            <div data-hook="review">
                <span data-hook="review-body"><span>This is a great product!</span></span>
                <span class="review-rating">5.0 out of 5 stars</span>
                <span data-hook="review-author">John Doe</span>
                <span data-hook="review-title">Excellent quality</span>
                <span data-hook="review-date">December 1, 2023</span>
                <span data-hook="avp-badge">Verified Purchase</span>
            </div>
        ';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('parseReviewsFromHtml');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $html, 'B123456789');

        $this->assertCount(1, $result);
        $this->assertEquals('This is a great product!', $result[0]['review_text']);
        $this->assertEquals(5.0, $result[0]['rating']);
        $this->assertEquals('John Doe', $result[0]['reviewer_name']);
        $this->assertEquals('Excellent quality', $result[0]['review_title']);
        $this->assertEquals('December 1, 2023', $result[0]['review_date']);
        $this->assertTrue($result[0]['verified_purchase']);
    }

    #[Test]
    public function it_handles_session_bootstrap_success()
    {
        $bootstrapHtml = $this->createMockBootstrapHtml();

        $this->mockHandler->append(
            new Response(200, [], $bootstrapHtml)
        );

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('bootstrapSession');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'B123456789');

        $this->assertNotNull($result);
        $this->assertEquals('/hz/reviews-render/ajax/reviews/get/', $result['ajax_url']);
        $this->assertEquals('test-csrf-token', $result['csrf_token']);
        $this->assertIsArray($result['page1_reviews']);
    }

    #[Test]
    public function it_handles_session_bootstrap_login_redirect()
    {
        $loginHtml = '<html><body>You are being redirected to ap/signin</body></html>';

        $this->mockHandler->append(
            new Response(200, [], $loginHtml)
        );

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('bootstrapSession');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'B123456789');

        $this->assertNull($result);
    }

    #[Test]
    public function it_can_fetch_ajax_page_with_json_response()
    {
        $ajaxResponse = [
            'html'       => '<div data-hook="review"><span data-hook="review-body"><span>AJAX review text</span></span></div>',
            'pagination' => ['hasNext' => true],
        ];

        $this->mockHandler->append(
            new Response(200, [], json_encode($ajaxResponse))
        );

        $sessionData = [
            'ajax_url'   => '/hz/reviews-render/ajax/reviews/get/',
            'csrf_token' => 'test-csrf-token',
            'base_url'   => 'https://www.amazon.com/product-reviews/B123456789',
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('fetchAjaxPage');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'B123456789', 2, $sessionData);

        $this->assertCount(1, $result);
        $this->assertEquals('AJAX review text', $result[0]['review_text']);
    }

    #[Test]
    public function it_handles_ajax_page_failure()
    {
        $this->mockHandler->append(
            new Response(500, [], 'Internal Server Error')
        );

        $sessionData = [
            'ajax_url'   => '/hz/reviews-render/ajax/reviews/get/',
            'csrf_token' => 'test-csrf-token',
            'base_url'   => 'https://www.amazon.com/product-reviews/B123456789',
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('fetchAjaxPage');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'B123456789', 2, $sessionData);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_fetches_reviews_with_ajax_pagination()
    {
        // Mock bootstrap response
        $bootstrapHtml = $this->createMockBootstrapHtml();
        $this->mockHandler->append(new Response(200, [], $bootstrapHtml));

        // Mock AJAX page responses
        $ajaxResponse1 = ['html' => $this->createMockReviewHtml('AJAX review 1')];
        $ajaxResponse2 = ['html' => $this->createMockReviewHtml('AJAX review 2')];
        $ajaxResponse3 = ['html' => '']; // Empty response to stop pagination

        $this->mockHandler->append(new Response(200, [], json_encode($ajaxResponse1)));
        $this->mockHandler->append(new Response(200, [], json_encode($ajaxResponse2)));
        $this->mockHandler->append(new Response(200, [], json_encode($ajaxResponse3)));

        $result = $this->service->fetchReviews('B123456789');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertGreaterThan(0, count($result['reviews']));
    }

    #[Test]
    public function it_implements_fetch_reviews_and_save()
    {
        // Mock successful bootstrap and AJAX responses
        $bootstrapHtml = $this->createMockBootstrapHtml();
        $this->mockHandler->append(new Response(200, [], $bootstrapHtml));

        $ajaxResponse = ['html' => $this->createMockReviewHtml('Test review')];
        $this->mockHandler->append(new Response(200, [], json_encode($ajaxResponse)));
        $this->mockHandler->append(new Response(200, [], json_encode(['html' => '']))); // Stop pagination

        $result = $this->service->fetchReviewsAndSave('B123456789', 'us', 'https://amazon.com/dp/B123456789');

        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B123456789', $result->asin);
    }

    #[Test]
    public function it_implements_fetch_product_data()
    {
        $bootstrapHtml = $this->createMockBootstrapHtml();
        $this->mockHandler->append(new Response(200, [], $bootstrapHtml));

        $result = $this->service->fetchProductData('B123456789');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('total_reviews', $result);
    }

    private function createMockBootstrapHtml(): string
    {
        return '
            <html>
                <head><title>Amazon Reviews</title></head>
                <body>
                    <span id="cr-state-object" data-state="{&quot;reviewsAjaxUrl&quot;:&quot;/hz/reviews-render/ajax/reviews/get/&quot;,&quot;reviewsCsrfToken&quot;:&quot;test-csrf-token&quot;,&quot;isArpPaginationDisabled&quot;:false}"></span>
                    <div data-hook="review">
                        <span data-hook="review-body"><span>Initial review from page 1</span></span>
                        <span class="review-rating">4.0 out of 5 stars</span>
                        <span data-hook="review-author">Test User</span>
                        <span data-hook="review-title">Great product</span>
                        <span data-hook="review-date">November 15, 2023</span>
                    </div>
                    <div class="feature">This is a test product description</div>
                    <span>1,500 customer reviews</span>
                </body>
            </html>
        ';
    }

    #[Test]
    public function it_uses_cookie_session_manager_for_rotation()
    {
        // Set up multiple cookie sessions
        putenv('AMAZON_COOKIES_1=cookie1=value1; session-id=session1');
        putenv('AMAZON_COOKIES_2=cookie2=value2; session-id=session2');

        // Create new service to pick up environment changes
        $service = new AmazonAjaxReviewService();

        // Use reflection to verify cookie session manager is initialized
        $reflection = new \ReflectionClass($service);
        $cookieSessionManagerProperty = $reflection->getProperty('cookieSessionManager');
        $cookieSessionManagerProperty->setAccessible(true);
        $cookieSessionManager = $cookieSessionManagerProperty->getValue($service);

        $this->assertNotNull($cookieSessionManager);
        $this->assertInstanceOf(\App\Services\Amazon\CookieSessionManager::class, $cookieSessionManager);

        // Clean up
        putenv('AMAZON_COOKIES_2');
    }

    #[Test]
    public function it_falls_back_to_legacy_cookies_when_no_sessions_available()
    {
        // Clear all cookie sessions
        putenv('AMAZON_COOKIES_1');
        putenv('AMAZON_COOKIE=legacy-cookie=legacy-value');

        // Create service with no cookie sessions
        $service = new AmazonAjaxReviewService();

        // Should not crash and should initialize properly
        $this->assertInstanceOf(AmazonAjaxReviewService::class, $service);

        // Clean up
        putenv('AMAZON_COOKIE');
    }

    #[Test]
    public function it_handles_captcha_in_ajax_responses()
    {
        // Mock successful bootstrap
        $bootstrapHtml = $this->createMockBootstrapHtml();
        $this->mockHandler->append(new Response(200, [], $bootstrapHtml));

        // Mock CAPTCHA in AJAX response
        $captchaResponse = 'unusual traffic detected - solve this puzzle';
        $this->mockHandler->append(new Response(200, [], $captchaResponse));

        $result = $this->service->fetchReviews('B123456789');

        // Should still return valid structure even with CAPTCHA in AJAX
        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertCount(1, $result['reviews']); // Only page 1 reviews
    }

    private function createMockReviewHtml(string $reviewText): string
    {
        return '
            <div data-hook="review">
                <span data-hook="review-body"><span>'.$reviewText.'</span></span>
                <span class="review-rating">5.0 out of 5 stars</span>
                <span data-hook="review-author">AJAX User</span>
                <span data-hook="review-title">AJAX Review</span>
                <span data-hook="review-date">December 1, 2023</span>
                <span data-hook="avp-badge">Verified Purchase</span>
            </div>
        ';
    }
}
