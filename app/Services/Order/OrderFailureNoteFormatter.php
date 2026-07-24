<?php

namespace App\Services\Order;

use Illuminate\Http\Client\RequestException;
use Throwable;

class OrderFailureNoteFormatter
{
    /**
     * Prefer the full HTTP response body over {@see RequestException::getMessage()},
     * which Laravel truncates and often embeds as invalid JSON.
     */
    public function fromThrowable(Throwable $throwable): string
    {
        if ($throwable instanceof RequestException) {
            $response = $throwable->response;
            if ($response !== null) {
                $json = $response->json();
                if (is_array($json)) {
                    foreach (['message', 'error', 'error_message', 'description'] as $key) {
                        $candidate = data_get($json, $key);
                        if (is_string($candidate) && trim($candidate) !== '') {
                            return trim($candidate);
                        }
                    }
                }

                $fromBody = $this->toPlainText($response->body());
                if ($fromBody !== '' && ! str_contains($fromBody, 'HTTP request returned')) {
                    return $fromBody;
                }
            }
        }

        return $this->toPlainText($throwable->getMessage());
    }

    public function supplierFailureNote(string $error): string
    {
        return 'Supplier fulfillment failed: '.$this->toPlainText($error);
    }

    public function directTopUpFailureNote(string $error, string $prefix = 'Direct top-up fulfillment failed:'): string
    {
        return $prefix.' '.$this->toPlainText($error);
    }

    public function toPlainText(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return $trimmed;
        }

        $fromMessage = $this->extractJsonMessageField($trimmed);
        if ($fromMessage !== null && $fromMessage !== '') {
            return $fromMessage;
        }

        if (preg_match('/status code \d+:\s*\n?(.*)$/s', $trimmed, $matches)) {
            $afterHttp = trim($matches[1]);
            $fromMessage = $this->extractJsonMessageField($afterHttp);
            if ($fromMessage !== null && $fromMessage !== '') {
                return $fromMessage;
            }
        }

        return $this->stripTruncatedMarker($trimmed);
    }

    private function extractJsonMessageField(string $raw): ?string
    {
        if (preg_match('/"message"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $raw, $matches)) {
            return $this->stripTruncatedMarker(stripcslashes($matches[1]));
        }

        if (preg_match('/"message"\s*:\s*"(.+)/s', $raw, $matches)) {
            return $this->stripTruncatedMarker(rtrim($matches[1], "\" \n\r\t"));
        }

        return null;
    }

    private function stripTruncatedMarker(string $value): string
    {
        return trim(preg_replace('/\s*\(truncated\.\.\.\)\s*$/', '', $value) ?? $value);
    }
}
