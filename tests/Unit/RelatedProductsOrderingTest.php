<?php

namespace Tests\Unit;

use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedProductsOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides): AsinData
    {
        return AsinData::factory()->create(array_merge([
            'country'           => 'us',
            'status'            => 'completed',
            'have_product_data' => true,
            'product_title'     => 'Test Product',
        ], $overrides));
    }

    public function test_related_products_excludes_grade_u(): void
    {
        $tags = ['Electronics', 'Computers', 'Networking', 'Routers'];

        $main = $this->makeProduct([
            'asin'              => 'B0RELMAIN1',
            'product_title'     => 'Main Product',
            'category_tags'     => $tags,
            'grade'             => 'B',
            'fake_percentage'   => 25.0,
        ]);

        $valid = $this->makeProduct([
            'asin'            => 'B0RELGOOD1',
            'category_tags'   => $tags,
            'grade'           => 'A',
            'fake_percentage' => 10.0,
        ]);

        $uExcluded = $this->makeProduct([
            'asin'              => 'B0RELU0001',
            'product_title'     => 'Unanalyzable Product',
            'category_tags'     => $tags,
            'grade'             => 'U',
            'fake_percentage'   => 0.0,
        ]);

        $related = $main->getRelatedProducts(10);
        $ids = $related->pluck('id')->toArray();

        $this->assertNotContains($uExcluded->id, $ids);
        $this->assertContains($valid->id, $ids);
    }

    public function test_related_products_orders_by_shared_tags_then_best_grade_then_fake_percentage(): void
    {
        $tags = ['Electronics', 'Computers', 'Networking', 'Routers'];

        $main = $this->makeProduct([
            'asin'            => 'B0RELMAIN2',
            'category_tags'   => $tags,
            'grade'           => 'B',
            'fake_percentage' => 25.0,
        ]);

        $aHigherFake = $this->makeProduct([
            'asin'            => 'B0RELA1001',
            'category_tags'   => $tags,
            'grade'           => 'A',
            'fake_percentage' => 40.0,
        ]);

        $aLowerFake = $this->makeProduct([
            'asin'            => 'B0RELA1002',
            'category_tags'   => $tags,
            'grade'           => 'A',
            'fake_percentage' => 10.0,
        ]);

        $bLowerFake = $this->makeProduct([
            'asin'            => 'B0RELB1001',
            'category_tags'   => $tags,
            'grade'           => 'B',
            'fake_percentage' => 5.0,
        ]);

        $threeShared = $this->makeProduct([
            'asin'            => 'B0REL3SHR2',
            'category_tags'   => ['Electronics', 'Computers', 'Networking'],
            'grade'           => 'A',
            'fake_percentage' => 1.0,
        ]);

        $related = $main->getRelatedProducts(10);

        $ids = $related->pluck('id')->toArray();

        $this->assertSame(
            [$aLowerFake->id, $aHigherFake->id, $bLowerFake->id, $threeShared->id],
            array_slice($ids, 0, 4)
        );
    }
}

