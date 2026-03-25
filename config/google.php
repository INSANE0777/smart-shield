<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google AdSense Configuration
    |--------------------------------------------------------------------------
    |
    | Toggle AdSense with GOOGLE_ADSENSE_ENABLED and set your publisher ID.
    |
    */
    'adsense' => [
        'enabled'      => env('GOOGLE_ADSENSE_ENABLED', false),
        'publisher_id' => env('GOOGLE_ADSENSE_PUBLISHER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Google Analytics tracking ID. Currently hardcoded in templates but
    | could be moved here for consistency.
    |
    */
    'analytics' => [
        'tracking_id' => env('GOOGLE_ANALYTICS_ID', 'G-BYWNNLXEYV'),
    ],
];
