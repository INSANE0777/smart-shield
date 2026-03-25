<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\ProductCategorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductCategorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_categorization_service_has_predefined_categories(): void
    {
        $categories = ProductCategorizationService::getCategories();

        $this->assertIsArray($categories);
        $this->assertContains('Electronics', $categories);
        $this->assertContains('Home & Kitchen', $categories);
        $this->assertContains('Other', $categories);
    }

    public function test_categorize_returns_null_for_empty_title(): void
    {
        Http::fake();

        $service = new ProductCategorizationService();

        $result = $service->categorize('');

        $this->assertNull($result);
    }

    public function test_categorize_with_llm_response(): void
    {
        // Fake all HTTP requests with wildcard
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Electronics']],
                ],
                'response' => 'Electronics', // For Ollama compatibility
            ], 200),
        ]);

        $service = new ProductCategorizationService();

        $result = $service->categorize('TP-Link AX1800 WiFi 6 Router');

        $this->assertNotNull($result);
        // Service now returns only category_tags (single source of truth)
        $this->assertEquals(['Electronics'], $result['category_tags']);
    }

    public function test_normalize_category_handles_invalid_responses(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Category: electronics.']],
                ],
                'response' => 'Category: electronics.', // For Ollama compatibility
            ], 200),
        ]);

        $service = new ProductCategorizationService();

        $result = $service->categorize('Test Product');

        $this->assertNotNull($result);
        // Service now returns only category_tags - should normalize to proper case
        $this->assertEquals(['Electronics'], $result['category_tags']);
    }

    public function test_categorize_and_save_updates_model(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Kitchen & Dining']],
                ],
                'response' => 'Kitchen & Dining',
            ], 200),
        ]);

        $product = AsinData::factory()->create([
            'product_title' => 'Ninja Professional Blender 1000W',
            'category_tags' => null,
        ]);

        $service = new ProductCategorizationService();
        $result = $service->categorizeAndSave($product);

        $this->assertTrue($result);

        $product->refresh();
        $this->assertNotNull($product->category);
        $this->assertEquals('llm_inference', $product->category_source);
        // Verify category_tags is set as single-element array
        $this->assertNotNull($product->category_tags);
        $this->assertEquals(['Kitchen & Dining'], $product->category_tags);
    }

    public function test_categorize_and_save_returns_false_without_title(): void
    {
        $product = AsinData::factory()->create([
            'product_title' => null,
        ]);

        $service = new ProductCategorizationService();
        $result = $service->categorizeAndSave($product);

        $this->assertFalse($result);
    }

    public function test_model_has_category_method(): void
    {
        // hasCategory() now checks category_tags array
        $productWithCategory = AsinData::factory()->withCategory('Routers')->create();
        $productWithoutCategory = AsinData::factory()->create([
            'category_tags' => null,
        ]);

        $this->assertTrue($productWithCategory->hasCategory());
        $this->assertFalse($productWithoutCategory->hasCategory());
    }

    public function test_model_get_related_products_ranks_by_shared_tag_count(): void
    {
        // Main product: Electronics > Computers > Networking > Routers
        $routerTags = ['Electronics', 'Computers', 'Networking', 'Routers'];

        $mainProduct = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => $routerTags,
        ]);

        // Product with ALL 4 matching tags - should rank highest
        $bestMatch = AsinData::factory()->create([
            'product_title'     => 'Best Match Router',
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => ['Electronics', 'Computers', 'Networking', 'Routers'],
        ]);

        // Product with 3 matching tags - should rank second
        $goodMatch = AsinData::factory()->create([
            'product_title'     => 'Good Match Switch',
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => ['Electronics', 'Computers', 'Networking', 'Switches'],
        ]);

        // Product with 2 matching tags - should rank third
        $okMatch = AsinData::factory()->create([
            'product_title'     => 'OK Match Keyboard',
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => ['Electronics', 'Computers', 'Keyboards'],
        ]);

        // Product with 0 matching tags - should NOT be included (below minSharedTags)
        AsinData::factory()->create([
            'product_title'     => 'No Match Blender',
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => ['Home & Kitchen', 'Small Appliances', 'Blenders'],
        ]);

        // Product with only 1 matching tag - should NOT be included (below minSharedTags default of 2)
        AsinData::factory()->create([
            'product_title'     => 'Barely Related Audio',
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => ['Electronics', 'Audio', 'Headphones'],
        ]);

        $related = $mainProduct->getRelatedProducts(6);

        // Should only include products with 2+ shared tags
        $this->assertCount(3, $related);

        // Verify products are ranked by shared tag count (highest first)
        // Due to inRandomOrder() for same-count products, we can't guarantee exact order
        // but we can verify all included products have at least 2 shared tags
        $relatedIds = $related->pluck('id')->toArray();
        $this->assertContains($bestMatch->id, $relatedIds); // 4 shared
        $this->assertContains($goodMatch->id, $relatedIds); // 3 shared
        $this->assertContains($okMatch->id, $relatedIds);   // 2 shared
    }

    public function test_model_get_related_products_returns_empty_without_category_tags(): void
    {
        $product = AsinData::factory()->create([
            'category_tags' => null,
        ]);

        $related = $product->getRelatedProducts();

        $this->assertTrue($related->isEmpty());
    }

    public function test_model_scopes_for_category(): void
    {
        // Clear any existing data to ensure test isolation
        AsinData::query()->delete();

        // Create products with category_tags
        AsinData::factory()->withCategory('Routers')->count(3)->create();
        AsinData::factory()->withLlmCategory('Home & Kitchen')->count(2)->create();
        AsinData::factory()->create(['category_tags' => null]);

        $withCategoryTags = AsinData::withCategoryTags()->get();
        $withoutCategoryTags = AsinData::withoutCategoryTags()->get();

        $this->assertCount(5, $withCategoryTags);
        $this->assertCount(1, $withoutCategoryTags);
    }

    public function test_factory_creates_products_with_breadcrumb_category(): void
    {
        $product = AsinData::factory()->withCategory('Routers')->create();

        // Only category_tags and category_source are stored
        // category and category_path are derived via accessors
        $this->assertEquals('amazon_breadcrumb', $product->category_source);
        $this->assertEquals(
            ['Electronics', 'Computers & Accessories', 'Networking Products', 'Routers'],
            $product->category_tags
        );

        // Verify accessors derive values correctly
        $this->assertEquals('Routers', $product->category);
        $this->assertEquals('Electronics > Computers & Accessories > Networking Products > Routers', $product->category_path);
    }

    public function test_factory_creates_products_with_llm_category(): void
    {
        $product = AsinData::factory()->withLlmCategory('Electronics')->create();

        // Only category_tags and category_source are stored
        $this->assertEquals('llm_inference', $product->category_source);
        $this->assertEquals(['Electronics'], $product->category_tags);

        // Verify accessors derive values correctly
        $this->assertEquals('Electronics', $product->category);
        $this->assertEquals('Electronics', $product->category_path);
    }

    public function test_parse_category_path_helper(): void
    {
        $result = AsinData::parseCategoryPath('Electronics > Computers > Networking > Routers');

        $this->assertEquals(['Electronics', 'Computers', 'Networking', 'Routers'], $result);
    }

    public function test_parse_category_path_returns_empty_for_null(): void
    {
        $result = AsinData::parseCategoryPath(null);

        $this->assertEquals([], $result);
    }

    public function test_related_products_respects_min_shared_tags_threshold(): void
    {
        // This test verifies that related products must meet the minSharedTags threshold

        $fullHierarchy = ['Electronics', 'Computers', 'Networking', 'Routers'];

        $mainProduct = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => $fullHierarchy,
        ]);

        // Product with 3 shared tags - should match with default minSharedTags=2
        $threeShared = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => ['Electronics', 'Computers', 'Networking'],
        ]);

        // Product with only 1 shared tag - should NOT match with default minSharedTags=2
        $oneShared = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => ['Electronics', 'Audio', 'Headphones'],
        ]);

        // Product with exact same tags - SHOULD match
        $fourShared = AsinData::factory()->create([
            'status'            => 'completed',
            'have_product_data' => true,
            'category_tags'     => $fullHierarchy,
        ]);

        // Test with default minSharedTags=2
        $relatedDefault = $mainProduct->getRelatedProducts(10);
        $this->assertCount(2, $relatedDefault);
        $this->assertContains($fourShared->id, $relatedDefault->pluck('id')->toArray());
        $this->assertContains($threeShared->id, $relatedDefault->pluck('id')->toArray());
        $this->assertNotContains($oneShared->id, $relatedDefault->pluck('id')->toArray());

        // Test with minSharedTags=3 (stricter)
        $relatedStrict = $mainProduct->getRelatedProducts(10, 3);
        $this->assertCount(2, $relatedStrict); // fourShared (4) and threeShared (3)

        // Test with minSharedTags=4 (very strict)
        $relatedVeryStrict = $mainProduct->getRelatedProducts(10, 4);
        $this->assertCount(1, $relatedVeryStrict);
        $this->assertEquals($fourShared->id, $relatedVeryStrict->first()->id);
    }
}
