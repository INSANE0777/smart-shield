<?php

namespace App\Jobs;

use App\Models\AnalysisSession;
use App\Models\AsinData;
use App\Services\LoggingService;
use App\Services\ReviewAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessProductAnalysis implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 360; // 6 minutes - sufficient for BrightData processing
    public $backoff = [30, 60, 120]; // Backoff delays for retries

    public function __construct(
        private string $sessionId,
        private string $productUrl
    ) {
        // Use dedicated analysis queue
        $this->onQueue('analysis');
    }

    public function handle(): void
    {
        $session = AnalysisSession::find($this->sessionId);

        if (!$session) {
            Log::error('Analysis session not found', ['session_id' => $this->sessionId]);

            return;
        }

        try {
            $session->markAsProcessing();

            LoggingService::log('Starting async product analysis', [
                'session_id' => $this->sessionId,
                'asin'       => $session->asin,
                'attempt'    => $this->attempts(),
            ]);

            // Step 1: Captcha already validated in controller
            $session->updateProgress(1, 12, 'Starting analysis...');

            // Step 2: Check if product exists in database
            $session->updateProgress(2, 25, 'Checking product database...');
            $analysisService = app(ReviewAnalysisService::class);
            $productInfo = $analysisService->checkProductExists($this->productUrl);

            $asinData = $productInfo['asin_data'];

            // Step 3: Fetch reviews if needed
            if ($productInfo['needs_fetching']) {
                $session->updateProgress(3, 52, 'Gathering review information...');
                $asinData = $analysisService->fetchReviews(
                    $productInfo['asin'],
                    $productInfo['country'],
                    $productInfo['product_url']
                );
            }

            // Step 4: Analyze with OpenAI if needed
            if ($productInfo['needs_openai']) {
                $session->updateProgress(4, 70, 'Analyzing reviews with AI...');
                $asinData = $analysisService->analyzeWithLLM($asinData);
            }

            // Step 5: Calculate final metrics
            $session->updateProgress(5, 85, 'Computing authenticity metrics...');
            $analysisResult = $analysisService->calculateFinalMetrics($asinData);

            // Step 6: Execute product data scraping as part of analysis flow (user waits for complete result)
            if (!$asinData->have_product_data) {
                $session->updateProgress(6, 92, 'Fetching product information...');

                // Execute product scraping synchronously as part of the analysis process
                $scrapeJob = new ScrapeAmazonProductData($asinData);
                $scrapeJob->handle();

                // Refresh the model to get updated product data
                $asinData = $asinData->fresh();

                LoggingService::log('Product scraping completed as part of analysis', [
                    'asin'              => $asinData->asin,
                    'session_id'        => $this->sessionId,
                    'have_product_data' => $asinData->have_product_data,
                ]);
            } else {
                // Skip product data step if already available
                $session->updateProgress(6, 92, 'Product information already available...');
            }

            // Step 7: Complete analysis with full product data available
            $session->updateProgress(7, 98, 'Generating final report...');

            // Check if analysis completed successfully (including graceful handling of no reviews)
            if ($asinData->status !== 'completed') {
                // Mark as failed - analysis didn't complete successfully
                $session->markAsFailed('Analysis could not be completed. Please try again later.');

                LoggingService::log('Analysis failed - status not completed', [
                    'asin'          => $asinData->asin,
                    'session_id'    => $this->sessionId,
                    'status'        => $asinData->status,
                    'reviews_count' => count($asinData->getReviewsArray()),
                ]);

                return;
            }

            LoggingService::log('Analysis completed successfully', [
                'asin'          => $asinData->asin,
                'session_id'    => $this->sessionId,
                'status'        => $asinData->status,
                'reviews_count' => count($asinData->getReviewsArray()),
                'grade'         => $asinData->grade,
            ]);

            // Prepare final result
            $redirectUrl = $this->determineRedirectUrl($asinData);

            LoggingService::log('Async analysis completed successfully', [
                'asin'         => $asinData->asin,
                'redirect_url' => $redirectUrl,
            ]);

            $finalResult = [
                'success'         => true,
                'asin_data'       => $asinData->fresh(),
                'analysis_result' => $analysisResult,
                'redirect_url'    => $redirectUrl,
            ];

            // Step 8: Mark as completed (100%)
            $session->markAsCompleted($finalResult);

            // Dispatch price analysis job (runs independently, doesn't block main flow)
            $this->dispatchPriceAnalysis($asinData);

            // Dispatch editorial content job (runs independently, doesn't block main flow)
            $this->dispatchEditorialContent($asinData);

            LoggingService::log('Async product analysis completed successfully', [
                'session_id' => $this->sessionId,
                'asin'       => $session->asin,
                'total_time' => $session->started_at->diffInSeconds(now()),
            ]);
        } catch (\Exception $e) {
            $this->handleFailure($session, $e);
        }
    }

    private function determineRedirectUrl(AsinData $asinData): ?string
    {
        // Always redirect if analysis is completed and we have product data
        // This includes products with no reviews (Grade U) - users should see the result
        $hasAnalysis = $asinData->status === 'completed' &&
                       !is_null($asinData->fake_percentage) &&
                       !is_null($asinData->grade);

        if (!$hasAnalysis) {
            LoggingService::log('Product analysis incomplete - no redirect URL generated', [
                'asin'              => $asinData->asin,
                'reviews_count'     => count($asinData->getReviewsArray()),
                'status'            => $asinData->status,
                'fake_percentage'   => $asinData->fake_percentage,
                'grade'             => $asinData->grade,
                'have_product_data' => $asinData->have_product_data,
            ]);

            return null;
        }

        // Redirect to product page - handles both products with reviews and without reviews
        // Products without reviews show Grade U with appropriate messaging
        if ($asinData->slug) {
            return route('amazon.product.show.slug', [
                'country' => $asinData->country,
                'asin'    => $asinData->asin,
                'slug'    => $asinData->slug,
            ]);
        }

        return route('amazon.product.show', [
            'country' => $asinData->country,
            'asin'    => $asinData->asin,
        ]);
    }

    /**
     * Dispatch price analysis job (independent, doesn't block main flow).
     */
    private function dispatchPriceAnalysis(AsinData $asinData): void
    {
        // Only dispatch if product meets requirements
        if (!$asinData->needsPriceAnalysis()) {
            LoggingService::log('Skipping price analysis dispatch', [
                'asin'                   => $asinData->asin,
                'has_price_analysis'     => $asinData->hasPriceAnalysis(),
                'is_processing'          => $asinData->isPriceAnalysisProcessing(),
            ]);

            return;
        }

        try {
            ProcessPriceAnalysis::dispatch($asinData->id);

            LoggingService::log('Price analysis job dispatched', [
                'asin'         => $asinData->asin,
                'asin_data_id' => $asinData->id,
            ]);
        } catch (\Exception $e) {
            // Log but don't fail - price analysis is a non-critical enhancement
            LoggingService::log('Failed to dispatch price analysis job', [
                'asin'  => $asinData->asin,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch editorial content job (independent, doesn't block main flow).
     */
    private function dispatchEditorialContent(AsinData $asinData): void
    {
        // Only dispatch if product meets requirements
        if (!$asinData->needsEditorialContent()) {
            LoggingService::log('Skipping editorial content dispatch', [
                'asin'                     => $asinData->asin,
                'has_editorial_content'    => $asinData->hasEditorialContent(),
                'is_processing'            => $asinData->isEditorialContentProcessing(),
            ]);

            return;
        }

        try {
            ProcessEditorialContent::dispatch($asinData->id);

            LoggingService::log('Editorial content job dispatched', [
                'asin'         => $asinData->asin,
                'asin_data_id' => $asinData->id,
            ]);
        } catch (\Exception $e) {
            // Log but don't fail - editorial content is a non-critical enhancement
            LoggingService::log('Failed to dispatch editorial content job', [
                'asin'  => $asinData->asin,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleFailure(AnalysisSession $session, \Exception $e): void
    {
        $userMessage = LoggingService::handleException($e);
        $session->markAsFailed($userMessage);

        LoggingService::log('Async product analysis failed', [
            'session_id' => $this->sessionId,
            'asin'       => $session->asin,
            'error'      => $e->getMessage(),
            'attempt'    => $this->attempts(),
        ]);

        // Re-throw for retry mechanism if not final attempt
        if ($this->attempts() < $this->tries) {
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $session = AnalysisSession::find($this->sessionId);

        if ($session && !$session->isFailed()) {
            $userMessage = LoggingService::handleException($exception);
            $session->markAsFailed($userMessage);
        }

        Log::error('Product analysis job failed permanently', [
            'session_id' => $this->sessionId,
            'error'      => $exception->getMessage(),
            'attempts'   => $this->attempts(),
        ]);
    }
}
