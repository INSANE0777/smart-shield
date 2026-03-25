<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LoggingService
{
    // Log levels
    const DEBUG = 'debug';
    const INFO = 'info';
    const ERROR = 'error';

    // Error types for user-friendly messages
    const ERROR_TYPES = [
        'API_TIMEOUT' => [
            'patterns' => ['cURL error 28', 'Operation timed out', 'timed out after'],
            'message'  => 'The review service took too long to respond. This can happen when Amazon is blocking requests or the service is overloaded. Please try again in a few minutes.',
        ],
        'PROXY_AUTH_ERROR' => [
            'patterns' => ['cURL error 56', 'Received HTTP code 407', 'proxy authentication'],
            'message'  => 'The review service is temporarily unavailable due to network configuration issues. Please try again in a few minutes.',
        ],
        'UNWRANGLE_TIMEOUT' => [
            'patterns' => ['Unwrangle API request exception', 'with 0 bytes received'],
            'message'  => 'Unable to connect to the review service. This may be due to high traffic or temporary service issues. Please try again in a few minutes.',
        ],
        'PRODUCT_NOT_FOUND' => [
            'patterns' => ['Product does not exist on Amazon.com (US) site'],
            'message'  => 'Product does not exist on Amazon.com (US) site. Please check the URL and try again.',
        ],
        'DATA_TYPE_ERROR' => [
            'patterns' => ['count(): Argument #1 ($value) must be of type Countable|array', 'TypeError'],
            'message'  => 'Data processing error occurred. Please try again.',
        ],
        'FETCHING_FAILED' => [
            'patterns' => ['Unable to fetch product reviews at this time', 'Unable to fetch product reviews'],
            'message'  => null, // Use the detailed message from AmazonFetchService
        ],
        'OPENAI_ERROR' => [
            'patterns' => ['OpenAI API request failed'],
            'message'  => 'Analysis service is temporarily unavailable. Please try again later.',
        ],
        'INVALID_URL' => [
            'patterns' => ['Invalid Amazon URL', 'ASIN not found', 'Could not extract ASIN from URL'],
            'message'  => 'Please provide a valid Amazon product URL.',
        ],
        'REDIRECT_FAILED' => [
            'patterns' => ['Failed to follow redirect', 'Redirect does not lead to Amazon domain'],
            'message'  => 'Unable to resolve the shortened URL. Please try using the full Amazon product URL instead.',
        ],
        'VALIDATION_ERROR' => [
            'patterns' => ['The product url field is required', 'The product url field must be a valid URL', 'validation.required', 'validation.url'],
            'message'  => null, // Will use original message for validation errors
        ],
        'CAPTCHA_ERROR' => [
            'patterns' => ['Captcha verification failed', 'captcha verification failed', 'CAPTCHA', 'captcha'],
            'message'  => null, // Will use original message for captcha errors
        ],
    ];

    /**
     * Log a message with context.
     */
    public static function log(string $message, array $context = [], string $level = self::INFO): void
    {
        Log::$level($message, $context);
    }

    /**
     * Log an exception and return user-friendly message.
     */
    public static function handleException(\Exception $e): string
    {
        $errorMessage = $e->getMessage();
        self::log($errorMessage, ['trace' => $e->getTraceAsString()], self::ERROR);

        // Match error message against patterns and return user-friendly message
        foreach (self::ERROR_TYPES as $type) {
            foreach ($type['patterns'] as $pattern) {
                if (str_contains($errorMessage, $pattern)) {
                    // For validation errors, return the original message
                    return $type['message'] ?? $errorMessage;
                }
            }
        }

        // Default generic error message
        return 'An unexpected error occurred. Please try again later.';
    }

    /**
     * Log progress updates.
     */
    public static function logProgress(string $step, string $message): void
    {
        self::log("Progress: {$step} - {$message}");
    }
}
