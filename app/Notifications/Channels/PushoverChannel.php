<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushoverChannel
{
    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toPushover($notifiable);

        if (!$message) {
            return null;
        }

        // Check if required configuration is present
        if (empty($message['token']) || empty($message['user'])) {
            Log::warning('Pushover notification skipped - missing token or user configuration');

            return null;
        }

        // Prevent real sends in testing environment
        if (app()->environment('testing')) {
            // Return a fake response for testing
            return new class() {
                public function successful()
                {
                    return true;
                }

                public function status()
                {
                    return 200;
                }

                public function body()
                {
                    return '{"status":1,"request":"test-fake"}';
                }

                public function json()
                {
                    return ['status' => 1, 'request' => 'test-fake'];
                }
            };
        }

        try {
            $response = Http::timeout(30)
                ->retry(3, 5000) // Retry 3 times with 5 second delay
                ->post('https://api.pushover.net/1/messages.json', $message);

            // Log the response for debugging
            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Pushover notification sent successfully', [
                    'request_id' => $responseData['request'] ?? null,
                    'status'     => $responseData['status'] ?? null,
                ]);
            } else {
                $statusCode = $response->status();
                $responseBody = $response->body();

                Log::error('Pushover notification failed', [
                    'status_code'  => $statusCode,
                    'response'     => $responseBody,
                    'message_data' => $message,
                ]);

                // Handle specific error cases
                if ($statusCode >= 400 && $statusCode < 500) {
                    // Client error - don't retry
                    Log::warning('Pushover client error - check configuration', [
                        'status'   => $statusCode,
                        'response' => $responseBody,
                    ]);
                } elseif ($statusCode >= 500) {
                    // Server error - already retried via Http::retry()
                    Log::error('Pushover server error after retries', [
                        'status'   => $statusCode,
                        'response' => $responseBody,
                    ]);
                }
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Pushover notification exception', [
                'error'        => $e->getMessage(),
                'message_data' => $message,
            ]);

            // In tests, we might want to return a mock response
            if (app()->environment('testing')) {
                // Create a mock response for testing
                return new class() {
                    public function successful()
                    {
                        return false;
                    }

                    public function status()
                    {
                        return 500;
                    }

                    public function body()
                    {
                        return 'Test exception';
                    }

                    public function json()
                    {
                        return [];
                    }
                };
            }

            throw $e;
        }
    }
}
