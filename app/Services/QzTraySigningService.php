<?php

namespace App\Services;

use RuntimeException;

class QzTraySigningService
{
    public function isConfigured(): bool
    {
        return is_readable(config('qz-tray.private_key_path'))
            && is_readable(config('qz-tray.certificate_path'));
    }

    /**
     * @return array{
     *     qzTrayConfigured: bool,
     *     qzTrayEnabled: bool,
     *     qzTrayMode: string,
     *     qzTrayAvailable: bool,
     *     qzTrayActive: bool
     * }
     */
    public function viewVariables(): array
    {
        $configured = $this->isConfigured();
        $enabled = filter_var(config('qz-tray.enabled'), FILTER_VALIDATE_BOOLEAN);
        $mode = (string) config('qz-tray.mode', 'preview');
        $available = $configured && $enabled;

        return [
            'qzTrayConfigured' => $configured,
            'qzTrayEnabled' => $enabled,
            'qzTrayMode' => $mode,
            'qzTrayAvailable' => $available,
            'qzTrayActive' => $available && in_array($mode, ['qz', 'auto'], true),
        ];
    }

    public function getCertificate(): string
    {
        $path = config('qz-tray.certificate_path');

        if (! is_readable($path)) {
            throw new RuntimeException('QZ Tray certificate is not configured. Run: php artisan qz-tray:generate-keys');
        }

        return trim((string) file_get_contents($path));
    }

    public function sign(string $request): string
    {
        $privateKeyPath = config('qz-tray.private_key_path');

        if (! is_readable($privateKeyPath)) {
            throw new RuntimeException('QZ Tray private key is not configured. Run: php artisan qz-tray:generate-keys');
        }

        $privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyPath));

        if ($privateKey === false) {
            throw new RuntimeException('Unable to load QZ Tray private key.');
        }

        $signature = '';
        $signed = openssl_sign($request, $signature, $privateKey, OPENSSL_ALGO_SHA512);

        if (! $signed) {
            throw new RuntimeException('Unable to sign QZ Tray request.');
        }

        return base64_encode($signature);
    }
}
