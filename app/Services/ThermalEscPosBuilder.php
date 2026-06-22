<?php

namespace App\Services;

class ThermalEscPosBuilder
{
    private const ESC = "\x1B";

    private const GS = "\x1D";

    /**
     * Build one thermal receipt (one digital code) as raw ESC/POS bytes.
     *
     * @param  array{productName: string, code: string, pin?: string|null, serial?: string|null, expiry?: string|null, orderId?: int|string|null}  $code
     */
    public function buildSingleCodeReceipt(
        string $shopName,
        string $shopTagline,
        array $code,
        int $paperWidthMm = 80,
        ?int $codeIndex = null,
        ?int $codeTotal = null
    ): string {
        $bytes = '';
        $bytes .= self::ESC.'@';
        $bytes .= $this->setAlign('center');
        $bytes .= $this->setBold(true);
        $bytes .= $this->setTextSize(2, 2);
        $bytes .= $this->line($this->sanitize($shopName));
        $bytes .= $this->setTextSize(1, 1);
        $bytes .= $this->setBold(false);
        $bytes .= $this->line($this->sanitize($shopTagline));
        $bytes .= $this->horizontalRule($paperWidthMm);
        $bytes .= $this->setBold(true);
        $bytes .= $this->line('Digital Product Receipt');
        $bytes .= $this->setBold(false);

        if ($codeIndex !== null && $codeTotal !== null && $codeTotal > 1) {
            $bytes .= $this->line('Receipt '.$codeIndex.'/'.$codeTotal);
        }

        $bytes .= $this->line('Date: '.now()->format('Y-m-d H:i:s'));
        $bytes .= $this->horizontalRule($paperWidthMm);

        $bytes .= $this->setAlign('left');
        $bytes .= $this->setBold(true);
        $bytes .= $this->line($this->sanitize($code['productName'] ?? 'Digital Product'));
        $bytes .= $this->setBold(false);
        $bytes .= $this->line('Code: '.$this->sanitize($code['code'] ?? ''));

        if (! empty($code['pin'])) {
            $bytes .= $this->line('PIN: '.$this->sanitize((string) $code['pin']));
        }

        if (! empty($code['serial'])) {
            $bytes .= $this->line('Serial: '.$this->sanitize((string) $code['serial']));
        }

        if (! empty($code['expiry'])) {
            $bytes .= $this->line('Expiry: '.$this->sanitize((string) $code['expiry']));
        }

        if (! empty($code['orderId'])) {
            $bytes .= $this->line('Order: #'.$this->sanitize((string) $code['orderId']));
        }

        $bytes .= $this->feed(1);
        $bytes .= $this->horizontalRule($paperWidthMm);
        $bytes .= $this->setAlign('center');
        $bytes .= $this->line('Thank you for shopping!');
        $bytes .= $this->feed(2);
        $bytes .= $this->cut();

        return $bytes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $codes
     * @return array<int, string>
     */
    public function buildJobs(string $shopName, string $shopTagline, array $codes, int $paperWidthMm = 80): array
    {
        $jobs = [];
        $total = count($codes);

        foreach ($codes as $index => $code) {
            $jobs[] = $this->buildSingleCodeReceipt(
                $shopName,
                $shopTagline,
                $code,
                $paperWidthMm,
                $index + 1,
                $total
            );
        }

        return $jobs;
    }

    private function setAlign(string $align): string
    {
        $mode = match ($align) {
            'center' => 1,
            'right' => 2,
            default => 0,
        };

        return self::ESC.'a'.chr($mode);
    }

    private function setBold(bool $enabled): string
    {
        return self::ESC.'E'.chr($enabled ? 1 : 0);
    }

    private function setTextSize(int $width, int $height): string
    {
        $width = max(1, min(8, $width)) - 1;
        $height = max(1, min(8, $height)) - 1;
        $n = ($width << 4) | $height;

        return self::GS.'!'.chr($n);
    }

    private function line(string $text): string
    {
        return $text."\n";
    }

    private function feed(int $lines = 1): string
    {
        return self::ESC.'d'.chr(max(0, min(255, $lines)));
    }

    private function horizontalRule(int $paperWidthMm): string
    {
        $chars = $paperWidthMm >= 80 ? 32 : 24;

        return str_repeat('-', $chars)."\n";
    }

    private function cut(): string
    {
        return self::GS.'V'.chr(66).chr(0);
    }

    private function sanitize(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;

        return trim($text);
    }
}
