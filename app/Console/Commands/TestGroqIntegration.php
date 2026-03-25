<?php

namespace App\Console\Commands;

use App\Services\LLMServiceManager;
use Illuminate\Console\Command;

class TestGroqIntegration extends Command
{
    protected $signature = 'test:groq';
    protected $description = 'Quick test for Groq LLM integration';

    public function handle()
    {
        $this->info('Testing Groq integration...');

        $reviews = [
            [
                'id' => 'rev1',
                'rating' => 5,
                'text' => 'Absolutely amazing product! Best purchase ever. Very high quality material.',
                'meta_data' => ['verified_purchase' => true]
            ],
            [
                'id' => 'rev2',
                'rating' => 1,
                'text' => 'Terrible experience. Broke after one use. The motor just stopped working.',
                'meta_data' => ['verified_purchase' => true]
            ],
            [
                'id' => 'rev3',
                'rating' => 4,
                'text' => 'Good value for the price. Not perfect but does the job well enough.',
                'meta_data' => ['verified_purchase' => false]
            ]
        ];

        try {
            $llm = app(LLMServiceManager::class);
            $this->info('Attempting analysis with: ' . ($llm->getOptimalProvider() ? $llm->getOptimalProvider()->getProviderName() : 'None'));

            $startTime = microtime(true);
            $result = $llm->analyzeReviews($reviews);
            $duration = microtime(true) - $startTime;

            $this->info('Analysis successful! Duration: ' . round($duration, 2) . 's');
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Analysis failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

