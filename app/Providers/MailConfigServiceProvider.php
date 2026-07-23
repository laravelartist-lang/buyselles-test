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
            $emailServices = getActiveMailConfig();
            if ($emailServices !== null) {
                $this->applyMailConfiguration($emailServices);
            }
        } catch (Exception $ex) {
        }
    }

    /**
     * @param  array<string, mixed>  $emailServices
     */
    private function applyMailConfiguration(array $emailServices): void
    {
        $encryption = strtolower((string) ($emailServices['encryption'] ?? 'tls'));
        $port = (int) ($emailServices['port'] ?? 587);

        Config::set('mail.default', 'smtp');
        Config::set('mail.driver', 'smtp');

        // Legacy mail config keys (still used when config/mail.php defines "driver").
        Config::set('mail.host', $emailServices['host']);
        Config::set('mail.port', $port);
        Config::set('mail.username', $emailServices['username']);
        Config::set('mail.password', $emailServices['password']);
        Config::set('mail.encryption', $encryption);

        // Laravel 12 mailer config.
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $emailServices['host']);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.username', $emailServices['username']);
        Config::set('mail.mailers.smtp.password', $emailServices['password']);
        Config::set('mail.mailers.smtp.encryption', $encryption);

        Config::set('mail.from.address', $emailServices['email_id']);
        Config::set('mail.from.name', $emailServices['name']);
    }
}
