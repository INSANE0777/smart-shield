<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Captcha Service for handling multiple CAPTCHA providers.
 *
 * This service provides a unified interface for both reCAPTCHA and hCaptcha
 * verification systems, automatically using the configured provider.
 */
class CaptchaService
{
    /**
     * Get the site key for the configured captcha provider.
     *
     * @return string The public site key
     */
    public function getSiteKey(): string
    {
        $provider = config('captcha.provider');

        return config("captcha.{$provider}.site_key");
    }

    /**
     * Get the currently configured captcha provider.
     *
     * @return string The provider name ('recaptcha' or 'hcaptcha')
     */
    public function getProvider(): string
    {
        return config('captcha.provider');
    }

    /**
     * Verify a captcha response token with the configured provider.
     *
     * @param string      $token The captcha response token from the client
     * @param string|null $ip    Optional client IP address for verification
     *
     * @return bool True if verification successful, false otherwise
     */
    public function verify(string $token, ?string $ip = null): bool
    {
        $provider = config('captcha.provider');
        $secret = config("captcha.{$provider}.secret_key");
        $url = config("captcha.{$provider}.verify_url");

        $response = Http::asForm()->post($url, array_filter([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]));

        $data = $response->json();

        // Both APIs return 'success' boolean
        return $data['success'] ?? false;
    }
}
