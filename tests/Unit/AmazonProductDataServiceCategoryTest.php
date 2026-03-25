<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\Amazon\AmazonProductDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests for AmazonProductDataService category extraction functionality.
 */
class AmazonProductDataServiceCategoryTest extends TestCase
{
    use RefreshDatabase;

    private AmazonProductDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AmazonProductDataService();
    }

    public function test_build_product_url_for_us(): void
    {
        $url = $this->service->buildProductUrl('B00NGV4506', 'us');

        $this->assertEquals('https://www.amazon.com/dp/B00NGV4506', $url);
    }

    public function test_build_product_url_for_uk(): void
    {
        $url = $this->service->buildProductUrl('B00NGV4506', 'gb');

        $this->assertEquals('https://www.amazon.co.uk/dp/B00NGV4506', $url);
    }

    public function test_build_product_url_for_canada(): void
    {
        $url = $this->service->buildProductUrl('B00NGV4506', 'ca');

        $this->assertEquals('https://www.amazon.ca/dp/B00NGV4506', $url);
    }

    public function test_build_product_url_for_germany(): void
    {
        $url = $this->service->buildProductUrl('B00NGV4506', 'de');

        $this->assertEquals('https://www.amazon.de/dp/B00NGV4506', $url);
    }

    public function test_build_product_url_for_japan(): void
    {
        $url = $this->service->buildProductUrl('B00NGV4506', 'jp');

        $this->assertEquals('https://www.amazon.co.jp/dp/B00NGV4506', $url);
    }

    public function test_build_product_url_for_australia(): void
    {
        $url = $this->service->buildProductUrl('B00NGV4506', 'au');

        $this->assertEquals('https://www.amazon.com.au/dp/B00NGV4506', $url);
    }

    public function test_build_product_url_defaults_to_us_for_unknown_country(): void
    {
        $url = $this->service->buildProductUrl('B00NGV4506', 'xyz');

        $this->assertEquals('https://www.amazon.com/dp/B00NGV4506', $url);
    }

    public function test_scrape_categories_batch_parallel_returns_empty_for_empty_input(): void
    {
        $result = $this->service->scrapeCategoriesBatchParallel([]);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('throttled', $result);
        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['throttled']);
    }

    public function test_scrape_categories_batch_parallel_extracts_breadcrumb(): void
    {
        // Create a product to categorize
        $product = AsinData::factory()->create([
            'asin'    => 'B00NGV4506',
            'country' => 'us',
        ]);

        // Mock HTTP response with Amazon-like breadcrumb HTML
        Http::fake([
            'https://www.amazon.com/dp/B00NGV4506' => Http::response($this->getMockAmazonHtmlWithBreadcrumb(), 200),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$product]);
        $results = $result['results'];

        $this->assertArrayHasKey($product->id, $results);
        $this->assertNotNull($results[$product->id]);
        $this->assertArrayHasKey('category_tags', $results[$product->id]);
        $this->assertEquals(
            ['Home & Kitchen', 'Kitchen & Dining', 'Small Appliances', 'Blenders', 'Countertop Blenders'],
            $results[$product->id]['category_tags']
        );
        $this->assertEquals(0, $result['throttled']);
    }

    public function test_scrape_categories_batch_parallel_handles_failed_requests(): void
    {
        $product = AsinData::factory()->create([
            'asin'    => 'BADASIN123',
            'country' => 'us',
        ]);

        Http::fake([
            'https://www.amazon.com/dp/BADASIN123' => Http::response('Not Found', 404),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$product]);
        $results = $result['results'];

        $this->assertArrayHasKey($product->id, $results);
        $this->assertNull($results[$product->id]);
    }

    public function test_scrape_categories_batch_parallel_handles_empty_html(): void
    {
        $product = AsinData::factory()->create([
            'asin'    => 'B00EMPTY01',
            'country' => 'us',
        ]);

        Http::fake([
            'https://www.amazon.com/dp/B00EMPTY01' => Http::response('', 200),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$product]);
        $results = $result['results'];

        $this->assertArrayHasKey($product->id, $results);
        $this->assertNull($results[$product->id]);
    }

    public function test_scrape_categories_batch_parallel_handles_html_without_breadcrumb(): void
    {
        $product = AsinData::factory()->create([
            'asin'    => 'B00NOCRUMB',
            'country' => 'us',
        ]);

        $htmlWithoutBreadcrumb = '<!DOCTYPE html><html><head><title>Test</title></head><body><h1>Product</h1></body></html>';

        Http::fake([
            'https://www.amazon.com/dp/B00NOCRUMB' => Http::response($htmlWithoutBreadcrumb, 200),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$product]);
        $results = $result['results'];

        $this->assertArrayHasKey($product->id, $results);
        $this->assertNull($results[$product->id]);
    }

    public function test_scrape_categories_batch_parallel_processes_multiple_products(): void
    {
        $product1 = AsinData::factory()->create([
            'asin'    => 'B00PROD001',
            'country' => 'us',
        ]);
        $product2 = AsinData::factory()->create([
            'asin'    => 'B00PROD002',
            'country' => 'ca',
        ]);

        Http::fake([
            'https://www.amazon.com/dp/B00PROD001' => Http::response($this->getMockAmazonHtmlWithBreadcrumb(), 200),
            'https://www.amazon.ca/dp/B00PROD002'  => Http::response($this->getMockAmazonHtmlWithElectronicsBreadcrumb(), 200),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$product1, $product2]);
        $results = $result['results'];

        $this->assertCount(2, $results);

        // First product - Home & Kitchen
        $this->assertArrayHasKey($product1->id, $results);
        $this->assertNotNull($results[$product1->id]);
        $this->assertEquals('Countertop Blenders', end($results[$product1->id]['category_tags']));

        // Second product - Electronics
        $this->assertArrayHasKey($product2->id, $results);
        $this->assertNotNull($results[$product2->id]);
        $this->assertEquals('Routers', end($results[$product2->id]['category_tags']));
    }

    public function test_scrape_categories_batch_parallel_handles_mixed_success_failure(): void
    {
        $successProduct = AsinData::factory()->create([
            'asin'    => 'B00SUCCESS',
            'country' => 'us',
        ]);
        $failProduct = AsinData::factory()->create([
            'asin'    => 'B00FAILURE',
            'country' => 'us',
        ]);

        Http::fake([
            'https://www.amazon.com/dp/B00SUCCESS' => Http::response($this->getMockAmazonHtmlWithBreadcrumb(), 200),
            'https://www.amazon.com/dp/B00FAILURE' => Http::response('Server Error', 500),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$successProduct, $failProduct]);
        $results = $result['results'];

        $this->assertCount(2, $results);

        // Success product should have category_tags
        $this->assertNotNull($results[$successProduct->id]);
        $this->assertArrayHasKey('category_tags', $results[$successProduct->id]);

        // Failed product should be null
        $this->assertNull($results[$failProduct->id]);
    }

    public function test_scrape_categories_batch_parallel_detects_throttling(): void
    {
        $product = AsinData::factory()->create([
            'asin'    => 'B00THROTTLE',
            'country' => 'us',
        ]);

        Http::fake([
            'https://www.amazon.com/dp/B00THROTTLE' => Http::response('Too Many Requests', 429),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$product]);

        $this->assertEquals(1, $result['throttled']);
        $this->assertNull($result['results'][$product->id]);
    }

    public function test_scrape_categories_batch_parallel_detects_captcha(): void
    {
        $product = AsinData::factory()->create([
            'asin'    => 'B00CAPTCHA1',
            'country' => 'us',
        ]);

        $captchaHtml = '<!DOCTYPE html><html><body>To discuss automated access to Amazon data please contact api-services-support@amazon.com</body></html>';

        Http::fake([
            'https://www.amazon.com/dp/B00CAPTCHA1' => Http::response($captchaHtml, 200),
        ]);

        $result = $this->service->scrapeCategoriesBatchParallel([$product]);

        $this->assertEquals(1, $result['throttled']);
        $this->assertNull($result['results'][$product->id]);
    }

    /**
     * Get mock Amazon HTML with Home & Kitchen breadcrumb.
     */
    private function getMockAmazonHtmlWithBreadcrumb(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Test Product</title></head>
<body>
<div id="wayfinding-breadcrumbs_feature_div">
    <ul class="a-breadcrumb">
        <li><a href="#">Home & Kitchen</a></li>
        <li><a href="#">Kitchen & Dining</a></li>
        <li><a href="#">Small Appliances</a></li>
        <li><a href="#">Blenders</a></li>
        <li><a href="#">Countertop Blenders</a></li>
    </ul>
</div>
<h1 id="productTitle">Test Blender Product</h1>
</body>
</html>
HTML;
    }

    /**
     * Get mock Amazon HTML with Electronics breadcrumb.
     */
    private function getMockAmazonHtmlWithElectronicsBreadcrumb(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head><title>Test Router</title></head>
<body>
<div id="wayfinding-breadcrumbs_feature_div">
    <ul class="a-breadcrumb">
        <li><a href="#">Electronics</a></li>
        <li><a href="#">Computers & Accessories</a></li>
        <li><a href="#">Networking Products</a></li>
        <li><a href="#">Routers</a></li>
    </ul>
</div>
<h1 id="productTitle">Test Router Product</h1>
</body>
</html>
HTML;
    }
}
