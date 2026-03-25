<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Newsletter Service for handling Mailtrain API integration.
 *
 * This service provides a unified interface for managing newsletter subscriptions
 * through a self-hosted Mailtrain instance.
 */
class NewsletterService
{
    protected $baseUrl;
    protected $apiToken;
    protected $listId;
    protected $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.mailtrain.base_url');
        $this->apiToken = config('services.mailtrain.api_token');
        $this->listId = config('services.mailtrain.list_id');
        $this->timeout = config('services.mailtrain.timeout', 30);
    }

    /**
     * Subscribe an email address to the newsletter.
     *
     * @param string $email          The email address to subscribe
     * @param array  $additionalData Optional additional subscriber data
     *
     * @return array Result array with success status and message
     */
    public function subscribe(string $email, array $additionalData = []): array
    {
        try {
            $this->validateConfiguration();

            $data = array_merge([
                'EMAIL'           => $email,
                'FORCE_SUBSCRIBE' => 'yes', // Force subscription without confirmation
            ], $additionalData);

            $response = Http::timeout($this->timeout)
                ->asForm() // Use form encoding instead of JSON
                ->post("{$this->baseUrl}/api/subscribe/{$this->listId}?access_token={$this->apiToken}", $data);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Newsletter subscription successful', [
                    'email'    => $email,
                    'list_id'  => $this->listId,
                    'response' => $result,
                ]);

                return [
                    'success' => true,
                    'message' => 'Successfully subscribed to newsletter!',
                    'data'    => $result,
                ];
            } else {
                $errorData = $response->json();

                // Handle specific Mailtrain error codes
                if (isset($errorData['error']) && $errorData['error'] === 'ALREADY_SUBSCRIBED') {
                    return [
                        'success' => false,
                        'message' => 'This email is already subscribed to our newsletter.',
                        'code'    => 'ALREADY_SUBSCRIBED',
                    ];
                }

                Log::warning('Newsletter subscription failed', [
                    'email'    => $email,
                    'status'   => $response->status(),
                    'response' => $errorData,
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to subscribe. Please try again later.',
                    'error'   => $errorData,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Newsletter subscription error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while processing your subscription. Please try again later.',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Unsubscribe an email address from the newsletter.
     *
     * @param string $email The email address to unsubscribe
     *
     * @return array Result array with success status and message
     */
    public function unsubscribe(string $email): array
    {
        try {
            $this->validateConfiguration();

            $response = Http::timeout($this->timeout)
                ->asForm() // Use form encoding instead of JSON
                ->post("{$this->baseUrl}/api/unsubscribe/{$this->listId}?access_token={$this->apiToken}", [
                    'EMAIL' => $email,
                ]);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('Newsletter unsubscription successful', [
                    'email'    => $email,
                    'list_id'  => $this->listId,
                    'response' => $result,
                ]);

                return [
                    'success' => true,
                    'message' => 'Successfully unsubscribed from newsletter.',
                    'data'    => $result,
                ];
            } else {
                $errorData = $response->json();

                Log::warning('Newsletter unsubscription failed', [
                    'email'    => $email,
                    'status'   => $response->status(),
                    'response' => $errorData,
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to unsubscribe. Please try again later.',
                    'error'   => $errorData,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Newsletter unsubscription error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while processing your unsubscription. Please try again later.',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if an email is subscribed to the newsletter.
     *
     * @param string $email The email address to check
     *
     * @return array Result array with subscription status
     */
    public function checkSubscription(string $email): array
    {
        try {
            $this->validateConfiguration();

            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/subscription/{$this->listId}/{$email}?access_token={$this->apiToken}");

            if ($response->successful()) {
                $result = $response->json();

                return [
                    'success'    => true,
                    'subscribed' => $result['subscribed'] ?? false,
                    'data'       => $result,
                ];
            } else {
                return [
                    'success'    => false,
                    'subscribed' => false,
                    'error'      => $response->json(),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Newsletter subscription check error', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success'    => false,
                'subscribed' => false,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate that the service is properly configured.
     *
     * @throws \InvalidArgumentException If configuration is missing
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->baseUrl)) {
            throw new \InvalidArgumentException('Mailtrain base URL is not configured');
        }

        if (empty($this->apiToken)) {
            throw new \InvalidArgumentException('Mailtrain API token is not configured');
        }

        if (empty($this->listId)) {
            throw new \InvalidArgumentException('Mailtrain list ID is not configured');
        }
    }

    /**
     * Test the connection to Mailtrain API.
     *
     * @return array Result array with connection status
     */
    public function testConnection(): array
    {
        try {
            $this->validateConfiguration();

            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/lists?access_token={$this->apiToken}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Successfully connected to Mailtrain API',
                    'data'    => $response->json(),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to Mailtrain API',
                    'error'   => $response->json(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed',
                'error'   => $e->getMessage(),
            ];
        }
    }
}
