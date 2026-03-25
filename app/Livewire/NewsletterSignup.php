<?php

namespace App\Livewire;

use App\Services\CaptchaService;
use App\Services\NewsletterService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Livewire component for newsletter subscription with reCAPTCHA v3 protection.
 */
class NewsletterSignup extends Component
{
    public $email = '';
    public $g_recaptcha_response = '';
    public $loading = false;
    public $message = '';
    public $messageType = ''; // 'success' or 'error'
    public $subscribed = false;

    protected $rules = [
        'email' => 'required|email|max:255',
    ];

    protected $messages = [
        'email.required' => 'Email address is required.',
        'email.email'    => 'Please enter a valid email address.',
        'email.max'      => 'Email address is too long.',
    ];

    public function mount()
    {
        $this->resetState();
    }

    public function subscribe()
    {
        try {
            $this->loading = true;
            $this->message = '';
            $this->messageType = '';

            // Validate email
            $this->validate();

            // reCAPTCHA v3 validation (DISABLED for newsletter)
            /*
            if (!app()->environment(['local', 'testing'])) {
                if (empty($this->g_recaptcha_response)) {
                    $this->setErrorMessage('Please wait for security verification to complete, then try again.');
                    // Trigger fresh reCAPTCHA generation
                    $this->dispatch('generateRecaptcha');
                    return;
                }

                $captchaService = app(CaptchaService::class);
                if (!$captchaService->verify($this->g_recaptcha_response)) {
                    $this->setErrorMessage('Security verification failed. Please try again.');
                    // Reset reCAPTCHA
                    $this->dispatch('resetRecaptcha');
                    return;
                }
            }
            */

            // Subscribe via service
            $newsletterService = app(NewsletterService::class);
            $result = $newsletterService->subscribe($this->email);

            if ($result['success']) {
                $this->subscribed = true;
                $this->setSuccessMessage($result['message']);
                $this->email = ''; // Clear email after successful subscription

                Log::info('Newsletter subscription successful via Livewire', [
                    'email'     => $this->email,
                    'component' => 'NewsletterSignup',
                ]);
            } else {
                // Handle specific error cases
                if (isset($result['code']) && $result['code'] === 'ALREADY_SUBSCRIBED') {
                    $this->setErrorMessage($result['message']);
                } else {
                    $this->setErrorMessage($result['message'] ?? 'Failed to subscribe. Please try again later.');
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->setErrorMessage($e->validator->errors()->first());
        } catch (\Exception $e) {
            Log::error('Newsletter subscription Livewire error', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->setErrorMessage('An unexpected error occurred. Please try again later.');
        } finally {
            $this->loading = false;
        }
    }

    public function resetForm()
    {
        $this->resetState();
        $this->dispatch('resetRecaptcha');
    }

    private function resetState()
    {
        $this->email = '';
        $this->g_recaptcha_response = '';
        $this->loading = false;
        $this->message = '';
        $this->messageType = '';
        $this->subscribed = false;
    }

    private function setSuccessMessage(string $message)
    {
        $this->message = $message;
        $this->messageType = 'success';
    }

    private function setErrorMessage(string $message)
    {
        $this->message = $message;
        $this->messageType = 'error';
    }

    public function render()
    {
        return view('livewire.newsletter-signup');
    }
}
