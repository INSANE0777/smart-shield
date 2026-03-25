<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Provider Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration determines which CAPTCHA provider to use and their
    | respective settings. Supported providers: 'recaptcha', 'hcaptcha'
    |
    */

    'provider' => env('CAPTCHA_PROVIDER', 'recaptcha'),

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA Configuration
    |--------------------------------------------------------------------------
    */
    'recaptcha' => [
        'site_key'   => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
    ],

    /*
    |--------------------------------------------------------------------------
    | hCaptcha Configuration
    |--------------------------------------------------------------------------
    */
    'hcaptcha' => [
        'site_key'   => env('HCAPTCHA_SITE_KEY'),
        'secret_key' => env('HCAPTCHA_SECRET_KEY'),
        'verify_url' => 'https://hcaptcha.com/siteverify',
    ],

];
