<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            $emailServices_smtp = getActiveMailConfig();
            if ($emailServices_smtp !== null) {
                Config::set('mail.default', 'smtp');
                Config::set('mail.mailers.smtp.transport', 'smtp');
                Config::set('mail.mailers.smtp.host', $emailServices_smtp['host']);
                Config::set('mail.mailers.smtp.port', (int) $emailServices_smtp['port']);
                Config::set('mail.mailers.smtp.username', $emailServices_smtp['username']);
                Config::set('mail.mailers.smtp.password', $emailServices_smtp['password']);
                Config::set('mail.mailers.smtp.encryption', strtolower((string) $emailServices_smtp['encryption']));
                Config::set('mail.from.address', $emailServices_smtp['email_id']);
                Config::set('mail.from.name', $emailServices_smtp['name']);
            }
        } catch (Exception $ex) {
        }
    }
}
