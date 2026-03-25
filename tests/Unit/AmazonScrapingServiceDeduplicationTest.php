<?php

namespace Tests\Unit;

use App\Services\Amazon\AmazonScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class AmazonScrapingServiceDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private AmazonScrapingService $scrapingService;
    private ReflectionMethod $deduplicateMethod;
    private ReflectionMethod $normalizeMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scrapingService = app(AmazonScrapingService::class);

        // Use reflection to access private methods for testing
        $reflection = new ReflectionClass($this->scrapingService);
        $this->deduplicateMethod = $reflection->getMethod('deduplicateReviews');
        $this->deduplicateMethod->setAccessible(true);

        $this->normalizeMethod = $reflection->getMethod('normalizeReviewText');
        $this->normalizeMethod->setAccessible(true);
    }

    #[Test]
    public function it_deduplicates_identical_review_texts()
    {
        $existingReviews = [
            ['review_text' => 'Great product, highly recommend!', 'rating' => 5],
            ['review_text' => 'Average quality for the price', 'rating' => 3],
        ];

        $newReviews = [
            ['review_text' => 'Great product, highly recommend!', 'rating' => 5], // Duplicate
            ['review_text' => 'Terrible quality, would not buy again', 'rating' => 1], // New
        ];

        $result = $this->deduplicateMethod->invoke(
            $this->scrapingService,
            $existingReviews,
            $newReviews
        );

        $this->assertCount(3, $result);
        $this->assertEquals('Great product, highly recommend!', $result[0]['review_text']);
        $this->assertEquals('Average quality for the price', $result[1]['review_text']);
        $this->assertEquals('Terrible quality, would not buy again', $result[2]['review_text']);
    }

    #[Test]
    public function it_handles_case_insensitive_duplicates()
    {
        $existingReviews = [
            ['review_text' => 'WORKS GREAT!', 'rating' => 5],
        ];

        $newReviews = [
            ['review_text' => 'works great!', 'rating' => 5], // Same text, different case
            ['review_text' => 'Works Great!', 'rating' => 5], // Same text, different case
            ['review_text' => 'Does not work at all', 'rating' => 1], // New
        ];

        $result = $this->deduplicateMethod->invoke(
            $this->scrapingService,
            $existingReviews,
            $newReviews
        );

        $this->assertCount(2, $result);
        $this->assertEquals('WORKS GREAT!', $result[0]['review_text']);
        $this->assertEquals('Does not work at all', $result[1]['review_text']);
    }

    #[Test]
    public function it_handles_whitespace_and_punctuation_variations()
    {
        $existingReviews = [
            ['review_text' => 'Works great, great price too....', 'rating' => 5],
        ];

        $newReviews = [
            ['review_text' => 'Works great,   great price too.....', 'rating' => 5], // Extra whitespace/punctuation
            ['review_text' => 'Works great great price too', 'rating' => 5], // No punctuation
            ['review_text' => '  Works great, great price too!!!  ', 'rating' => 5], // Different punctuation/spacing
            ['review_text' => 'Completely different review', 'rating' => 3], // New
        ];

        $result = $this->deduplicateMethod->invoke(
            $this->scrapingService,
            $existingReviews,
            $newReviews
        );

        // Should only have 2 reviews - the original and the completely different one
        $this->assertCount(2, $result);
        $this->assertEquals('Works great, great price too....', $result[0]['review_text']);
        $this->assertEquals('Completely different review', $result[1]['review_text']);
    }

    #[Test]
    public function it_preserves_review_data_integrity()
    {
        $existingReviews = [
            [
                'review_text'       => 'Great product!',
                'rating'            => 5,
                'verified_purchase' => true,
                'date'              => '2025-01-01',
            ],
        ];

        $newReviews = [
            [
                'review_text'       => 'Amazing new review',
                'rating'            => 4,
                'verified_purchase' => false,
                'date'              => '2025-01-02',
                'helpful_votes'     => 5,
            ],
        ];

        $result = $this->deduplicateMethod->invoke(
            $this->scrapingService,
            $existingReviews,
            $newReviews
        );

        $this->assertCount(2, $result);

        // Verify all original data is preserved
        $this->assertEquals(5, $result[0]['rating']);
        $this->assertTrue($result[0]['verified_purchase']);
        $this->assertEquals('2025-01-01', $result[0]['date']);

        // Verify new review data is preserved
        $this->assertEquals(4, $result[1]['rating']);
        $this->assertFalse($result[1]['verified_purchase']);
        $this->assertEquals('2025-01-02', $result[1]['date']);
        $this->assertEquals(5, $result[1]['helpful_votes']);
    }

    #[Test]
    public function it_handles_empty_arrays_gracefully()
    {
        // Empty existing, new reviews
        $result1 = $this->deduplicateMethod->invoke($this->scrapingService, [], [
            ['review_text' => 'First review', 'rating' => 5],
        ]);
        $this->assertCount(1, $result1);

        // Existing reviews, empty new
        $result2 = $this->deduplicateMethod->invoke($this->scrapingService, [
            ['review_text' => 'First review', 'rating' => 5],
        ], []);
        $this->assertCount(1, $result2);

        // Both empty
        $result3 = $this->deduplicateMethod->invoke($this->scrapingService, [], []);
        $this->assertCount(0, $result3);
    }

    #[Test]
    public function it_skips_reviews_without_text()
    {
        $existingReviews = [
            ['review_text' => 'Valid review', 'rating' => 5],
        ];

        $newReviews = [
            ['rating' => 3], // No review_text - should be skipped
            ['review_text' => 'Another valid review', 'rating' => 2],
        ];

        $result = $this->deduplicateMethod->invoke(
            $this->scrapingService,
            $existingReviews,
            $newReviews
        );

        // Should have 2 reviews - the deduplication method only processes reviews with text
        $this->assertCount(2, $result);

        // Check that valid reviews are properly positioned
        $validTexts = array_filter(array_column($result, 'review_text'));
        $this->assertCount(2, $validTexts);
        $this->assertContains('Valid review', $validTexts);
        $this->assertContains('Another valid review', $validTexts);
    }

    #[Test]
    public function normalize_review_text_handles_various_formats()
    {
        $testCases = [
            'Simple text'                          => 'simple text',
            '  UPPERCASE WITH SPACES  '            => 'uppercase with spaces',
            'Text!!! With??? Punctuation...'       => 'text with punctuation',
            'Multiple    spaces   between   words' => 'multiple spaces between words',
            'Text\nwith\nnewlines'                 => 'textnwithnnewlines', // Newlines become 'n' after punctuation removal
            'Mix3d numb3rs and sp3c!@l ch@rs'      => 'mix3d numb3rs and sp3cl chrs',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->normalizeMethod->invoke($this->scrapingService, $input);
            $this->assertEquals($expected, $result, "Failed normalizing: {$input}");
        }
    }

    #[Test]
    public function it_reproduces_the_b0bw8t4w5h_duplication_issue_scenario()
    {
        // Simulate the exact duplication pattern found in B0BW8T4W5H
        $sampleReviews = [
            ['review_text' => 'Works great, great price too....', 'rating' => 5],
            ['review_text' => 'This toner works perfectly well with my HP LaserJet Pro M29w', 'rating' => 5],
            ['review_text' => 'Does what it should...', 'rating' => 5],
        ];

        $existingReviews = [];

        // Simulate scraping the same reviews 20 times (as happened in the bug)
        for ($page = 1; $page <= 20; $page++) {
            $existingReviews = $this->deduplicateMethod->invoke(
                $this->scrapingService,
                $existingReviews,
                $sampleReviews
            );
        }

        // Should only have 3 unique reviews, not 60 (3 * 20)
        $this->assertCount(3, $existingReviews);

        // Verify the exact content
        $reviewTexts = array_column($existingReviews, 'review_text');
        $this->assertContains('Works great, great price too....', $reviewTexts);
        $this->assertContains('This toner works perfectly well with my HP LaserJet Pro M29w', $reviewTexts);
        $this->assertContains('Does what it should...', $reviewTexts);
    }

    #[Test]
    public function it_maintains_performance_with_large_datasets()
    {
        // Create a large dataset to test performance
        $existingReviews = [];
        for ($i = 0; $i < 100; $i++) {
            $existingReviews[] = ['review_text' => "Existing review number {$i}", 'rating' => 5];
        }

        $newReviews = [];
        for ($i = 50; $i < 150; $i++) { // 50 duplicates, 50 new
            $newReviews[] = ['review_text' => "Existing review number {$i}", 'rating' => 5];
        }

        $startTime = microtime(true);
        $result = $this->deduplicateMethod->invoke(
            $this->scrapingService,
            $existingReviews,
            $newReviews
        );
        $endTime = microtime(true);

        // Should complete in reasonable time (less than 1 second for this dataset)
        $this->assertLessThan(1.0, $endTime - $startTime);

        // Should have 150 unique reviews (100 existing + 50 new)
        $this->assertCount(150, $result);
    }
}
