<?php

namespace App\Services\Order;

class OrderFailureNoteFormatter
{
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
