<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App Store Connect API
    |--------------------------------------------------------------------------
    |
    | Credentials match GitHub Actions secrets used for TestFlight uploads.
    | Set APP_STORE_CONNECT_API_KEY to the full .p8 private key contents.
    |
    */

    'bundle_id' => env('APP_STORE_CONNECT_BUNDLE_ID', env('IOS_BUNDLE_ID', 'com.buyselles.app')),

    'key_id' => env('APP_STORE_CONNECT_KEY_ID'),

    'issuer_id' => env('APP_STORE_CONNECT_ISSUER_ID'),

    'private_key' => env('APP_STORE_CONNECT_API_KEY'),

    'private_key_path' => env('APP_STORE_CONNECT_PRIVATE_KEY_PATH'),

    'territory' => env('APPLE_IAP_TERRITORY', 'USA'),

    'locale' => env('APPLE_IAP_LOCALE', 'en-US'),

    'product_id_prefix' => env('APPLE_IAP_PRODUCT_ID_PREFIX', 'com.buyselles.app.digital.'),

    'review_note' => env(
        'APPLE_IAP_REVIEW_NOTE',
        'Digital consumable product for the Buyselles marketplace iOS app.'
    ),

];
