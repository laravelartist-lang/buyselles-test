<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\DatabaseSafetyServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\BroadcastServiceProvider::class,
    // App\Providers\EventServiceProvider::class,
    App\Providers\FirebaseServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Providers\MailConfigServiceProvider::class,
    App\Providers\PaymentConfigProvider::class,
    App\Providers\ConfigServiceProvider::class,
    App\Providers\ThemeServiceProvider::class,
    App\Providers\SocialLoginServiceProvider::class,
    App\Providers\InterfaceServiceProvider::class,
    App\Providers\ObserverServiceProvider::class,

    // Third-party packages
    Intervention\Image\ImageServiceProvider::class,
    Maatwebsite\Excel\ExcelServiceProvider::class,
    Madnest\Madzipper\MadzipperServiceProvider::class,
];
