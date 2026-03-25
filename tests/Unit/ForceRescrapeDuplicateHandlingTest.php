<?php

namespace Tests\Unit;

use App\Models\AsinData;
use App\Services\Amazon\AmazonScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForceRescrapeDuplicateHandlingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fetchReviewsAndSave_handles_existing_asin_without_integrity_constraint_error()
    {
        // Mock HTTP response for scraping - simple response that returns empty data
        Http::fake([
            'amazon.com/*' => Http::response('<html><body>Product not found</body></html>', 200),
        ]);

        $service = app(AmazonScrapingService::class);

        // Create initial product
        $asin = 'B0TEST123';
        $initialProduct = AsinData::create([
            'asin'                => $asin,
            'country'             => 'com',
            'product_description' => 'Initial description',
            'reviews'             => json_encode([
                [
                    'id'     => 'review1',
                    'rating' => 5,
                    'text'   => 'Initial review',
                ],
            ]),
            'total_reviews_on_amazon' => 50,
        ]);

        $this->assertDatabaseHas('asin_data', ['asin' => $asin]);
        $this->assertEquals(1, AsinData::where('asin', $asin)->count());

        // Re-scrape the same ASIN - should NOT create a new record or throw integrity constraint error
        $updatedProduct = $service->fetchReviewsAndSave(
            $asin,
            'com',
            "https://www.amazon.com/dp/{$asin}"
        );

        // Verify no duplicate records were created - this is the key test
        $this->assertEquals(1, AsinData::where('asin', $asin)->count());

        // Verify the existing record was updated (same ID)
        $this->assertEquals($initialProduct->id, $updatedProduct->id);

        // The test passes if we get here without an integrity constraint exception
        $this->assertTrue(true, 'No integrity constraint error occurred');
    }

    #[Test]
    public function fetchReviewsAndSave_creates_new_record_for_new_asin()
    {
        // Mock HTTP response for scraping
        Http::fake([
            'amazon.com/*' => Http::response('<html><body>Product not found</body></html>', 200),
        ]);

        $service = app(AmazonScrapingService::class);

        $asin = 'B0NEWTEST123';

        // Verify no existing record
        $this->assertEquals(0, AsinData::where('asin', $asin)->count());

        // Create new product via scraping
        $newProduct = $service->fetchReviewsAndSave(
            $asin,
            'com',
            "https://www.amazon.com/dp/{$asin}"
        );

        // Verify new record was created
        $this->assertEquals(1, AsinData::where('asin', $asin)->count());
        $this->assertEquals($asin, $newProduct->asin);
    }
}
