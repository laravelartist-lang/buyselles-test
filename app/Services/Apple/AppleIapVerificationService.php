<?php

namespace App\Services\Apple;

use App\Models\BusinessSetting;
use App\Models\IapTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AppleIapVerificationService
{
    /**
     * @return array{valid: bool, transaction_id: string, apple_product_id: string, environment: string|null, message?: string}
     */
    public function verify(string $verificationData, string $expectedAppleProductId, ?string $expectedTransactionId = null): array
    {
        if (Str::startsWith($verificationData, 'eyJ')) {
            return $this->verifyStoreKitJws($verificationData, $expectedAppleProductId, $expectedTransactionId);
        }

        return $this->verifyLegacyReceipt($verificationData, $expectedAppleProductId, $expectedTransactionId);
    }

    /**
     * @return array{valid: bool, transaction_id: string, apple_product_id: string, environment: string|null, message?: string}
     */
    private function verifyStoreKitJws(string $jws, string $expectedAppleProductId, ?string $expectedTransactionId): array
    {
        $parts = explode('.', $jws);
        if (count($parts) < 2) {
            return $this->invalid('Invalid StoreKit transaction payload.');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (! is_array($payload)) {
            return $this->invalid('Unable to decode StoreKit transaction payload.');
        }

        $transactionId = (string) ($payload['transactionId'] ?? $payload['originalTransactionId'] ?? '');
        $productId = (string) ($payload['productId'] ?? '');
        $bundleId = (string) ($payload['bundleId'] ?? '');

        if ($expectedTransactionId !== null && $expectedTransactionId !== '' && $transactionId !== $expectedTransactionId) {
            return $this->invalid('Transaction ID mismatch.');
        }

        if ($productId !== $expectedAppleProductId) {
            return $this->invalid('Apple product ID mismatch.');
        }

        if ($bundleId !== '' && $bundleId !== $this->expectedBundleId()) {
            return $this->invalid('Unexpected app bundle ID in transaction.');
        }

        if ($transactionId === '' || $productId === '') {
            return $this->invalid('Missing transaction metadata.');
        }

        if (IapTransaction::query()->where('transaction_id', $transactionId)->exists()) {
            return $this->invalid('This App Store transaction has already been used.');
        }

        return [
            'valid' => true,
            'transaction_id' => $transactionId,
            'apple_product_id' => $productId,
            'environment' => $payload['environment'] ?? null,
        ];
    }

    /**
     * @return array{valid: bool, transaction_id: string, apple_product_id: string, environment: string|null, message?: string}
     */
    private function verifyLegacyReceipt(string $receiptData, string $expectedAppleProductId, ?string $expectedTransactionId): array
    {
        $sharedSecret = $this->sharedSecret();
        if ($sharedSecret === '') {
            return $this->invalid('Apple IAP shared secret is not configured.');
        }

        $payload = [
            'receipt-data' => $receiptData,
            'password' => $sharedSecret,
            'exclude-old-transactions' => true,
        ];

        $response = Http::timeout(20)->post('https://buy.itunes.apple.com/verifyReceipt', $payload);
        $body = $response->json();

        if ((int) ($body['status'] ?? -1) === 21007) {
            $response = Http::timeout(20)->post('https://sandbox.itunes.apple.com/verifyReceipt', $payload);
            $body = $response->json();
        }

        if ((int) ($body['status'] ?? -1) !== 0) {
            return $this->invalid('Apple receipt verification failed.');
        }

        $latestReceiptInfo = collect($body['latest_receipt_info'] ?? $body['receipt']['in_app'] ?? [])
            ->sortByDesc('purchase_date_ms')
            ->first();

        if (! is_array($latestReceiptInfo)) {
            return $this->invalid('No in-app purchase found in receipt.');
        }

        $transactionId = (string) ($latestReceiptInfo['transaction_id'] ?? '');
        $productId = (string) ($latestReceiptInfo['product_id'] ?? '');

        if ($expectedTransactionId !== null && $expectedTransactionId !== '' && $transactionId !== $expectedTransactionId) {
            return $this->invalid('Transaction ID mismatch.');
        }

        if ($productId !== $expectedAppleProductId) {
            return $this->invalid('Apple product ID mismatch.');
        }

        if (IapTransaction::query()->where('transaction_id', $transactionId)->exists()) {
            return $this->invalid('This App Store transaction has already been used.');
        }

        return [
            'valid' => true,
            'transaction_id' => $transactionId,
            'apple_product_id' => $productId,
            'environment' => $body['environment'] ?? null,
        ];
    }

    private function expectedBundleId(): string
    {
        return (string) (config('app.ios_bundle_id') ?: 'com.buyselles.app');
    }

    private function sharedSecret(): string
    {
        $setting = BusinessSetting::where('type', 'apple_iap_shared_secret')->first();

        return trim((string) ($setting?->value ?? ''));
    }

    /**
     * @return array{valid: bool, transaction_id: string, apple_product_id: string, environment: string|null, message?: string}
     */
    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'transaction_id' => '',
            'apple_product_id' => '',
            'environment' => null,
            'message' => $message,
        ];
    }
}
