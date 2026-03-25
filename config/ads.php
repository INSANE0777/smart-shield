<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ezoic Ads Configuration
    |--------------------------------------------------------------------------
    |
    | Enable Ezoic with a single flag. Scripts are rendered from the
    | Ezoic-provided defaults in the view partial.
    |
    */
    'ezoic' => [
        'enabled' => env('EZOIC_ENABLED', false),
    ],
];

