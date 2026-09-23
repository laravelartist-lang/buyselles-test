<?php

namespace App\DTOs\Supplier;

/**
 * Result of a live supplier stock / balance check for checkout.
 */
readonly class AvailabilityResult
{
    /**
     * @param  string[]  $errors
     * @param  int[]  $failedProductIds
     */
    public function __construct(
        public bool $ok,
        public array $errors = [],
        public array $failedProductIds = [],
    ) {}

    public static function available(): self
    {
        return new self(ok: true);
    }

    /**
     * @param  string[]  $errors
     * @param  int[]  $failedProductIds
     */
    public static function unavailable(array $errors, array $failedProductIds = []): self
    {
        return new self(ok: false, errors: $errors, failedProductIds: $failedProductIds);
    }

    public function errorMessage(): string
    {
        return implode(' | ', $this->errors);
    }
}
