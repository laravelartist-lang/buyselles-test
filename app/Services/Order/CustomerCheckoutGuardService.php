<?php

namespace App\Services\Order;

use App\Models\Cart;
use App\Models\CustomerCheckoutIdempotency;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CustomerCheckoutGuardService
{
    private const REPLAY_WINDOW_MINUTES = 15;

    /**
     * @param  callable(): array{http_status: int, payload: array<string, mixed>}  $processor
     * @return array{http_status: int, payload: array<string, mixed>, idempotent_replay?: bool, checkout_in_progress?: bool}
     */
    public function executeWalletCheckout(
        Request $request,
        Collection $carts,
        callable $processor,
    ): array {
        $customerScope = $this->resolveCustomerScope($request);
        $cartFingerprint = $this->buildCartFingerprint($carts);
        $idempotencyKey = $this->resolveIdempotencyKey($request);

        $lock = Cache::lock('customer_checkout:'.$customerScope, 30);

        try {
            return $lock->block(10, function () use ($customerScope, $cartFingerprint, $idempotencyKey, $processor, $request): array {
                if ($idempotencyKey !== null) {
                    $existing = CustomerCheckoutIdempotency::query()
                        ->where('customer_scope', $customerScope)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing !== null) {
                        return $this->replayStoredCheckout($existing);
                    }
                }

                $recent = CustomerCheckoutIdempotency::query()
                    ->where('customer_scope', $customerScope)
                    ->where('checkout_method', 'wallet')
                    ->where('cart_fingerprint', $cartFingerprint)
                    ->where('created_at', '>=', now()->subMinutes(self::REPLAY_WINDOW_MINUTES))
                    ->whereIn('status', [
                        CustomerCheckoutIdempotency::STATUS_COMPLETED,
                        CustomerCheckoutIdempotency::STATUS_FAILED,
                    ])
                    ->latest('id')
                    ->first();

                if ($recent !== null) {
                    return $this->replayStoredCheckout($recent);
                }

                $processing = CustomerCheckoutIdempotency::query()
                    ->where('customer_scope', $customerScope)
                    ->where('checkout_method', 'wallet')
                    ->where('cart_fingerprint', $cartFingerprint)
                    ->where('status', CustomerCheckoutIdempotency::STATUS_PROCESSING)
                    ->where('created_at', '>=', now()->subMinutes(2))
                    ->exists();

                if ($processing) {
                    return [
                        'http_status' => 409,
                        'payload' => [
                            'message' => translate('checkout_already_in_progress'),
                        ],
                        'checkout_in_progress' => true,
                    ];
                }

                $record = CustomerCheckoutIdempotency::query()->create([
                    'customer_scope' => $customerScope,
                    'checkout_method' => 'wallet',
                    'idempotency_key' => $idempotencyKey,
                    'cart_fingerprint' => $cartFingerprint,
                    'status' => CustomerCheckoutIdempotency::STATUS_PROCESSING,
                ]);

                try {
                    $result = $processor();
                    $httpStatus = (int) ($result['http_status'] ?? 200);
                    $payload = $result['payload'] ?? [];
                    $isSuccess = $httpStatus >= 200 && $httpStatus < 300;

                    $record->update([
                        'status' => $isSuccess
                            ? CustomerCheckoutIdempotency::STATUS_COMPLETED
                            : CustomerCheckoutIdempotency::STATUS_FAILED,
                        'order_ids' => $payload['order_ids'] ?? null,
                        'response_payload' => $payload,
                        'http_status' => $httpStatus,
                    ]);

                    if ($isSuccess) {
                        $this->forgetWebCheckoutIdempotencyKey($request);
                    }

                    return [
                        'http_status' => $httpStatus,
                        'payload' => $payload,
                    ];
                } catch (\Throwable $throwable) {
                    $record->update([
                        'status' => CustomerCheckoutIdempotency::STATUS_FAILED,
                        'response_payload' => [
                            'message' => translate('Something_went_wrong'),
                        ],
                        'http_status' => 500,
                    ]);

                    throw $throwable;
                }
            });
        } catch (LockTimeoutException) {
            return [
                'http_status' => 409,
                'payload' => [
                    'message' => translate('checkout_already_in_progress'),
                ],
                'checkout_in_progress' => true,
            ];
        }
    }

    public function resolveIdempotencyKey(Request $request): ?string
    {
        $key = trim((string) ($request->header('X-Idempotency-Key') ?? $request->input('idempotency_key') ?? ''));

        if ($key !== '') {
            return Str::limit($key, 128, '');
        }

        $sessionKey = session('customer_wallet_checkout_idempotency_key');

        return is_string($sessionKey) && $sessionKey !== '' ? Str::limit($sessionKey, 128, '') : null;
    }

    public function ensureWebWalletCheckoutIdempotencyKey(Request $request): string
    {
        $existing = $this->resolveIdempotencyKey($request);

        if ($existing !== null) {
            return $existing;
        }

        $key = (string) Str::uuid();
        session(['customer_wallet_checkout_idempotency_key' => $key]);

        return $key;
    }

    /**
     * @param  Collection<int, Cart>  $carts
     */
    public function buildCartFingerprint(Collection $carts): string
    {
        $parts = $carts
            ->sortBy('id')
            ->map(static fn (Cart $cart): string => implode(':', [
                (string) $cart->id,
                (string) $cart->product_id,
                (string) $cart->quantity,
                (string) $cart->cart_group_id,
                (string) $cart->direct_topup_quantity,
                (string) $cart->direct_topup_account_id,
                (string) $cart->variant,
            ]))
            ->values()
            ->all();

        return hash('sha256', implode('|', $parts));
    }

    public function resolveCustomerScope(Request $request): string
    {
        $user = $request->user();

        if ($user !== null) {
            return 'user:'.$user->id;
        }

        if (auth('customer')->check()) {
            return 'user:'.auth('customer')->id();
        }

        $guestId = $request->input('guest_id') ?? session('guest_id');

        return 'guest:'.($guestId ?: '0');
    }

    /**
     * @return array{http_status: int, payload: array<string, mixed>, idempotent_replay: true}
     */
    private function replayStoredCheckout(CustomerCheckoutIdempotency $existing): array
    {
        return [
            'http_status' => (int) ($existing->http_status ?? 200),
            'payload' => array_merge($existing->response_payload ?? [], [
                'idempotent_replay' => true,
                'order_ids' => $existing->order_ids ?? ($existing->response_payload['order_ids'] ?? []),
            ]),
            'idempotent_replay' => true,
        ];
    }

    private function forgetWebCheckoutIdempotencyKey(Request $request): void
    {
        if ($request->has('idempotency_key') || session()->has('customer_wallet_checkout_idempotency_key')) {
            session()->forget('customer_wallet_checkout_idempotency_key');
        }
    }
}
