<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QZ Tray Thermal Printing
    |--------------------------------------------------------------------------
    |
    | Enables direct ESC/POS printing via the QZ Tray desktop agent.
    | Run `php artisan qz-tray:generate-keys` once per environment, then
    | install QZ Tray on each POS/client machine.
    |
    */

    'enabled' => env('QZ_TRAY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Print mode (until client confirms final thermal setup)
    |--------------------------------------------------------------------------
    |
    | preview — open 80mm receipt in new tab (works everywhere; default)
    | auto    — try QZ if configured + printer saved, else preview (no modals)
    | qz      — full QZ Tray flow with printer picker (requires QZ on client PC)
    |
    */
    'mode' => env('QZ_TRAY_MODE', 'preview'),

    'connect_timeout_seconds' => (int) env('QZ_TRAY_CONNECT_TIMEOUT', 4),

    'default_printer' => env('QZ_TRAY_DEFAULT_PRINTER'),

    'paper_width_mm' => (int) env('QZ_TRAY_PAPER_WIDTH', 80),

    'storage_path' => storage_path('app/qz-tray'),

    'private_key_path' => storage_path('app/qz-tray/private-key.pem'),

    'certificate_path' => storage_path('app/qz-tray/digital-certificate.txt'),

];
