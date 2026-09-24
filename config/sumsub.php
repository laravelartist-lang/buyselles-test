<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sumsub (Idensic) KYC Configuration
    |--------------------------------------------------------------------------
    |
    | A single set of credentials is shared by every client: the web
    | (WebSDK), the customer mobile app and the vendor mobile app. Clients
    | never talk to Sumsub directly - the backend mints a short lived
    | access token which is then handed to the SDK on the device.
    |
    */

    'enabled' => env('SUMSUB_ENABLED', false),

    'base_url' => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),

    'app_token' => env('SUMSUB_APP_TOKEN'),

    'secret_key' => env('SUMSUB_SECRET_KEY'),

    /*
    | The webhook secret is generated when the webhook is created in the
    | Sumsub dashboard and is used to verify the X-Payload-Digest header.
    */
    'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET'),

    /*
    | Lifespan of a generated SDK access token, in seconds.
    */
    'access_token_ttl' => (int) env('SUMSUB_ACCESS_TOKEN_TTL', 600),

    /*
    | Request timeout (seconds) when calling the Sumsub API.
    */
    'timeout' => (int) env('SUMSUB_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Verification Levels
    |--------------------------------------------------------------------------
    |
    | Each level is configured in the Sumsub dashboard with the required
    | document types (passport / national ID / driving licence) and the
    | liveness (face scan) step enabled.
    |
    */

    'levels' => [
        'customer' => env('SUMSUB_LEVEL_CUSTOMER', 'customer-kyc'),
        'vendor' => env('SUMSUB_LEVEL_VENDOR', 'vendor-kyc'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Purchase Threshold
    |--------------------------------------------------------------------------
    |
    | A customer must complete KYC once their lifetime paid purchases reach
    | this amount, including the order currently being placed.
    |
    */

    'customer_threshold' => (float) env('SUMSUB_CUSTOMER_THRESHOLD', 100),

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    */

    'webhook_path' => env('SUMSUB_WEBHOOK_PATH', 'webhooks/sumsub'),

    'log_channel' => env('SUMSUB_LOG_CHANNEL'),

];
