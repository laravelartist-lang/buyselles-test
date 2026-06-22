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
    | Print mode
    |--------------------------------------------------------------------------
    |
    | preview — ask user: direct thermal (QZ) or browser preview (default)
    | qz      — guided QZ Tray wizard: install → connect → pick printer → print
    | auto    — silent QZ print if printer saved, else browser preview
    |
    */
    'mode' => env('QZ_TRAY_MODE', 'qz'),

    'connect_timeout_seconds' => (int) env('QZ_TRAY_CONNECT_TIMEOUT', 120),

    'default_printer' => env('QZ_TRAY_DEFAULT_PRINTER'),

    'paper_width_mm' => (int) env('QZ_TRAY_PAPER_WIDTH', 80),

    'storage_path' => storage_path('app/qz-tray'),

    'private_key_path' => storage_path('app/qz-tray/private-key.pem'),

    'certificate_path' => storage_path('app/qz-tray/digital-certificate.txt'),

];
