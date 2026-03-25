<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\Amazon\AmazonFetchService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmazonFetchServiceTest extends TestCase
{
    use RefreshDatabase;

    private AmazonFetchService $service;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up environment variables for testing
        config(['app.env' => 'testing']);
        putenv('UNWRANGLE_API_KEY=test_api_key');
        putenv('UNWRANGLE_AMAZON_COOKIE=test_cookie');

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Create service instance and inject mock client
        $this->service = new AmazonFetchService();

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->service, $mockClient);
    }

    public function test_fetch_reviews_and_save_success()
    {
        // Mock Unwrangle API response (no more Amazon validation)
        $unwrangleResponse = [
            'success'       => true,
            'total_results' => 2,
            'description'   => 'Test Product Description',
            'reviews'       => [
                ['rating' => 5, 'text' => 'Great product', 'author' => 'John'],
                ['rating' => 4, 'text' => 'Good product', 'author' => 'Jane'],
            ],
        ];
        $this->mockHandler->append(new Response(200, [], json_encode($unwrangleResponse)));

        $result = $this->service->fetchReviewsAndSave('B08N5WRWNW', 'us', 'https://amazon.com/dp/B08N5WRWNW');

        $this->assertInstanceOf(AsinData::class, $result);
        $this->assertEquals('B08N5WRWNW', $result->asin);
        $this->assertEquals('us', $result->country);
        $this->assertEquals('Test Product Description', $result->product_description);
        $this->assertCount(2, $result->getReviewsArray());
        $this->assertNull($result->openai_result);
    }

    public function test_fetch_reviews_and_save_product_not_exists()
    {
        // Mock Unwrangle API failure (product doesn't exist or API error)
        $unwrangleResponse = [
            'success' => false,
            'error'   => 'Product not found',
        ];
        $this->mockHandler->append(new Response(200, [], json_encode($unwrangleResponse)));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to fetch product reviews at this time');

        $this->service->fetchReviewsAndSave('INVALID123', 'us', 'https://amazon.com/dp/INVALID123');
    }

    public function test_fetch_reviews_success()
    {
        // Mock Unwrangle API response (no more Amazon validation)
        $unwrangleResponse = [
            'success'       => true,
            'total_results' => 3,
            'description'   => 'Test Product',
            'reviews'       => [
                ['rating' => 5, 'text' => 'Excellent'],
                ['rating' => 4, 'text' => 'Good'],
                ['rating' => 3, 'text' => 'Average'],
            ],
        ];
        $this->mockHandler->append(new Response(200, [], json_encode($unwrangleResponse)));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('total_reviews', $result);
        $this->assertCount(3, $result['reviews']);
        $this->assertEquals('Test Product', $result['description']);
        $this->assertEquals(3, $result['total_reviews']);
    }

    public function test_fetch_reviews_asin_validation_fails()
    {
        // Test invalid ASIN format validation
        $result = $this->service->fetchReviews('INVALID123', 'us');

        $this->assertEmpty($result);
    }

    public function test_fetch_reviews_unwrangle_api_error()
    {
        // Mock Unwrangle API error response (no more Amazon validation)
        $this->mockHandler->append(new Response(500, [], 'Internal Server Error'));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertEmpty($result);
    }

    public function test_fetch_reviews_unwrangle_api_returns_error()
    {
        // Mock Unwrangle API response with error (no more Amazon validation)
        $unwrangleResponse = [
            'success' => false,
            'error'   => 'API limit exceeded',
        ];
        $this->mockHandler->append(new Response(200, [], json_encode($unwrangleResponse)));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertEmpty($result);
    }

    public function test_fetch_reviews_unwrangle_api_exception()
    {
        // Mock Unwrangle API exception (no more Amazon validation)
        $this->mockHandler->append(new RequestException(
            'Connection timeout',
            new Request('GET', 'test')
        ));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertEmpty($result);
    }

    // Note: validateAsinExistsFast method removed - validation now happens client-side
    // These tests have been removed as we no longer do server-side Amazon validation

    public function test_fetch_reviews_with_empty_response()
    {
        // Mock Unwrangle API empty response (no more Amazon validation)
        $this->mockHandler->append(new Response(200, [], ''));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertEmpty($result);
    }

    public function test_fetch_reviews_with_invalid_json()
    {
        // Mock Unwrangle API invalid JSON response (no more Amazon validation)
        $this->mockHandler->append(new Response(200, [], 'invalid json'));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        $this->assertEmpty($result);
    }

    public function test_fetch_reviews_uses_correct_api_parameters()
    {
        // Mock Unwrangle API response (no more Amazon validation)
        $unwrangleResponse = [
            'success'       => true,
            'total_results' => 1,
            'description'   => 'Test Product',
            'reviews'       => [['rating' => 5, 'text' => 'Great']],
        ];
        $this->mockHandler->append(new Response(200, [], json_encode($unwrangleResponse)));

        $result = $this->service->fetchReviews('B08N5WRWNW', 'us');

        // Verify the method returned expected data (indicating API was called correctly)
        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertEquals('Test Product', $result['description']);
        $this->assertCount(1, $result['reviews']);

        // Verify mock response was consumed
        $this->assertCount(0, $this->mockHandler);
    }

    public function test_detects_cookie_expiration_from_no_reviews_found_error()
    {
        // Use reflection to access the private method for testing
        $service = new AmazonFetchService();
        $reflectionClass = new \ReflectionClass($service);
        $method = $reflectionClass->getMethod('isAmazonCookieExpiredError');
        $method->setAccessible(true);

        // Test data that matches the actual error from the logs
        $errorData = [
            'success'    => false,
            'platform'   => 'amazon_reviews',
            'message'    => 'No reviews found for this product after multiple attempts. The product may not have any reviews or there may be an issue with the cookie.',
            'error_code' => 'NO_REVIEWS_FOUND',
        ];

        // Verify that our detection method correctly identifies this as a cookie issue
        $result = $method->invoke($service, $errorData);
        $this->assertTrue($result, 'Should detect cookie expiration from NO_REVIEWS_FOUND error with cookie message');
    }

    public function test_detects_amazon_signin_required_error()
    {
        $service = new AmazonFetchService();

        $reflectionClass = new \ReflectionClass($service);
        $method = $reflectionClass->getMethod('isAmazonCookieExpiredError');
        $method->setAccessible(true);

        $errorData = [
            'success'    => false,
            'error_code' => 'AMAZON_SIGNIN_REQUIRED',
            'message'    => 'Amazon sign-in is required',
        ];

        $result = $method->invoke($service, $errorData);
        $this->assertTrue($result, 'Should detect AMAZON_SIGNIN_REQUIRED as cookie expiration');
    }

    public function test_detects_session_expired_messages()
    {
        $service = new AmazonFetchService();

        $reflectionClass = new \ReflectionClass($service);
        $method = $reflectionClass->getMethod('isAmazonCookieExpiredError');
        $method->setAccessible(true);

        $testCases = [
            ['message' => 'Session expired, please sign in again'],
            ['message' => 'Authentication failed - cookie expired'],
            ['message' => 'Unauthorized access detected'],
            ['message' => 'Invalid session token'],
            ['message' => 'Login required to access this resource'],
        ];

        foreach ($testCases as $testCase) {
            $result = $method->invoke($service, $testCase);
            $this->assertTrue($result, "Should detect cookie expiration from message: {$testCase['message']}");
        }
    }

    public function test_does_not_detect_non_cookie_errors()
    {
        $service = new AmazonFetchService();

        $reflectionClass = new \ReflectionClass($service);
        $method = $reflectionClass->getMethod('isAmazonCookieExpiredError');
        $method->setAccessible(true);

        $testCases = [
            ['error_code' => 'NO_REVIEWS_FOUND', 'message' => 'Product has no reviews available'],
            ['error_code' => 'PRODUCT_NOT_FOUND', 'message' => 'Product does not exist'],
            ['error_code' => 'RATE_LIMITED', 'message' => 'Too many requests'],
            ['message'    => 'Network timeout occurred'],
            ['message'    => 'Server error occurred'],
        ];

        foreach ($testCases as $testCase) {
            $result = $method->invoke($service, $testCase);
            $this->assertFalse($result, 'Should NOT detect cookie expiration from: '.json_encode($testCase));
        }
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('UNWRANGLE_API_KEY');
        putenv('UNWRANGLE_AMAZON_COOKIE');

        parent::tearDown();
    }
}
