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

    'enabled' => filter_var(env('QZ_TRAY_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

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

    'connect_timeout_seconds' => (int) env('QZ_TRAY_CONNECT_TIMEOUT', 60),

    'default_printer' => env('QZ_TRAY_DEFAULT_PRINTER'),

    'paper_width_mm' => (int) env('QZ_TRAY_PAPER_WIDTH', 80),

    /*
    |--------------------------------------------------------------------------
    | Local test mode (no physical printer required)
    |--------------------------------------------------------------------------
    |
    | Injects a fake printer that writes raw ESC/POS bytes to a local file via
    | QZ Tray. The path must exist on the computer running QZ Tray (client PC).
    |
    */
    'test_mode' => filter_var(env('QZ_TRAY_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN),

    'test_printer_name' => env('QZ_TRAY_TEST_PRINTER_NAME', 'TEST - Validate ESC/POS (no printer)'),

    /*
     * Relative filename written inside the QZ Tray sandbox (~/.qz/sandbox/...) via qz.file.write.
     * Do not use /tmp — legacy print-to-file is blocked by QZ Tray 2.2+.
     */
    'test_output_file' => env('QZ_TRAY_TEST_OUTPUT_FILE', 'buyselles-thermal-test.raw'),

    'storage_path' => storage_path('app/qz-tray'),

    'private_key_path' => storage_path('app/qz-tray/private-key.pem'),

    'certificate_path' => storage_path('app/qz-tray/digital-certificate.txt'),

    /*
    |--------------------------------------------------------------------------
    | Web server group (Unix permissions for generated keys)
    |--------------------------------------------------------------------------
    |
    | Keys must be readable by PHP-FPM (typically www-data) for /qz-tray/sign.
    | `php artisan qz-tray:generate-keys` assigns this group to key files.
    |
    */
    'web_group' => env('QZ_TRAY_WEB_GROUP', 'www-data'),

];
