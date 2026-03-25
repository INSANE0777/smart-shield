<?php

namespace App\Console\Commands;

use App\Services\Providers\DeepSeekProvider;
use Illuminate\Console\Command;

class TestDeepSeekChunking extends Command
{
    protected $signature = 'test:deepseek-chunking {--count=120 : Number of reviews to generate}';
    protected $description = 'Test DeepSeek chunking with simulated Chrome extension data';

    public function handle()
    {
        $reviewCount = (int) $this->option('count');

        $this->info("Testing DeepSeek chunking with {$reviewCount} reviews...");

        // Generate realistic review data based on the provided sample
        $reviews = $this->generateReviewData($reviewCount);

        $this->info("Generated {$reviewCount} reviews for testing");
        $this->info('This should trigger chunking (threshold is 80 reviews)');

        try {
            $deepSeekProvider = new DeepSeekProvider();

            // Check if DeepSeek is available
            if (!$deepSeekProvider->isAvailable()) {
                $this->error('DeepSeek provider is not available. Check API key configuration.');

                return 1;
            }

            $this->info('DeepSeek provider is available. Starting analysis...');

            $startTime = microtime(true);
            $result = $deepSeekProvider->analyzeReviews($reviews);
            $duration = microtime(true) - $startTime;

            $this->info('Analysis completed in '.round($duration, 2).' seconds');

            // Display results
            $this->displayResults($result);

            // Verify the fix worked
            $this->verifyDeduplication($result);

            $this->info('SUCCESS: DeepSeek chunking completed without "Array to string conversion" error!');
        } catch (\Exception $e) {
            $this->error('FAILED: '.$e->getMessage());

            // Check recent logs for more details
            $this->info('Checking recent logs for more details...');
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $logs = file_get_contents($logFile);
                $recentLogs = substr($logs, -2000); // Last 2000 characters
                $this->info('Recent log entries:');
                $this->info($recentLogs);
            }

            return 1;
        }

