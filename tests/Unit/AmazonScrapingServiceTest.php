<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\AlertManager;
use App\Services\Amazon\AmazonScrapingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AmazonScrapingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $mockHandler;
    private AmazonScrapingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up mock HTTP client
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Create service instance and inject mock client
        $this->service = new AmazonScrapingService();

        // Set the mock HTTP client
        $this->service->setHttpClient($mockClient);

        // Set test environment variables
        putenv('AMAZON_COOKIES=session-id=test123; session-token=test456');
    }

    public function test_fetch_reviews_and_save_success()
    {
        // Mock product page response
        $productHtml = $this->createMockProductHtml('Test Product Title');
        $this->mockHandler->append(new Response(200, [], $productHtml));

        // Mock review URL pattern testing (3 patterns, first one works)
        $reviewsHtml = $this->createMockReviewsHtml();
        $this->mockHandler->append(new Response(200, [], $reviewsHtml)); // First pattern works

        // Mock the actual reviews page request
        $this->mockHandler->append(new Response(200, [], $reviewsHtml));

        $result = $this->service->fetchReviewsAndSave('B08N5WRWNW', 'us', 'https://amazon.com/dp/B08N5WRWNW');

        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B08N5WRWNW', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertEquals('Test Product Title', $result->product_description);
        $this->assertNotNull($result->reviews);

        $reviews = json_decode($result->reviews, true);
        $this->assertIsArray($reviews);
        $this->assertGreaterThan(0, count($reviews));
    }

    public function test_fetch_reviews_and_save_product_not_exists()
    {
        // Mock empty product page response
        $this->mockHandler->append(new Response(404, [], 'Not Found'));

        // With improved error handling, this should now return AsinData object instead of exception
        $result = $this->service->fetchReviewsAndSave('B08N5WRWNW', 'us', 'https://amazon.com/dp/B08N5WRWNW');

        $this->assertInstanceOf(\App\Models\AsinData::class, $result);
        $this->assertEquals('B08N5WRWNW', $result->asin);
        $this->assertEquals('us', $result->country);
    }

    public function test_fetch_reviews_success()
    {
        // Mock product page response
        $productHtml = $this->createMockProductHtml('Test Product');
        $this->mockHandler->append(new Response(200, [], $productHtml));

        // Mock review URL pattern testing (3 patterns)
        $reviewsHtml = $this->createMockReviewsHtml();
        $this->mockHandler->append(new Response(404, [], 'Not Found')); // First pattern fails
        $this->mockHandler->append(new Response(200, [], $reviewsHtml)); // Second pattern works

        // Mock the actual reviews page request
        $this->mockHandler->append(new Response(200, [], $reviewsHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('total_reviews', $result);
        $this->assertEquals('Test Product', $result['description']);
        $this->assertGreaterThan(0, count($result['reviews']));
    }

    public function test_fetch_reviews_invalid_asin_format()
    {
        $result = $this->service->fetchReviews('INVALID123', 'us');
        $this->assertEquals([
            'reviews'       => [],
            'description'   => '',
            'total_reviews' => 0,
        ], $result);
    }

    public function test_fetch_reviews_product_page_error()
    {
        // Mock product page error
        $this->mockHandler->append(new Response(500, [], 'Server Error'));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');
        $this->assertEquals([
            'reviews'       => [],
            'description'   => '',
            'total_reviews' => 0,
        ], $result);
    }

    public function test_fetch_reviews_no_reviews_found()
    {
        // Mock product page response
        $productHtml = $this->createMockProductHtml('Test Product');
        $this->mockHandler->append(new Response(200, [], $productHtml));

        // Mock review URL pattern testing (3 patterns, first one works)
        $emptyReviewsHtml = '<html><body><div>No reviews found</div></body></html>';
        $this->mockHandler->append(new Response(200, [], $emptyReviewsHtml));

        // Mock empty reviews page response
        $this->mockHandler->append(new Response(200, [], $emptyReviewsHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');
        $this->assertEquals([
            'reviews'       => [],
            'description'   => 'Test Product',
            'total_reviews' => 0,
        ], $result);
    }

    public function test_fetch_reviews_with_cookie_expiration_detection()
    {
        // Mock AlertManager to verify failure recording
        $alertManager = Mockery::mock(AlertManager::class);
        $alertManager->shouldReceive('recordFailure')
            ->once()
            ->with(
                'Amazon Direct Scraping',
                'SESSION_EXPIRED',
                'Amazon scraping session may have expired - no reviews found',
                Mockery::on(function ($context) {
                    return $context['asin'] === 'B08N5WRWNW'
                        && $context['service'] === 'direct_scraping'
                        && $context['method'] === 'fetchReviews';
                })
            );

        $this->app->instance(AlertManager::class, $alertManager);

        // Mock product page response
        $productHtml = $this->createMockProductHtml('Test Product');
        $this->mockHandler->append(new Response(200, [], $productHtml));

        // Mock review URL pattern testing - all patterns fail (short content)
        $emptyReviewsHtml = '<html><body><div>No reviews found</div></body></html>';
        $this->mockHandler->append(new Response(404, [], 'Not Found')); // First pattern fails
        $this->mockHandler->append(new Response(404, [], 'Not Found')); // Second pattern fails
        $this->mockHandler->append(new Response(404, [], 'Not Found')); // Third pattern fails

        // Mock cookie expiration detection response
        $signInHtml = '<html><body><div>sign in to amazon</div></body></html>';
        $this->mockHandler->append(new Response(200, [], $signInHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');
        $this->assertEquals([
            'reviews'       => [],
            'description'   => 'Test Product', // Product page succeeds, so description is extracted
            'total_reviews' => 0,
        ], $result);
    }

    public function test_fetch_reviews_handles_multiple_pages()
    {
        // Mock product page response
        $productHtml = $this->createMockProductHtml('Test Product');
        $this->mockHandler->append(new Response(200, [], $productHtml));

        // Mock review URL pattern testing (3 patterns, first one works)
        $reviewsHtml1 = $this->createMockReviewsHtml(['Review 1', 'Review 2']);
        $this->mockHandler->append(new Response(200, [], $reviewsHtml1)); // First pattern works

        // Mock first reviews page
        $this->mockHandler->append(new Response(200, [], $reviewsHtml1));

        // Mock second reviews page
        $reviewsHtml2 = $this->createMockReviewsHtml(['Review 3', 'Review 4']);
        $this->mockHandler->append(new Response(200, [], $reviewsHtml2));

        // Mock empty third page (to stop pagination)
        $emptyReviewsHtml = '<html><body><div>No reviews found</div></body></html>';
        $this->mockHandler->append(new Response(200, [], $emptyReviewsHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertCount(4, $result['reviews']);
    }

    public function test_fetch_reviews_handles_network_exception()
    {
        // Mock network exception
        $this->mockHandler->append(new \GuzzleHttp\Exception\ConnectException(
            'Connection timeout',
            new \GuzzleHttp\Psr7\Request('GET', 'test')
        ));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');
        $this->assertEquals([
            'reviews'       => [],
            'description'   => '',
            'total_reviews' => 0,
        ], $result);
    }

    public function test_fetch_reviews_detects_captcha_and_handles_gracefully()
    {
        // Create CAPTCHA HTML response
        $captchaHtml = $this->createMockCaptchaHtml();

        // Mock product page returning CAPTCHA
        $this->mockHandler->append(new Response(200, [], $captchaHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        // Should return empty result when CAPTCHA is detected
        $this->assertEquals([
            'reviews'       => [],
            'description'   => '',
            'total_reviews' => 0,
        ], $result);
    }

    public function test_fetch_reviews_detects_small_content_with_captcha_indicators()
    {
        // Create small HTML response with CAPTCHA indicator
        $smallHtml = '<html><body><div>Service temporarily unavailable due to unusual traffic</div></body></html>';

        // Mock product page returning small content with CAPTCHA indicator
        $this->mockHandler->append(new Response(200, [], $smallHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        // Should return empty result when CAPTCHA indicators are detected
        $this->assertEquals([
            'reviews'       => [],
            'description'   => '',
            'total_reviews' => 0,
        ], $result);
    }

    public function test_fetch_reviews_and_save_handles_captcha()
    {
        // Mock CAPTCHA response
        $captchaHtml = $this->createMockCaptchaHtml();
        $this->mockHandler->append(new Response(200, [], $captchaHtml));

        $result = $this->service->fetchReviewsAndSave('B08N5WRWNW', 'us', 'https://amazon.com/dp/B08N5WRWNW');

        // Should still create AsinData record even with CAPTCHA
        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B08N5WRWNW', $result->asin);

        // Should have empty reviews due to CAPTCHA
        $reviews = json_decode($result->reviews, true);
        $this->assertEquals([], $reviews);
    }

    public function test_blocking_detection_with_status_codes()
    {
        // Mock product page response
        $productHtml = $this->createMockProductHtml('Test Product');
        $this->mockHandler->append(new Response(200, [], $productHtml));

        // Mock review URL pattern testing (first pattern works but returns blocking)
        $blockingHtml = '<html><body><div>Service temporarily unavailable due to unusual traffic</div></body></html>';
        $this->mockHandler->append(new Response(503, [], $blockingHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        // Should return empty result due to blocking
        $this->assertEquals([
            'reviews'       => [],
            'description'   => 'Test Product',
            'total_reviews' => 0,
        ], $result);
    }

    public function test_parses_review_data_correctly()
    {
        // Mock product page response
        $productHtml = $this->createMockProductHtml('Test Product');
        $this->mockHandler->append(new Response(200, [], $productHtml));

        // Mock review URL pattern testing (3 patterns, first one works)
        $reviewsHtml = $this->createMockReviewsHtml([
            'This is a great product! I love it.',
            'Not bad, but could be better.',
        ], [5, 3], ['John Doe', 'Jane Smith']);
        $this->mockHandler->append(new Response(200, [], $reviewsHtml)); // First pattern works

        // Mock the actual reviews page request
        $this->mockHandler->append(new Response(200, [], $reviewsHtml));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertCount(2, $result['reviews']);

        $review1 = $result['reviews'][0];
        $this->assertEquals(5, $review1['rating']);
        $this->assertStringContainsString('This is a great product! I love it.', $review1['review_text']);
        $this->assertStringContainsString('This is a great product! I love it.', $review1['text']); // Backward compatibility
        // Note: author and review_title removed for bandwidth optimization
        $this->assertArrayHasKey('id', $review1);

        // Verify bandwidth optimization: only essential fields present
        $this->assertArrayNotHasKey('author', $review1);
        $this->assertArrayNotHasKey('review_title', $review1);

        $review2 = $result['reviews'][1];
        $this->assertEquals(3, $review2['rating']);
        $this->assertStringContainsString('Not bad, but could be better.', $review2['review_text']);
        $this->assertStringContainsString('Not bad, but could be better.', $review2['text']); // Backward compatibility
        // Note: author and review_title removed for bandwidth optimization
        $this->assertArrayHasKey('id', $review2);

        // Verify bandwidth optimization: only essential fields present
        $this->assertArrayNotHasKey('author', $review2);
        $this->assertArrayNotHasKey('review_title', $review2);
    }

    /**
     * Create mock HTML for Amazon product page.
     */
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

    /**
     * Create mock CAPTCHA HTML that would trigger detection.
     */
    private function createMockCaptchaHtml(): string
    {
        return '
        <!DOCTYPE html>
        <html class="a-no-js" lang="en-us">
        <head>
            <meta charset="utf-8">
            <title>Amazon.com</title>
        </head>
        <body>
            <div class="a-container a-padding-double-large">
                <div class="a-row a-spacing-double-large">
                    <div class="a-box a-alert a-alert-info a-spacing-base">
                        <div class="a-box-inner">
                            <h4>Click the button below to continue shopping</h4>
                        </div>
                    </div>
                    <form method="get" action="/errors/validateCaptcha" name="">
                        <input type="hidden" name="amzn" value="test123" />
                        <input type="hidden" name="amzn-r" value="&#047;dp&#047;B08N5WRWNW" />
                        <button type="submit" class="a-button-text">Continue shopping</button>
                    </form>
                </div>
            </div>
            <script src="https://opfcaptcha.amazon.com/csm-captcha-instrumentation.min.js"></script>
        </body>
        </html>';
    }

    /**
     * Create mock HTML for Amazon reviews page.
     */
    private function createMockReviewsHtml(?array $reviewTexts = null, ?array $ratings = null, ?array $authors = null): string
    {
        $reviewTexts = $reviewTexts ?? ['Great product!', 'Good value for money'];
        $ratings = $ratings ?? [5, 4];
        $authors = $authors ?? ['Customer 1', 'Customer 2'];

        // Create a large HTML document that exceeds 2000 bytes threshold
        $reviewsHtml = '<html><head><title>Amazon Product Reviews</title>';
        $reviewsHtml .= '<meta charset="utf-8">';
        $reviewsHtml .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $reviewsHtml .= '<style>body { font-family: Arial, sans-serif; }</style>';
        $reviewsHtml .= '</head><body>';
        $reviewsHtml .= '<div class="reviews-container">';
        $reviewsHtml .= '<h1>Customer Reviews</h1>';
        $reviewsHtml .= '<div class="review-summary">Based on '.count($reviewTexts).' reviews</div>';

        for ($i = 0; $i < count($reviewTexts); $i++) {
            $rating = $ratings[$i] ?? 5;
            $text = $reviewTexts[$i];
            $author = $authors[$i] ?? 'Anonymous';

            $reviewsHtml .= "
            <div data-hook='review' class='review-item' id='review-{$i}'>
                <div class='review-header'>
                    <div class='review-rating'>
                        <i data-hook='review-star-rating' class='a-icon a-icon-star a-star-{$rating} review-rating'>
                            <span class='a-icon-alt'>{$rating}.0 out of 5 stars</span>
                        </i>
                    </div>
                    <div data-hook='review-title' class='review-title'>
                        <span>Great title for review {$i} - Excellent product quality and value</span>
                    </div>
                </div>
                <div class='review-meta'>
                    <span class='review-date'>Reviewed on January ".(15 + $i).", 2024</span>
                    <span class='verified-purchase'>Verified Purchase</span>
                </div>
                <div data-hook='review-body' class='review-body'>
                    <div data-hook='review-collapsed' class='review-text'>
                        <span>{$text} This is additional padding text to make the HTML larger for testing purposes. The review content needs to be substantial enough to pass validation checks.</span>
                    </div>
                </div>
                <div class='review-footer'>
                    <div data-hook='review-author' class='review-author'>
                        <span class='a-profile-name'>{$author}</span>
                    </div>
                    <div class='review-actions'>
                        <button class='helpful-button'>Helpful</button>
                        <button class='report-button'>Report</button>
                    </div>
                </div>
            </div>
            ";
        }

        // Add some padding content to ensure we exceed 2000 bytes
        $reviewsHtml .= str_repeat('<div class="padding-content">Additional content for testing</div>', 10);
        $reviewsHtml .= '</div></body></html>';

        return $reviewsHtml;
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('AMAZON_COOKIES');

        parent::tearDown();
    }
}
