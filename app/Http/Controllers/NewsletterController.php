<?php

namespace App\Http\Controllers;

use App\Services\CaptchaService;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Newsletter Controller for handling newsletter subscription requests.
 */
class NewsletterController extends Controller
{
    protected $newsletterService;
    protected $captchaService;

    public function __construct(NewsletterService $newsletterService, CaptchaService $captchaService)
    {
        $this->newsletterService = $newsletterService;
        $this->captchaService = $captchaService;
    }

    /**
     * Subscribe to the newsletter.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function subscribe(Request $request): JsonResponse
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'email'                => 'required|email|max:255',
                'g_recaptcha_response' => app()->environment(['local', 'testing']) ? 'nullable' : 'required',
            ], [
                'email.required'                => 'Email address is required.',
                'email.email'                   => 'Please enter a valid email address.',
                'email.max'                     => 'Email address is too long.',
                'g_recaptcha_response.required' => 'Please complete the captcha verification.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $email = $request->input('email');
            $captchaResponse = $request->input('g_recaptcha_response');

            // Verify CAPTCHA (skip in local/testing environment)
            if (!app()->environment(['local', 'testing'])) {
                if (!$this->captchaService->verify($captchaResponse, $request->ip())) {
                    Log::warning('Newsletter subscription CAPTCHA verification failed', [
                        'email' => $email,
                        'ip'    => $request->ip(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'CAPTCHA verification failed. Please try again.',
                    ], 422);
                }
            }

            // Subscribe to newsletter
            $result = $this->newsletterService->subscribe($email);

            if ($result['success']) {
                Log::info('Newsletter subscription successful via controller', [
                    'email'      => $email,
                    'ip'         => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                ]);
            } else {
                // Handle specific error cases
                if (isset($result['code']) && $result['code'] === 'ALREADY_SUBSCRIBED') {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                        'code'    => 'ALREADY_SUBSCRIBED',
                    ], 409);
                }

                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Newsletter subscription controller error', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }

    /**
     * Unsubscribe from the newsletter.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $email = $request->input('email');
            $result = $this->newsletterService->unsubscribe($email);

            if ($result['success']) {
                Log::info('Newsletter unsubscription successful via controller', [
                    'email' => $email,
                    'ip'    => $request->ip(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Newsletter unsubscription controller error', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }

    /**
     * Check subscription status.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function checkSubscription(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $email = $request->input('email');
            $result = $this->newsletterService->checkSubscription($email);

            return response()->json([
                'success'    => $result['success'],
                'subscribed' => $result['subscribed'] ?? false,
                'message'    => $result['success'] ? 'Subscription status retrieved' : 'Failed to check subscription status',
            ]);
        } catch (\Exception $e) {
            Log::error('Newsletter subscription check controller error', [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success'    => false,
                'subscribed' => false,
                'message'    => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }

    /**
     * Test Mailtrain connection (for admin use).
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->newsletterService->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data'    => $result['data'] ?? null,
            ], $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            Log::error('Newsletter connection test error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