        return 0;
    }

    private function generateReviewData(int $count): array
    {
        $baseReviews = [
            [
                'content'           => "I give a 5 because it is really fun to use. It's easy to learn how to use the toy. I figured out how to aim it right at someone and shoot them with it. It's fun , it doesn't hurt when hit with it. My littlest son got this toy for his 7th birthday. He has lost most of the flying rings, it would be cool if I could buy replacements for them, I'd buy another with replacement discs.",
                'rating'            => 5,
                'author'            => 'carrie Ryan',
                'title'             => 'Disc toy',
                'verified_purchase' => true,
                'vine_customer'     => false,
            ],
            [
                'content'           => "So Much Fun – Kids Can't Get Enough! The Ninja Blast Flyer Launcher is an absolute blast—literally! It's easy for kids to launch, and the flyers go so high and fast, it keeps them entertained for hours. Great for outdoor play, birthday parties, or just burning off some energy.",
                'rating'            => 5,
                'author'            => 'Mombossandteacherlife',
                'title'             => "So Much Fun – Kids Can't Get Enough!",
                'verified_purchase' => true,
                'vine_customer'     => false,
            ],
            [
                'content'           => "Product flies up so there's no way it can be launch. Sad grandson! Bought way in advance before they visited me",
                'rating'            => 1,
                'author'            => 'SW',
                'title'             => "Doesn't work!",
                'verified_purchase' => true,
                'vine_customer'     => false,
            ],
            [
                'content'           => 'Cute toy! Kept the kids occupied on Easter!',
                'rating'            => 5,
                'author'            => 'Amazon Customer',
                'title'             => 'Excellent for individual play',
                'verified_purchase' => true,
                'vine_customer'     => false,
            ],
            [
                'content'           => 'Great product! Kids love it!',
                'rating'            => 5,
                'author'            => 'Generic Reviewer',
                'title'             => 'Amazing!',
                'verified_purchase' => true,
                'vine_customer'     => false,
            ],
            [
                'content'           => 'Amazing toy, highly recommend!',
                'rating'            => 5,
                'author'            => 'Another Customer',
                'title'             => 'Perfect!',
                'verified_purchase' => true,
                'vine_customer'     => false,
            ],
        ];

        $reviews = [];
        $baseCount = count($baseReviews);

        for ($i = 0; $i < $count; $i++) {
            $baseReview = $baseReviews[$i % $baseCount];

            // Convert to the format expected by the analysis service
            $reviews[] = [
                'id'          => 'TEST_'.str_pad($i, 4, '0', STR_PAD_LEFT), // Add required ID field
                'text'        => $baseReview['content'],
                'review_text' => $baseReview['content'],
                'rating'      => $baseReview['rating'],
                'date'        => date('Y-m-d', strtotime('2025-01-01') - ($i * 86400)),
                'author'      => $baseReview['author'].($i > $baseCount ? ' '.($i % 10) : ''),
                'title'       => $baseReview['title'],
                'review_id'   => 'TEST_'.str_pad($i, 4, '0', STR_PAD_LEFT),
                'meta_data'   => [
                    'verified_purchase' => $baseReview['verified_purchase'],
                    'is_vine_voice'     => $baseReview['vine_customer'],
                    'helpful_votes'     => rand(0, 5),
                ],
            ];
        }

        return $reviews;
    }

    private function displayResults(array $result): void
    {
        $this->info('=== ANALYSIS RESULTS ===');
        $this->info('Fake Percentage: '.($result['fake_percentage'] ?? 'N/A').'%');
        $this->info('Confidence: '.($result['confidence'] ?? 'N/A'));
        $this->info('Chunks Processed: '.($result['chunks_processed'] ?? 'N/A'));

        if (isset($result['fake_examples']) && is_array($result['fake_examples'])) {
            $this->info('Fake Examples Count: '.count($result['fake_examples']));
            foreach ($result['fake_examples'] as $i => $example) {
                if (is_array($example)) {
                    $this->info('  Example '.($i + 1).': '.substr($example['text'] ?? 'N/A', 0, 50).'...');
                } else {
                    $this->info('  Example '.($i + 1).': '.substr($example, 0, 50).'...');
                }
            }
        }

        if (isset($result['key_patterns']) && is_array($result['key_patterns'])) {
            $this->info('Key Patterns Count: '.count($result['key_patterns']));
            foreach ($result['key_patterns'] as $i => $pattern) {
                $this->info('  Pattern '.($i + 1).': '.$pattern);
            }
        }

        if (isset($result['explanation'])) {
            $this->info('Explanation: '.substr($result['explanation'], 0, 200).'...');
        }
    }

    private function verifyDeduplication(array $result): void
    {
        $this->info('=== DEDUPLICATION VERIFICATION ===');

        // Check fake_examples deduplication
        if (isset($result['fake_examples']) && is_array($result['fake_examples'])) {
            $exampleTexts = [];
            foreach ($result['fake_examples'] as $example) {
                if (is_array($example) && isset($example['text'])) {
                    $exampleTexts[] = $example['text'];
                }
            }

            $uniqueTexts = array_unique($exampleTexts);
            if (count($exampleTexts) === count($uniqueTexts)) {
                $this->info('✓ Fake examples properly deduplicated');
            } else {
                $this->warn('⚠ Potential duplicate fake examples detected');
            }
        }

        // Check key_patterns deduplication
        if (isset($result['key_patterns']) && is_array($result['key_patterns'])) {
            $uniquePatterns = array_unique($result['key_patterns']);
            if (count($result['key_patterns']) === count($uniquePatterns)) {
                $this->info('✓ Key patterns properly deduplicated');
            } else {
                $this->warn('⚠ Potential duplicate key patterns detected');
                $this->info('Original count: '.count($result['key_patterns']));
                $this->info('Unique count: '.count($uniquePatterns));
            }
        }

        // Verify no "Array to string conversion" error occurred
        $this->info('✓ No "Array to string conversion" error - fix is working!');
    }
}
