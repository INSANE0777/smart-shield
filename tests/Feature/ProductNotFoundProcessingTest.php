<?php

namespace Tests\Feature;

use App\Models\AnalysisSession;
use App\Models\AsinData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductNotFoundProcessingTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function product_not_found_shows_default_message_when_not_processing(): void
    {
        $response = $this->get('/amazon/us/B0CMVXDBV8');

        $response->assertStatus(200);
        $response->assertSee('Product Not Found');
        $response->assertSee("We haven't analyzed this Amazon product yet", false); // false = don't escape
        $response->assertDontSee('Analysis In Progress');
        $response->assertDontSee('Estimated completion time');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function product_not_found_shows_processing_message_when_session_exists(): void
    {
        // Create an active analysis session
        $session = AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440000',
            'user_session'        => 'test_session',
            'asin'                => 'B0CMVXDBV8',
            'product_url'         => 'https://www.amazon.com/dp/B0CMVXDBV8',
            'status'              => 'processing',
            'current_step'        => 2,
            'progress_percentage' => 50,
            'current_message'     => 'Analyzing reviews...',
            'total_steps'         => 5,
            'started_at'          => now()->subMinute(),
        ]);

        $response = $this->get('/amazon/us/B0CMVXDBV8');

        $response->assertStatus(200);
        $response->assertSee('Analysis In Progress');
        $response->assertSee('Estimated completion time');
        $response->assertSee('minutes');
        $response->assertSee('Check Status Now');
        $response->assertDontSee('We haven\'t analyzed this Amazon product yet');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function product_not_found_shows_processing_when_asin_data_is_fetched_status(): void
    {
        // Create AsinData with 'fetched' status (submitted by extension but not yet analyzed)
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0CMVXDBV8',
            'country'         => 'us',
            'status'          => 'fetched',
            'product_title'   => 'Test Product',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $response = $this->get('/amazon/us/B0CMVXDBV8');

        $response->assertStatus(200);
        $response->assertSee('Analysis In Progress');
        $response->assertSee('Test Product');
        $response->assertSee('Estimated completion time');
        $response->assertSee('Check Status Now');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function product_not_found_shows_processing_when_asin_data_is_processing_status(): void
    {
        // Create AsinData with 'processing' status
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0CMVXDBV8',
            'country'         => 'us',
            'status'          => 'processing',
            'product_title'   => 'Test Product Being Processed',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $response = $this->get('/amazon/us/B0CMVXDBV8');

        $response->assertStatus(200);
        $response->assertSee('Analysis In Progress');
        $response->assertSee('Test Product Being Processed');
        $response->assertSee('Estimated completion time');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function completed_product_does_not_show_processing_message(): void
    {
        // Create fully analyzed product
        $asinData = AsinData::factory()->create([
            'asin'              => 'B0CMVXDBV8',
            'country'           => 'us',
            'status'            => 'completed',
            'product_title'     => 'Completed Product',
            'fake_percentage'   => 25.5,
            'grade'             => 'B',
            'have_product_data' => true,
        ]);

        $response = $this->get('/amazon/us/B0CMVXDBV8');

        // Should redirect to product page or show product, not "not found"
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 200
        );

        if ($response->status() === 200) {
            $response->assertDontSee('Product Not Found');
            $response->assertDontSee('Analysis In Progress');
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function processing_message_shows_estimated_time_based_on_review_count(): void
    {
        // Create product with many reviews (should take longer)
        $reviews = array_fill(0, 150, [
            'author'            => 'Test Author',
            'content'           => 'Test review content',
            'rating'            => 5,
            'date'              => '2024-01-01',
            'verified_purchase' => true,
        ]);

        $asinData = AsinData::factory()->create([
            'asin'            => 'B0CMVXDBV8',
            'country'         => 'us',
            'status'          => 'processing',
            'product_title'   => 'Product with Many Reviews',
            'reviews'         => $reviews,
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $response = $this->get('/amazon/us/B0CMVXDBV8');

        $response->assertStatus(200);
        $response->assertSee('Analysis In Progress');
        $response->assertSee('Estimated completion time');

        // Should show some reasonable estimate
        $estimatedMinutes = $asinData->getEstimatedProcessingTimeMinutes();
        $this->assertGreaterThan(0, $estimatedMinutes);
        $this->assertLessThanOrEqual(10, $estimatedMinutes); // Capped at 10
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function processing_status_detection_with_pending_status(): void
    {
        $session = AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440001',
            'user_session'        => 'test_session_2',
            'asin'                => 'B0TEST1234',
            'product_url'         => 'https://www.amazon.com/dp/B0TEST1234',
            'status'              => 'pending',
            'current_step'        => 0,
            'progress_percentage' => 0,
            'current_message'     => 'Queued for analysis...',
            'total_steps'         => 5,
        ]);

        $response = $this->get('/amazon/us/B0TEST1234');

        $response->assertStatus(200);
        $response->assertSee('Analysis In Progress');
        $response->assertSee('Estimated completion time');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function processing_status_with_session_that_has_been_running_long_time(): void
    {
        // Create session that started 5 minutes ago (longer than expected)
        $session = AnalysisSession::create([
            'id'                  => '550e8400-e29b-41d4-a716-446655440002',
            'user_session'        => 'test_session_3',
            'asin'                => 'B0LONGRUN1',
            'product_url'         => 'https://www.amazon.com/dp/B0LONGRUN1',
            'status'              => 'processing',
            'current_step'        => 3,
            'progress_percentage' => 60,
            'current_message'     => 'Still analyzing...',
            'total_steps'         => 5,
            'started_at'          => now()->subMinutes(5),
        ]);

        $processingInfo = AsinData::checkProcessingSession('B0LONGRUN1');

        $this->assertTrue($processingInfo['is_processing']);
        // Should still show a reasonable estimate even if it's taking longer than expected
        $this->assertGreaterThan(0, $processingInfo['estimated_minutes']);
        $this->assertLessThanOrEqual(5, $processingInfo['estimated_minutes']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function product_not_found_page_includes_country_in_view_data(): void
    {
        $response = $this->get('/amazon/ca/B0CMVXDBV8');

        $response->assertStatus(200);
        $response->assertViewHas('country', 'ca');
        $response->assertViewHas('asin', 'B0CMVXDBV8');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function product_not_found_with_slug_shows_processing_message(): void
    {
        // Create AsinData with processing status
        $asinData = AsinData::factory()->create([
            'asin'            => 'B0CMVXDBV8',
            'country'         => 'us',
            'status'          => 'processing',
            'product_title'   => 'Test Product With Slug',
            'fake_percentage' => null,
            'grade'           => null,
        ]);

        $response = $this->get('/amazon/us/B0CMVXDBV8/test-product-with-slug');

        $response->assertStatus(200);
        $response->assertSee('Analysis In Progress');
        $response->assertSee('Test Product With Slug');
    }
}
