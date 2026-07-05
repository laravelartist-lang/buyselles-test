<?php

namespace App\Services\Supplier\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;

trait MakesResilientHttpRequests
{
    /**
     * Apply connect timeout, request timeout, and retries for transient network failures.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function applyResilientHttpDefaults(
        PendingRequest $request,
        array $settings = [],
        int $defaultTimeout = 120,
    ): PendingRequest {
        $connectTimeout = (int) ($settings['http_connect_timeout'] ?? 30);
        $timeout = (int) ($settings['http_timeout'] ?? $defaultTimeout);
        $retries = (int) ($settings['http_retries'] ?? 3);
        $retryDelayMs = (int) ($settings['http_retry_delay_ms'] ?? 2000);

        return $request
            ->connectTimeout($connectTimeout)
            ->timeout($timeout)
            ->retry(
                $retries,
                $retryDelayMs,
                fn (\Throwable $exception): bool => $this->shouldRetrySupplierHttp($exception),
            );
    }

    protected function shouldRetrySupplierHttp(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'curl error 28')
            || str_contains($message, 'curl error 6')
            || str_contains($message, 'curl error 7')
            || str_contains($message, 'timed out')
            || str_contains($message, 'could not resolve host')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection reset');
    }
}
