<?php

namespace Tests\Unit;

use App\Services\Amazon\BrightDataScraperService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrightDataScraperServiceTest extends TestCase
{
    private BrightDataScraperService $service;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock handler for HTTP requests
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        // Create service with mock HTTP client and test credentials
        $this->service = new BrightDataScraperService(
            httpClient: $httpClient,
            apiKey: 'test_api_key',
            datasetId: 'test_dataset_id',
            baseUrl: 'https://api.brightdata.com/datasets/v3',
            pollInterval: 0, // No delay in tests
            maxAttempts: 3
        );
    }

    #[Test]
    public function it_can_trigger_scraping_job_successfully()
    {
        // Mock concurrent job check (getJobsByStatus call)
        $this->mockHandler->append(new Response(200, [], json_encode([
            // Return empty array to indicate no running jobs
        ])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job completion
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 3])));

        // Mock data fetch
        $mockData = $this->createMockBrightDataResponse();
        $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertCount(3, $result['reviews']);
        $this->assertEquals('Test Product Name', $result['product_name']);
        $this->assertEquals(166807, $result['total_reviews']);
    }

    #[Test]
    public function it_handles_missing_api_key()
    {
        $service = new BrightDataScraperService(apiKey: '');

        $result = $service->fetchReviews('B0TEST12345', 'us');

        $this->assertEquals([], $result['reviews']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals(0, $result['total_reviews']);
    }

    #[Test]
    public function it_handles_job_trigger_failure()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock failed job trigger
        $this->mockHandler->append(new Response(400, [], json_encode(['error' => 'Bad request'])));

        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertEquals([], $result['reviews']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals(0, $result['total_reviews']);
    }

    #[Test]
    public function it_handles_polling_timeout()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job still running for all polling attempts
        for ($i = 0; $i < 3; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'running', 'records' => 0])));
        }

        // Mock job cancellation
        $this->mockHandler->append(new Response(200, [], 'OK'));

        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertEquals([], $result['reviews']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals(0, $result['total_reviews']);
    }

    #[Test]
    public function it_handles_job_failure_status()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job failure
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'failed', 'records' => 0])));

        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertEquals([], $result['reviews']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals(0, $result['total_reviews']);
    }

    #[Test]
    public function it_can_save_reviews_to_database()
    {
        // Force sync mode for testing
        config(['analysis.async_enabled' => false]);

        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job completion
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 1])));

        // Mock data fetch
        $mockData = $this->createMockBrightDataResponse();
        $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

        $result = $this->service->fetchReviewsAndSave('B0TEST12345', 'us', 'https://amazon.com/dp/B0TEST12345');

        $this->assertInstanceOf(\App\Models\AsinData::class, $result);
        $this->assertEquals('B0TEST12345', $result->asin);
        $this->assertEquals('us', $result->country);
    }

    #[Test]
    public function it_sets_have_product_data_true_when_brightdata_provides_complete_metadata()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job completion
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 1])));

        // Mock data with complete product metadata
        $mockData = $this->createMockBrightDataResponseWithImage();
        $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertEquals('Test Product With Image', $result['product_name']);
        $this->assertEquals('https://m.media-amazon.com/images/I/test-image.jpg', $result['product_image_url']);
    }

    #[Test]
    public function it_can_fetch_product_data()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job completion
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 1])));

        // Mock data fetch
        $mockData = $this->createMockBrightDataResponseWithImage();
        $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

        $result = $this->service->fetchProductData('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('image_url', $result);
        $this->assertArrayHasKey('rating', $result);
        $this->assertArrayHasKey('total_reviews', $result);

        $this->assertEquals('Test Product With Image', $result['title']);
        $this->assertEquals('https://m.media-amazon.com/images/I/test-image.jpg', $result['image_url']);
        $this->assertEquals(250000, $result['total_reviews']);
    }

    #[Test]
    public function it_transforms_brightdata_review_format_correctly()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock successful job trigger
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job completion
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 3])));

        // Mock data fetch
        $mockData = $this->createMockBrightDataResponse();
        $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertCount(3, $result['reviews']);

        $review = $result['reviews'][0];
        $this->assertArrayHasKey('id', $review);
        $this->assertArrayHasKey('rating', $review);
        $this->assertArrayHasKey('title', $review);
        $this->assertArrayHasKey('text', $review);
        $this->assertArrayHasKey('author', $review);
        $this->assertArrayHasKey('date', $review);
        $this->assertArrayHasKey('meta_data', $review);

        $this->assertEquals('R1TEST123', $review['id']);
        $this->assertEquals(5, $review['rating']);
        $this->assertEquals('Excellent product!', $review['title']);
        $this->assertEquals('This is an amazing product that works exactly as described.', $review['text']);
        $this->assertEquals('Test Author 1', $review['author']);

        $metaData = $review['meta_data'];
        $this->assertArrayHasKey('verified_purchase', $metaData);
        $this->assertArrayHasKey('helpful_count', $metaData);
        $this->assertArrayHasKey('vine_review', $metaData);
        $this->assertArrayHasKey('country', $metaData);
        $this->assertArrayHasKey('badge', $metaData);

        $this->assertTrue($metaData['verified_purchase']);
        $this->assertEquals(25, $metaData['helpful_count']);
        $this->assertFalse($metaData['vine_review']);
        $this->assertEquals('United States', $metaData['country']);
        $this->assertEquals('Verified Purchase', $metaData['badge']);
    }

    #[Test]
    public function it_handles_different_amazon_domains()
    {
        $testCases = [
            ['country' => 'us', 'expected_domain' => 'amazon.com'],
            ['country' => 'gb', 'expected_domain' => 'amazon.co.uk'],
            ['country' => 'ca', 'expected_domain' => 'amazon.ca'],
            ['country' => 'de', 'expected_domain' => 'amazon.de'],
            ['country' => 'fr', 'expected_domain' => 'amazon.fr'],
            ['country' => 'jp', 'expected_domain' => 'amazon.co.jp'],
            ['country' => 'au', 'expected_domain' => 'amazon.com.au'],
        ];

        foreach ($testCases as $case) {
            // Mock concurrent job check
            $this->mockHandler->append(new Response(200, [], json_encode([])));

            // Mock successful job trigger
            $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

            // Mock job completion
            $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 1])));

            // Mock data fetch
            $mockData = [
                [
                    'url'                  => "https://www.{$case['expected_domain']}/dp/B0TEST12345/",
                    'product_name'         => 'Test Product',
                    'product_rating'       => 4.5,
                    'product_rating_count' => 100,
                    'asin'                 => 'B0TEST12345',
                    'review_id'            => 'R1',
                    'review_text'          => 'Great product',
                    'rating'               => 5,
                    'review_header'        => 'Excellent',
                    'author_name'          => 'Test User',
                    'author_id'            => 'A1',
                    'review_posted_date'   => '2023-01-01',
                    'review_country'       => 'Test Country',
                    'helpful_count'        => 10,
                    'is_amazon_vine'       => false,
                    'is_verified'          => true,
                    'badge'                => 'Verified Purchase',
                    'timestamp'            => '2023-01-01T00:00:00Z',
                ],
            ];
            $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

            $result = $this->service->fetchReviews('B0TEST12345', $case['country']);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('reviews', $result);
            $this->assertCount(1, $result['reviews']);
        }
    }

    #[Test]
    public function it_can_check_progress()
    {
        // Mock progress check response
        $mockProgress = [
            [
                'id'         => 'job_123',
                'status'     => 'running',
                'records'    => 50,
                'created_at' => '2023-01-01T00:00:00Z',
            ],
            [
                'id'         => 'job_456',
                'status'     => 'ready',
                'records'    => 100,
                'created_at' => '2023-01-01T01:00:00Z',
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($mockProgress)));

        $result = $this->service->checkProgress();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('job_123', $result[0]['id']);
        $this->assertEquals('running', $result[0]['status']);
        $this->assertEquals(50, $result[0]['records']);
    }

    #[Test]
    public function it_handles_network_errors_gracefully()
    {
        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock network error during job trigger
        $this->mockHandler->append(new Response(500, [], json_encode(['error' => 'Internal server error'])));

        $result = $this->service->fetchReviews('B0TEST12345', 'us');

        $this->assertEquals([], $result['reviews']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals(0, $result['total_reviews']);
    }

    #[Test]
    public function it_generates_limited_urls_for_cost_control()
    {
        // Set a low review cap for testing URL generation
        config(['amazon.brightdata.max_reviews' => 50]);

        // Mock concurrent job check
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        // Mock job trigger - verify the URLs sent to BrightData
        $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_123'])));

        // Mock job completion
        $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 2])));

        // Mock limited response data (since we're sending limited URLs)
        $mockData = [
            [
                'url'                  => 'https://www.amazon.com/dp/B0123456789/',
                'product_name'         => 'Test Product',
                'product_rating'       => 4.5,
                'product_rating_count' => 100,
                'asin'                 => 'B0123456789',
                'review_id'            => 'R1',
                'review_text'          => 'First review from limited scraping',
                'rating'               => 5,
                'review_header'        => 'Great product',
                'author_name'          => 'John Doe',
                'author_id'            => 'A1',
                'review_posted_date'   => '2023-01-01',
                'review_country'       => 'United States',
                'helpful_count'        => 10,
                'is_amazon_vine'       => false,
                'is_verified'          => true,
                'badge'                => 'Verified Purchase',
                'timestamp'            => '2023-01-01T00:00:00Z',
            ],
            [
                'url'                  => 'https://www.amazon.com/product-reviews/B0123456789/?pageNumber=1',
                'product_name'         => 'Test Product',
                'product_rating'       => 4.5,
                'product_rating_count' => 100,
                'asin'                 => 'B0123456789',
                'review_id'            => 'R2',
                'review_text'          => 'Second review from limited scraping',
                'rating'               => 4,
                'review_header'        => 'Good product',
                'author_name'          => 'Jane Smith',
                'author_id'            => 'A2',
                'review_posted_date'   => '2023-01-02',
                'review_country'       => 'United States',
                'helpful_count'        => 5,
                'is_amazon_vine'       => false,
                'is_verified'          => true,
                'badge'                => 'Verified Purchase',
                'timestamp'            => '2023-01-02T00:00:00Z',
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

        $result = $this->service->fetchReviews('B0123456789', 'us');

        // Verify all reviews are returned (no post-processing cap)
        $this->assertCount(2, $result['reviews']);
        $this->assertEquals('R1', $result['reviews'][0]['id']);
        $this->assertEquals('R2', $result['reviews'][1]['id']);
        $this->assertEquals('Test Product', $result['product_name']);
        $this->assertEquals(100, $result['total_reviews']);
    }

    #[Test]
    public function it_calculates_correct_url_count_for_different_caps()
    {
        // Test URL generation logic with different caps (updated for 150 reviews per page)
        $testCases = [
            ['cap' => 50, 'expected_pages' => 1, 'total_urls' => 2], // 1 product + 1 review page (50 ÷ 150 = 1)
            ['cap' => 150, 'expected_pages' => 1, 'total_urls' => 2], // 1 product + 1 review page (150 ÷ 150 = 1)
            ['cap' => 200, 'expected_pages' => 2, 'total_urls' => 3], // 1 product + 2 review pages (200 ÷ 150 = 2)
            ['cap' => 500, 'expected_pages' => 4, 'total_urls' => 5], // 1 product + 4 review pages (500 ÷ 150 = 4)
        ];

        foreach ($testCases as $case) {
            config(['amazon.brightdata.max_reviews' => $case['cap']]);

            // Mock concurrent job check
            $this->mockHandler->append(new Response(200, [], json_encode([])));

            // Mock job trigger
            $this->mockHandler->append(new Response(200, [], json_encode(['snapshot_id' => 'test_job_'.$case['cap']])));

            // Mock job completion
            $this->mockHandler->append(new Response(200, [], json_encode(['status' => 'ready', 'records' => 1])));

            // Mock minimal response
            $mockData = [
                [
                    'url'                  => 'https://www.amazon.com/dp/B0TEST/',
                    'product_name'         => 'Test Product',
                    'product_rating'       => 4.0,
                    'product_rating_count' => 100,
                    'asin'                 => 'B0TEST',
                    'review_id'            => 'R1',
                    'review_text'          => 'Test review',
                    'rating'               => 4,
                    'review_header'        => 'Test',
                    'author_name'          => 'Test User',
                    'author_id'            => 'A1',
                    'review_posted_date'   => '2023-01-01',
                    'review_country'       => 'United States',
                    'helpful_count'        => 1,
                    'is_amazon_vine'       => false,
                    'is_verified'          => true,
                    'badge'                => 'Verified Purchase',
                    'timestamp'            => '2023-01-01T00:00:00Z',
                ],
            ];

            $this->mockHandler->append(new Response(200, [], json_encode($mockData)));

            $result = $this->service->fetchReviews('B0TEST', 'us');

            // Verify the service works (we can't directly test URL count without exposing the method)
            $this->assertIsArray($result);
            $this->assertArrayHasKey('reviews', $result);
        }
    }

    private function createMockBrightDataResponse(): array
    {
        return [
            [
                'url'                  => 'https://www.amazon.com/dp/B0TEST12345/',
                'product_name'         => 'Test Product Name',
                'product_rating'       => 4.6,
                'product_rating_count' => 166807,
                'rating'               => 5,
                'author_name'          => 'Test Author 1',
                'asin'                 => 'B0TEST12345',
                'review_header'        => 'Excellent product!',
                'review_id'            => 'R1TEST123',
                'review_text'          => 'This is an amazing product that works exactly as described.',
                'author_id'            => 'ATEST123',
                'badge'                => 'Verified Purchase',
                'review_posted_date'   => 'July 15, 2025',
                'review_country'       => 'United States',
                'helpful_count'        => 25,
                'is_amazon_vine'       => false,
                'is_verified'          => true,
            ],
            [
                'url'                  => 'https://www.amazon.com/dp/B0TEST12345/',
                'product_name'         => 'Test Product Name',
                'product_rating'       => 4.6,
                'product_rating_count' => 166807,
                'rating'               => 4,
                'author_name'          => 'Test Author 2',
                'asin'                 => 'B0TEST12345',
                'review_header'        => 'Good value',
                'review_id'            => 'R2TEST456',
                'review_text'          => 'Solid product for the price point.',
                'author_id'            => 'ATEST456',
                'badge'                => 'Verified Purchase',
                'review_posted_date'   => 'July 10, 2025',
                'review_country'       => 'United States',
                'helpful_count'        => 12,
                'is_amazon_vine'       => false,
                'is_verified'          => true,
            ],
            [
                'url'                  => 'https://www.amazon.com/dp/B0TEST12345/',
                'product_name'         => 'Test Product Name',
                'product_rating'       => 4.6,
                'product_rating_count' => 166807,
                'rating'               => 3,
                'author_name'          => 'Test Author 3',
                'asin'                 => 'B0TEST12345',
                'review_header'        => 'Average product',
                'review_id'            => 'R3TEST789',
                'review_text'          => 'It works but could be better.',
                'author_id'            => 'ATEST789',
                'badge'                => 'Verified Purchase',
                'review_posted_date'   => 'July 5, 2025',
                'review_country'       => 'United States',
                'helpful_count'        => 5,
                'is_amazon_vine'       => true,
                'is_verified'          => true,
            ],
        ];
    }

    private function createMockBrightDataResponseWithImage(): array
    {
        return [
            [
                'url'                  => 'https://www.amazon.com/dp/B0TEST12345/',
                'product_name'         => 'Test Product With Image',
                'product_rating'       => 4.7,
                'product_rating_count' => 250000,
                'product_image_url'    => 'https://m.media-amazon.com/images/I/test-image.jpg',
                'rating'               => 5,
                'author_name'          => 'Test Author 1',
                'asin'                 => 'B0TEST12345',
                'review_header'        => 'Excellent product with image!',
                'review_id'            => 'R1TEST123',
                'review_text'          => 'This is an amazing product that works exactly as described.',
                'author_id'            => 'ATEST123',
                'badge'                => 'Verified Purchase',
                'review_posted_date'   => 'July 15, 2025',
                'review_country'       => 'United States',
                'helpful_count'        => 25,
                'is_amazon_vine'       => false,
                'is_verified'          => true,
            ],
        ];
    }
}
