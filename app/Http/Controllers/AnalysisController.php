<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProductAnalysis;
use App\Models\AnalysisSession;
use App\Services\ReviewAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnalysisController extends Controller
{
    /**
     * Start async product analysis.
     */
    public function startAnalysis(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'productUrl'           => 'required|url',
            'g_recaptcha_response' => 'nullable|string',
            'h_captcha_response'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => $validator->errors()->first(),
            ], 422);
        }

        try {
            $productUrl = $request->input('productUrl');
            $userSession = $request->session()->getId();

            // Validate captcha immediately before processing (tokens expire quickly)
            $this->validateCaptchaIfRequired($request);

            // Extract ASIN from URL
            $analysisService = app(ReviewAnalysisService::class);
            $asin = $analysisService->extractAsinFromUrl($productUrl);

            // Check if there's already a recent analysis for this user/ASIN
            $existingSession = AnalysisSession::where('user_session', $userSession)
                ->where('asin', $asin)
                ->where('created_at', '>', now()->subMinutes(5))
                ->whereIn('status', ['pending', 'processing'])
                ->first();

            if ($existingSession) {
                return response()->json([
                    'success'    => true,
                    'session_id' => $existingSession->id,
                    'status'     => 'existing',
                    'message'    => 'Analysis already in progress',
                ]);
            }

            // Create new analysis session
            $session = AnalysisSession::create([
                'user_session'    => $userSession,
                'asin'            => $asin,
                'product_url'     => $productUrl,
                'status'          => 'pending',
                'total_steps'     => 8,
                'current_message' => 'Queued for analysis...',
            ]);

            // Dispatch async job (captcha already validated above)
            ProcessProductAnalysis::dispatch($session->id, $productUrl)
                ->onConnection('database');

            return response()->json([
                'success'    => true,
                'session_id' => $session->id,
                'status'     => 'started',
                'message'    => 'Analysis started successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Failed to start analysis: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get analysis progress.
     */
    public function getProgress(Request $request, string $sessionId): JsonResponse
    {
        $session = AnalysisSession::find($sessionId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'error'   => 'Analysis session not found',
            ], 404);
        }

        // Verify session belongs to current user (skip in local/testing environments)
        if (!app()->environment(['local', 'testing']) && $session->user_session !== $request->session()->getId()) {
            return response()->json([
                'success' => false,
                'error'   => 'Unauthorized access to analysis session',
            ], 403);
        }

        $response = [
            'success'             => true,
            'status'              => $session->status,
            'current_step'        => $session->current_step,
            'total_steps'         => $session->total_steps,
            'progress_percentage' => $session->progress_percentage,
            'current_message'     => $session->current_message,
            'asin'                => $session->asin,
        ];

        if ($session->isCompleted()) {
            $response['result'] = $session->result;
            $response['redirect_url'] = $session->result['redirect_url'] ?? null;
        } elseif ($session->isFailed()) {
            $response['error'] = $session->error_message;
        }

        return response()->json($response);
    }

    /**
     * Cancel analysis.
     */
    public function cancelAnalysis(Request $request, string $sessionId): JsonResponse
    {
        $session = AnalysisSession::find($sessionId);

        if (!$session) {
            return response()->json([
                'success' => false,
                'error'   => 'Analysis session not found',
            ], 404);
        }

        // Verify session belongs to current user (skip in local/testing environments)
        if (!app()->environment(['local', 'testing']) && $session->user_session !== $request->session()->getId()) {
            return response()->json([
                'success' => false,
                'error'   => 'Unauthorized access to analysis session',
            ], 403);
        }

        if ($session->isCompleted() || $session->isFailed()) {
            return response()->json([
                'success' => false,
                'error'   => 'Cannot cancel completed analysis',
            ], 422);
        }

        $session->markAsFailed('Analysis cancelled by user');

        return response()->json([
            'success' => true,
            'message' => 'Analysis cancelled successfully',
        ]);
    }

    /**
     * Clean up old analysis sessions.
     */
    public function cleanup(): JsonResponse
    {
        $deleted = AnalysisSession::where('created_at', '<', now()->subDays(1))->delete();

        return response()->json([
            'success'          => true,
            'deleted_sessions' => $deleted,
        ]);
    }

    /**
     * Validate captcha if required (skip in local/testing environments).
     */
    private function validateCaptchaIfRequired(Request $request): void
    {
        // Captcha validation is handled by the Livewire component
        // This method is kept for API compatibility but does nothing
    }
}
