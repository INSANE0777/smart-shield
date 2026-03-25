<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPriceAnalysis;
use App\Jobs\ProcessProductAnalysis;
use App\Models\AnalysisSession;
use App\Models\AsinData;
use App\Services\ExtensionReviewService;
use App\Services\LoggingService;
use App\Services\MetricsCalculationService;
use App\Services\ReviewAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExtensionController extends Controller
{
    private ExtensionReviewService $extensionService;
    private ReviewAnalysisService $analysisService;
    private MetricsCalculationService $metricsService;

    public function __construct(
        ExtensionReviewService $extensionService,
        ReviewAnalysisService $analysisService,
        MetricsCalculationService $metricsService
    ) {
        $this->extensionService = $extensionService;
        $this->analysisService = $analysisService;
        $this->metricsService = $metricsService;
    }

    /**
     * Submit review data from Chrome extension.
     */
    public function submitReviews(Request $request): JsonResponse
    {
        // Validate API key (skip in local/testing environments or when disabled)
        if (!app()->environment(['local', 'testing']) && config('services.extension.require_api_key', true) && !$this->validateApiKey($request)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid or missing API key',
            ], 401);
        }

        // Validate the JSON structure
        $rules = [
            'asin'                 => 'required|string|regex:/^[A-Z0-9]{10}$/',
            'country'              => 'required|string|size:2',
            'product_url'          => 'required|url',
            'extraction_timestamp' => 'required|date_format:Y-m-d\TH:i:s.v\Z',
            'extension_version'    => 'required|string',
            'reviews'              => 'present|array',
            // Product information from Chrome extension (replaces backend scraping)
            'product_info'                         => 'required|array',
            'product_info.title'                   => 'required|string|max:500',
            'product_info.description'             => 'nullable|string|max:2000',
            'product_info.image_url'               => 'nullable|url|max:500',
            'product_info.amazon_rating'           => 'nullable|numeric|min:0|max:5',
            'product_info.total_reviews_on_amazon' => 'required|integer|min:0',
            'product_info.price'                   => 'nullable|string|max:50',
            'product_info.availability'            => 'nullable|string|max:100',
        ];

        // Only add review validation rules if reviews array is not empty
        if (!empty($request->input('reviews'))) {
            $rules = array_merge($rules, [
                'reviews.*.author'            => 'required|string',
                'reviews.*.content'           => 'required|string',
                'reviews.*.date'              => 'required|date_format:Y-m-d',
                'reviews.*.extraction_index'  => 'required|integer|min:1',
                'reviews.*.helpful_votes'     => 'required|integer|min:0',
                'reviews.*.rating'            => 'required|integer|min:1|max:5',
                'reviews.*.review_id'         => 'required|string',
                'reviews.*.title'             => 'required|string',
                'reviews.*.verified_purchase' => 'required|boolean',
                'reviews.*.vine_customer'     => 'required|boolean',
            ]);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid data format',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->all();

            LoggingService::log('Chrome extension data received', [
                'asin'              => $data['asin'],
                'country'           => $data['country'],
                'review_count'      => count($data['reviews']),
                'extension_version' => $data['extension_version'],
            ]);

            // Check if product is ALREADY fully analyzed - if so, return existing analysis immediately
            // This prevents overwriting completed analysis with status='fetched'
            $existingAnalysis = AsinData::where('asin', $data['asin'])
                ->where('country', $data['country'])
                ->first();

            if ($existingAnalysis && $existingAnalysis->isAnalyzed()) {
                LoggingService::log('Product already analyzed - returning existing analysis', [
                    'asin'    => $data['asin'],
                    'country' => $data['country'],
                    'grade'   => $existingAnalysis->grade,
                    'status'  => $existingAnalysis->status,
                ]);

                // Build redirect URL
                $productSlug = $existingAnalysis->product_title
                    ? \Illuminate\Support\Str::slug($existingAnalysis->product_title)
                    : null;
                $redirectUrl = $productSlug
                    ? route('amazon.product.show.slug', [
                        'country' => $data['country'],
                        'asin'    => $data['asin'],
                        'slug'    => $productSlug,
                    ])
                    : route('amazon.product.show', [
                        'asin'    => $data['asin'],
                        'country' => $data['country'],
                    ]);

                return response()->json([
                    'success'           => true,
                    'asin'              => $data['asin'],
                    'country'           => $data['country'],
                    'analysis_id'       => $existingAnalysis->id,
                    'analysis_complete' => true,
                    'already_analyzed'  => true,
                    'redirect_url'      => $redirectUrl,
                    'view_url'          => $redirectUrl,
                    'fake_percentage'   => $existingAnalysis->fake_percentage,
                    'grade'             => $existingAnalysis->grade,
                    'adjusted_rating'   => $existingAnalysis->adjusted_rating,
                    'amazon_rating'     => $existingAnalysis->amazon_rating,
                    'explanation'       => $existingAnalysis->explanation,
                ]);
            }

            // Check if async processing is enabled (disabled in testing environment)
            $asyncEnabled = !app()->environment('testing') && (
                config('analysis.async_enabled') ??
                filter_var(env('ANALYSIS_ASYNC_ENABLED'), FILTER_VALIDATE_BOOLEAN) ??
                (env('APP_ENV') === 'production')
            );

            if ($asyncEnabled) {
                return $this->handleAsyncAnalysis($data);
            }

            // Synchronous processing (existing behavior)
            return $this->handleSyncAnalysis($data);
        } catch (\Exception $e) {
            LoggingService::handleException($e, 'Chrome extension data processing failed');

            return response()->json([
                'success' => false,
                'error'   => 'Failed to process review data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get progress for extension analysis session.
     */
    public function getExtensionProgress(Request $request, string $sessionId): JsonResponse
    {
        if (!app()->environment(['local', 'testing']) && config('services.extension.require_api_key', true) && !$this->validateApiKey($request)) {
            return response()->json([
                'success' => false,
                'error'   => 'Invalid or missing API key',
            ], 401);
        }

        $session = AnalysisSession::find($sessionId);

        if (!$session) {
            return response()->json([
                'success'           => false,
                'error'             => 'Analysis session not found',
                'analysis_complete' => false,
            ], 404);
        }

        $response = [
            'success'             => true,
            'status'              => $session->status,
            'current_step'        => $session->current_step,
            'total_steps'         => $session->total_steps,
            'progress_percentage' => $session->progress_percentage,
            'current_message'     => $session->current_message,
            'asin'                => $session->asin,
            'analysis_complete'   => false, // Default to false, updated below if completed
        ];

        if ($session->isCompleted()) {
            $response['result'] = $session->result;
            $response['redirect_url'] = $session->result['redirect_url'] ?? null;
            $response['analysis_complete'] = true;
        } elseif ($session->isFailed()) {
            $response['error'] = $session->error_message;
            // analysis_complete remains false for failed sessions
        }

        return response()->json($response);
    }

    /**
     * Get analysis status for extension.
     *
     * Returns data DIRECTLY without wrapper - extension API client adds its own wrapper.
     */
    public function getAnalysisStatus(Request $request, string $asin, string $country): JsonResponse
    {
        if (!app()->environment(['local', 'testing']) && config('services.extension.require_api_key', true) && !$this->validateApiKey($request)) {
            return response()->json([
                'error' => 'Invalid or missing API key',
            ], 401);
        }

        $asinData = AsinData::where('asin', $asin)
            ->where('country', $country)
            ->first();

        if (!$asinData) {
            return response()->json([
                'error'   => 'No analysis found',
                'asin'    => $asin,
                'country' => $country,
            ], 404);
        }

        // Build the product URL with slug for better SEO
        $productSlug = $asinData->product_title ? \Illuminate\Support\Str::slug($asinData->product_title) : null;
        $redirectUrl = $productSlug
            ? route('amazon.product.show.slug', [
                'country' => $country,
                'asin'    => $asin,
                'slug'    => $productSlug,
            ])
            : route('amazon.product.show', [
                'asin'    => $asin,
                'country' => $country,
            ]);

        // Check if analysis is complete AND page is ready to display
        $isAnalyzed = $asinData->isAnalyzed();

        // Return data DIRECTLY - extension API client wraps in {success, exists, data}
        return response()->json([
            'asin'                    => $asin,
            'country'                 => $country,
            'status'                  => $isAnalyzed ? 'completed' : ($asinData->status ?? 'processing'),
            'analysis_complete'       => $isAnalyzed,
            'redirect_url'            => $redirectUrl,
            'view_url'                => $redirectUrl,
            'fake_percentage'         => $asinData->fake_percentage,
            'grade'                   => $asinData->grade,
            'adjusted_rating'         => $asinData->adjusted_rating,
            'amazon_rating'           => $asinData->amazon_rating,
            'explanation'             => $asinData->explanation,
            'product_title'           => $asinData->product_title,
            'product_image_url'       => $asinData->product_image_url,
            'total_reviews_on_amazon' => $asinData->total_reviews_on_amazon,
            'analyzed_at'             => $asinData->last_analyzed_at?->toISOString(),
        ]);
    }

    /**
     * Validate API key from request.
     */
    private function validateApiKey(Request $request): bool
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');
        $validApiKey = config('services.extension.api_key');

        if (!$validApiKey) {
            LoggingService::log('Extension API key not configured');

            return false;
        }

        return hash_equals($validApiKey, $apiKey ?? '');
    }

    /**
     * Handle async analysis using queue jobs.
     */
    private function handleAsyncAnalysis(array $data): JsonResponse
    {
        // Process the extension data first
        $asinData = $this->extensionService->processExtensionData($data);

        // Create analysis session for progress tracking
        $session = AnalysisSession::create([
            'user_session'    => 'extension_'.$data['asin'].'_'.$data['country'],
            'asin'            => $data['asin'],
            'product_url'     => $data['product_url'],
            'status'          => 'pending',
            'total_steps'     => 3, // Extension analysis has fewer steps
            'current_message' => 'Processing extension data...',
        ]);

        // Update session to processing and dispatch job
        $session->markAsProcessing();
        $session->updateProgress(1, 33.3, 'Analyzing reviews with AI...');

        // Create a special job payload for extension data
        ProcessProductAnalysis::dispatch($session->id, $data['product_url'])
            ->onConnection('database');

        LoggingService::log('Chrome extension async analysis started', [
            'asin'       => $data['asin'],
            'country'    => $data['country'],
            'session_id' => $session->id,
        ]);

        return response()->json([
            'success'     => true,
            'asin'        => $data['asin'],
            'country'     => $data['country'],
            'analysis_id' => $asinData->id,
            'session_id'  => $session->id,
            'status'      => 'processing',
            'message'     => 'Analysis started - use session_id to track progress',
            'progress'    => [
                'percentage' => 33.3,
                'message'    => 'Analyzing reviews with AI...',
                'stage'      => 'processing',
            ],
            'progress_url'         => route('extension.progress', ['sessionId' => $session->id]),
            'estimated_completion' => now()->addMinutes(2)->toISOString(),
        ]);
    }

    /**
     * Handle synchronous analysis (existing behavior).
     */
    private function handleSyncAnalysis(array $data): JsonResponse
    {
        // Process the extension data
        $asinData = $this->extensionService->processExtensionData($data);

        // Perform analysis
        $asinData = $this->analysisService->analyzeWithLLM($asinData);
        $metrics = $this->analysisService->calculateFinalMetrics($asinData);

        // Get updated model with final metrics
        $asinData = $asinData->fresh();

        // Check if analysis actually succeeded
        if (is_null($asinData->fake_percentage) || is_null($asinData->grade)) {
            LoggingService::log('Chrome extension analysis failed - no valid results', [
                'asin'            => $data['asin'],
                'fake_percentage' => $asinData->fake_percentage,
                'grade'           => $asinData->grade,
                'status'          => $asinData->status,
            ]);

            return response()->json([
                'success'           => false,
                'error'             => 'Analysis failed - unable to process reviews',
                'error_details'     => 'All LLM providers failed or returned invalid results. Please try again later.',
                'asin'              => $data['asin'],
                'country'           => $data['country'],
                'processed_reviews' => count($asinData->getReviewsArray()),
                'retry_suggested'   => true,
            ], 500);
        }

        // Build response with detailed analysis results (matching front-end display)
        $reviewsAnalyzed = count($asinData->getReviewsArray());
        $fakeReviewCount = round(($asinData->fake_percentage / 100) * $reviewsAnalyzed);
        $genuineReviewCount = $reviewsAnalyzed - $fakeReviewCount;
        $ratingDifference = ($asinData->adjusted_rating ?? 0) - ($asinData->amazon_rating ?? 0);

        $response = [
            'success'           => true,
            'asin'              => $data['asin'],
            'country'           => $data['country'],
            'analysis_id'       => $asinData->id,
            'processed_reviews' => $reviewsAnalyzed,
            'analysis_complete' => true,
            'results'           => [
                'fake_percentage'   => $asinData->fake_percentage ?? 0,
                'grade'             => $asinData->grade,
                'explanation'       => $asinData->explanation,
                'amazon_rating'     => $asinData->amazon_rating ?? 0,
                'adjusted_rating'   => $asinData->adjusted_rating ?? 0,
                'rating_difference' => round($ratingDifference, 2),
            ],
            'statistics' => [
                'total_reviews_on_amazon' => $asinData->total_reviews_on_amazon ?? null,
                'reviews_analyzed'        => $reviewsAnalyzed,
                'genuine_reviews'         => $genuineReviewCount,
                'fake_reviews'            => $fakeReviewCount,
            ],
            'product_info' => [
                'title'       => $asinData->product_title,
                'description' => $asinData->product_description,
                'image_url'   => $asinData->product_image_url,
            ],
            'view_url' => route('amazon.product.show', [
                'asin'    => $data['asin'],
                'country' => $data['country'],
            ]),
            'redirect_url' => route('amazon.product.show', [
                'asin'    => $data['asin'],
                'country' => $data['country'],
            ]),
        ];

        LoggingService::log('Chrome extension analysis completed', [
            'asin'            => $data['asin'],
            'fake_percentage' => $asinData->fake_percentage,
            'grade'           => $asinData->grade,
        ]);

        // Dispatch price analysis job (independent, non-blocking)
        if ($asinData->needsPriceAnalysis()) {
            ProcessPriceAnalysis::dispatch($asinData->id);
        }

        return response()->json($response);
    }
}
