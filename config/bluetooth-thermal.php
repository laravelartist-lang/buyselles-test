<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bluetooth thermal printing (Web Bluetooth API)
    |--------------------------------------------------------------------------
    |
    | Browser direct BLE printing — no QZ Tray required for compatible printers.
    | Enable test mode to mimic pairing/printing without a physical device.
    |
    */

    'enabled' => filter_var(env('BLUETOOTH_THERMAL_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'test_mode' => filter_var(
        env('BLUETOOTH_THERMAL_TEST_MODE', env('APP_DEBUG', false)),
        FILTER_VALIDATE_BOOLEAN
    ),

    'test_printer_name' => env('BLUETOOTH_THERMAL_TEST_PRINTER_NAME', 'DEMO - Bluetooth Thermal Printer'),

];
