<?php

namespace Tests\Feature;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_shows_related_products_section(): void
    {
        // Create main product with full category hierarchy (Routers has 4 tags)
        $mainProduct = AsinData::factory()->withCategory('Routers')->create([
            'asin'    => 'B001MAIN01',
            'country' => 'us',
        ]);

        // Create related products with same category hierarchy
        AsinData::factory()->withCategory('Routers')->count(3)->create();

        // Follow redirects to get to final page
        $response = $this->followingRedirects()->get("/amazon/us/{$mainProduct->asin}");

        $response->assertStatus(200);
        $response->assertSee('Related Routers Products');
    }

    public function test_related_products_returns_empty_for_product_without_category(): void
    {
        $product = AsinData::factory()->create([
            'asin'          => 'B001NOCAT1',
            'country'       => 'us',
            'category_tags' => null,
        ]);

        // Without category_tags, getRelatedProducts should return empty collection
        $related = $product->getRelatedProducts();

        $this->assertTrue($related->isEmpty());
    }

    public function test_product_page_hides_related_section_when_no_related_products(): void
    {
        // Create product with unique category tags (no other products share 2+ tags)
        $product = AsinData::factory()->create([
            'asin'          => 'B001ALONE1',
            'country'       => 'us',
            'category_tags' => ['Unique', 'Category', 'Path'],
        ]);

        $response = $this->followingRedirects()->get("/amazon/us/{$product->asin}");

        $response->assertStatus(200);
        // Should not show section if no related products found
        $response->assertDontSee('Related Unique');
        $response->assertDontSee('Other Analyzed Products');
    }

    public function test_related_products_exclude_current_product(): void
    {
        $mainProduct = AsinData::factory()->withCategory('Routers')->create([
            'asin'    => 'B001MAIN02',
            'country' => 'us',
        ]);

        AsinData::factory()->withCategory('Routers')->count(2)->create();

        $related = $mainProduct->getRelatedProducts(10);

        foreach ($related as $product) {
            $this->assertNotEquals($mainProduct->id, $product->id);
        }
    }

    public function test_related_products_only_shows_completed_products(): void
    {
        $mainProduct = AsinData::factory()->withCategory('Routers')->create();

        // Create completed products
        AsinData::factory()->withCategory('Routers')->count(2)->create();

        // Create processing products (should not appear in related)
        AsinData::factory()->withCategory('Routers')->processing()->count(2)->create();

        $related = $mainProduct->getRelatedProducts(10);

        foreach ($related as $product) {
            $this->assertEquals('completed', $product->status);
        }
    }

    public function test_related_products_only_shows_products_with_data(): void
    {
        $mainProduct = AsinData::factory()->withCategory('Routers')->create();

        // Create products with product data
        AsinData::factory()->withCategory('Routers')->count(2)->create([
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ]);

        // Create products without product data (should not appear)
        AsinData::factory()->withCategory('Routers')->count(2)->create([
            'have_product_data' => false,
            'product_title'     => null,
        ]);

        $related = $mainProduct->getRelatedProducts(10);

        foreach ($related as $product) {
            $this->assertTrue($product->have_product_data);
            $this->assertNotNull($product->product_title);
        }
    }

    public function test_related_products_displays_grade_badges(): void
    {
        $mainProduct = AsinData::factory()->withCategory('Routers')->create([
            'asin'    => 'B001BADGE1',
            'country' => 'us',
        ]);

        AsinData::factory()->withCategory('Routers')->gradeA()->create([
            'product_title' => 'Grade A Product',
        ]);

        AsinData::factory()->withCategory('Routers')->gradeF()->create([
            'product_title' => 'Grade F Product',
        ]);

        $response = $this->followingRedirects()->get("/amazon/us/{$mainProduct->asin}");

        $response->assertStatus(200);
        $response->assertSee('Grade A');
        $response->assertSee('Grade F');
    }

    public function test_related_products_limited_to_specified_count(): void
    {
        $mainProduct = AsinData::factory()->withCategory('Routers')->create();

        // Create more products than limit
        AsinData::factory()->withCategory('Routers')->count(10)->create();

        $related = $mainProduct->getRelatedProducts(3);

        $this->assertCount(3, $related);
    }

    public function test_product_controller_passes_related_products_to_view(): void
    {
        $mainProduct = AsinData::factory()->withCategory('Routers')->create([
            'asin'    => 'B001CTRL01',
            'country' => 'us',
        ]);

        AsinData::factory()->withCategory('Routers')->count(2)->create();

        // Follow redirects to get to final view
        $response = $this->followingRedirects()->get("/amazon/us/{$mainProduct->asin}");

        $response->assertStatus(200);
        $response->assertViewHas('relatedProducts');

        $viewData = $response->viewData('relatedProducts');
        $this->assertCount(2, $viewData);
    }
}

