<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

class GenerateQzTrayKeys extends Command
{
    protected $signature = 'qz-tray:generate-keys {--force : Overwrite existing keys}';

    protected $description = 'Generate RSA key pair and certificate for QZ Tray signing';

    public function handle(): int
    {
        $directory = config('qz-tray.storage_path');
        $privateKeyPath = config('qz-tray.private_key_path');
        $certificatePath = config('qz-tray.certificate_path');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error('Unable to create storage directory: '.$directory);

            return self::FAILURE;
        }

        if (
            (! $this->option('force'))
            && (is_readable($privateKeyPath) || is_readable($certificatePath))
        ) {
            $this->warn('QZ Tray keys already exist. Use --force to overwrite.');

            return self::SUCCESS;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($privateKey === false) {
            $this->error('Unable to generate private key.');

            return self::FAILURE;
        }

        $opensslConfigPath = $directory.DIRECTORY_SEPARATOR.'openssl.cnf';
        $sanHosts = array_values(array_unique(array_filter([$host, 'localhost'])));

        $altNames = '';
        foreach ($sanHosts as $index => $sanHost) {
            $altNames .= 'DNS.'.($index + 1).' = '.$sanHost.PHP_EOL;
        }

        file_put_contents($opensslConfigPath, <<<CONF
[ req ]
default_bits = 2048
prompt = no
default_md = sha512
distinguished_name = req_distinguished_name
req_extensions = v3_req

[ req_distinguished_name ]
CN = {$host}
O = {$this->escapeOpenSslConfigValue((string) config('app.name', 'Buyselles'))}

[ v3_req ]
subjectAltName = @alt_names

[ alt_names ]
{$altNames}IP.1 = 127.0.0.1
CONF);

        $dn = [
            'commonName' => $host,
            'organizationName' => (string) config('app.name', 'Buyselles'),
        ];

        $csr = openssl_csr_new($dn, $privateKey, [
            'digest_alg' => 'sha512',
            'config' => $opensslConfigPath,
            'req_extensions' => 'v3_req',
        ]);

        if ($csr === false) {
            $this->error('Unable to generate certificate signing request.');

            return self::FAILURE;
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, 825, [
            'digest_alg' => 'sha512',
            'config' => $opensslConfigPath,
            'x509_extensions' => 'v3_req',
        ]);

        if ($certificate === false) {
            $this->error('Unable to sign certificate.');

            return self::FAILURE;
        }

        $privateKeyExport = '';
        $certificateExport = '';

        if (! openssl_pkey_export($privateKey, $privateKeyExport)) {
            throw new RuntimeException('Unable to export private key.');
        }

        if (! openssl_x509_export($certificate, $certificateExport)) {
            throw new RuntimeException('Unable to export certificate.');
        }

        file_put_contents($privateKeyPath, $privateKeyExport);
        file_put_contents($certificatePath, $certificateExport);
        chmod($privateKeyPath, 0600);

        $this->info('QZ Tray keys generated successfully.');
        $this->line('Private key: '.$privateKeyPath);
        $this->line('Certificate: '.$certificatePath);
        $this->newLine();
        $this->line('Next steps:');
        $this->line('1. Install QZ Tray on each client machine: https://qz.io/download/');
        $this->line('2. Set QZ_TRAY_DEFAULT_PRINTER in .env (optional — users can pick in browser)');
        $this->line('3. Trust this site certificate when QZ Tray prompts on first print');

        return self::SUCCESS;
    }

    private function escapeOpenSslConfigValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
