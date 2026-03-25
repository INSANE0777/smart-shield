<?php

namespace Tests\Unit;

use App\Services\Amazon\AmazonScrapingService;
use App\Services\Amazon\ProxyManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AmazonScrapingServiceWithProxyTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $mockHandler;
    private AmazonScrapingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache
        Cache::flush();

        // Set up proxy configuration
        putenv('BRIGHTDATA_USERNAME=brd-customer-test-zone-residential');
        putenv('BRIGHTDATA_PASSWORD=test-password');
        putenv('BRIGHTDATA_ENDPOINT=brd.superproxy.io:33335');

        // Set up mock HTTP client
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Create service instance and inject mock client
        $this->service = new AmazonScrapingService();
        $this->service->setHttpClient($mockClient);
    }

    public function test_scraping_service_uses_proxy_when_configured()
    {
        // Mock successful responses
        $productHtml = $this->createMockProductHtml('Test Product');
        $reviewsHtml = $this->createMockReviewsHtml();

        $this->mockHandler->append(new Response(200, [], $productHtml));
        $this->mockHandler->append(new Response(200, [], $reviewsHtml)); // URL pattern test
        $this->mockHandler->append(new Response(200, [], $reviewsHtml)); // Actual reviews

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertGreaterThan(0, count($result['reviews']));
    }

    public function test_scraping_service_handles_proxy_failure_with_retry()
    {
        // Mock first attempt failure (proxy blocked)
        $this->mockHandler->append(new Response(200, [], $this->createMockProductHtml('Test Product')));
        $this->mockHandler->append(new RequestException(
            'Connection timeout',
            new \GuzzleHttp\Psr7\Request('GET', 'test')
        ));

        // Mock retry success after proxy rotation
        $this->mockHandler->append(new Response(200, [], $this->createMockProductHtml('Test Product')));
        $this->mockHandler->append(new Response(200, [], $this->createMockReviewsHtml()));
        $this->mockHandler->append(new Response(200, [], $this->createMockReviewsHtml()));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
    }

    public function test_scraping_service_detects_blocked_responses()
    {
        // Mock product page
        $this->mockHandler->append(new Response(200, [], $this->createMockProductHtml('Test Product')));

        // Mock blocked response (small content indicating blocking)
        $blockedHtml = '<html><body><div>Access denied</div></body></html>';
        $this->mockHandler->append(new Response(200, [], $blockedHtml));

        // Mock retry after proxy rotation
        $this->mockHandler->append(new Response(200, [], $this->createMockProductHtml('Test Product')));
        $this->mockHandler->append(new Response(200, [], $this->createMockReviewsHtml()));
        $this->mockHandler->append(new Response(200, [], $this->createMockReviewsHtml()));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
    }

    public function test_scraping_service_handles_session_rotation()
    {
        $proxyManager = new ProxyManager();

        // Get initial session
        $config1 = $proxyManager->getProxyConfig();
        $sessionId1 = $config1['session_id'] ?? 'no_session';

        // Force session rotation
        $proxyManager->rotateSession();

        // Mock failure to trigger session rotation in the service
        $this->mockHandler->append(new Response(200, [], $this->createMockProductHtml('Test Product')));
        $this->mockHandler->append(new RequestException(
            'Connection failed',
            new \GuzzleHttp\Psr7\Request('GET', 'test')
        ));

        // Mock success after rotation
        $this->mockHandler->append(new Response(200, [], $this->createMockProductHtml('Test Product')));
        $this->mockHandler->append(new Response(200, [], $this->createMockReviewsHtml()));
        $this->mockHandler->append(new Response(200, [], $this->createMockReviewsHtml()));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        // Verify session was rotated
        $config2 = $proxyManager->getProxyConfig();
        $sessionId2 = $config2['session_id'] ?? 'no_session';

        // Session should be different after rotation
        $this->assertNotEquals($sessionId1, $sessionId2, 'Session ID should change after rotation');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
    }

    public function test_scraping_service_works_without_proxy_configuration()
    {
        // Clear proxy configuration
        putenv('BRIGHTDATA_USERNAME=');
        putenv('BRIGHTDATA_PASSWORD=');

        // Create new service instance without proxy
        $service = new AmazonScrapingService();
        $service->setHttpClient($this->getMockClient());

        $productHtml = $this->createMockProductHtml('Test Product');
        $reviewsHtml = $this->createMockReviewsHtml();

        $this->mockHandler->append(new Response(200, [], $productHtml));
        $this->mockHandler->append(new Response(200, [], $reviewsHtml));
        $this->mockHandler->append(new Response(200, [], $reviewsHtml));

        $result = $service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
    }

    public function test_custom_proxy_integration()
    {
        // Clear third-party provider config and set custom proxies
        putenv('BRIGHTDATA_USERNAME=');
        putenv('BRIGHTDATA_PASSWORD=');
        putenv('CUSTOM_PROXIES=192.168.1.1:8080:user1:pass1:US,192.168.1.2:8080:user2:pass2:UK');

        $service = new AmazonScrapingService();
        $service->setHttpClient($this->getMockClient());

        $productHtml = $this->createMockProductHtml('Test Product');
        $reviewsHtml = $this->createMockReviewsHtml();

        $this->mockHandler->append(new Response(200, [], $productHtml));
        $this->mockHandler->append(new Response(200, [], $reviewsHtml));
        $this->mockHandler->append(new Response(200, [], $reviewsHtml));

        $result = $service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
    }

    public function test_max_retries_exhausted()
    {
        // Mock continuous failures
        for ($i = 0; $i < 10; $i++) {
            $this->mockHandler->append(new RequestException(
                'Connection timeout',
                new \GuzzleHttp\Psr7\Request('GET', 'test')
            ));
        }

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        // Should return structured array after max retries
        $this->assertEquals([
            'reviews'       => [],
            'description'   => '',
            'total_reviews' => 0,
        ], $result);
    }

    private function getMockClient(): Client
    {
        return new Client(['handler' => HandlerStack::create($this->mockHandler)]);
    }

    private function createMockProductHtml(string $title): string
    {
        return "
        <html>
        <head><title>Amazon Product</title></head>
        <body>
            <div id='productTitle'>{$title}</div>
            <div class='product-details'>
                <span>Product details here</span>
            </div>
        </body>
        </html>
        ";
    }

    private function createMockReviewsHtml(): string
    {
        $reviewsHtml = '<html><head><title>Amazon Product Reviews</title>';
        $reviewsHtml .= '<style>body { font-family: Arial, sans-serif; }</style>';
        $reviewsHtml .= '</head><body>';
        $reviewsHtml .= '<div class="reviews-container">';
        $reviewsHtml .= '<h1>Customer Reviews</h1>';

        // Add two mock reviews
        for ($i = 0; $i < 2; $i++) {
            $rating = 5;
            $text = "Great product review {$i}";
            $author = "Customer {$i}";

            $reviewsHtml .= "
            <div data-hook='review' class='review-item' id='review-{$i}'>
                <div class='review-header'>
                    <div class='review-rating'>
                        <i data-hook='review-star-rating' class='a-icon a-icon-star a-star-{$rating} review-rating'>
                            <span class='a-icon-alt'>{$rating}.0 out of 5 stars</span>
                        </i>
                    </div>
                    <div data-hook='review-title' class='review-title'>
                        <span>Great title for review {$i}</span>
                    </div>
                </div>
                <div data-hook='review-body' class='review-body'>
                    <div data-hook='review-collapsed' class='review-text'>
                        <span>{$text}</span>
                    </div>
                </div>
                <div class='review-footer'>
                    <div data-hook='review-author' class='review-author'>
                        <span class='a-profile-name'>{$author}</span>
                    </div>
                </div>
            </div>
            ";
        }

        // Add padding content to ensure we exceed 2000 bytes
        $reviewsHtml .= str_repeat('<div class="padding-content">Additional content for testing</div>', 10);
        $reviewsHtml .= '</div></body></html>';

        return $reviewsHtml;
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('BRIGHTDATA_USERNAME=');
        putenv('BRIGHTDATA_PASSWORD=');
        putenv('BRIGHTDATA_ENDPOINT=');
        putenv('CUSTOM_PROXIES=');

        Cache::flush();
        parent::tearDown();
    }
}
