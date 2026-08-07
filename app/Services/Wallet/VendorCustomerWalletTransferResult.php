<?php

namespace App\Services\Wallet;

use App\Models\WalletTransaction;
use App\Models\WalletTransfer;

class VendorCustomerWalletTransferResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?WalletTransfer $transfer = null,
        public ?WalletTransaction $walletTransaction = null,
        public ?float $vendorTotalEarningUsd = null,
        public ?string $failureCode = null,
    ) {}
}
