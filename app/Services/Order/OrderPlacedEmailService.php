<?php

namespace App\Services\Order;

use App\Events\OrderPlacedEvent;
use App\Models\Order;
use App\Utils\OrderManager;
use Illuminate\Support\Facades\Schema;

class OrderPlacedEmailService
{
    public const CUSTOMER_TEMPLATE = 'order-place';

    public function hasBeenSent(int $orderId): bool
    {
        if (! $this->columnExists()) {
            return false;
        }

        return Order::query()
            ->whereKey($orderId)
            ->whereNotNull('order_placed_email_sent_at')
            ->exists();
    }

    public function reserveSend(int $orderId): bool
    {
        if (! $this->columnExists()) {
            return true;
        }

        return Order::query()
            ->whereKey($orderId)
            ->whereNull('order_placed_email_sent_at')
            ->update(['order_placed_email_sent_at' => now()]) === 1;
    }

    public function shouldSkipCustomerOrderPlaceMail(array $data): bool
    {
        if (($data['templateName'] ?? '') !== self::CUSTOMER_TEMPLATE) {
            return false;
        }

        $orderId = (int) ($data['orderId'] ?? 0);
        if ($orderId <= 0) {
            return false;
        }

        if ($this->hasBeenSent($orderId)) {
            return true;
        }

        $order = Order::query()->find($orderId);
        if ($order === null) {
            return true;
        }

        return in_array($order->order_status, ['failed', 'canceled', 'returned'], true);
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $mailEvents
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function filterMailEvents(array $mailEvents): array
    {
        return array_values(array_map(function (array $group): array {
            return array_values(array_filter($group, function (array $event): bool {
                return ! $this->shouldSkipCustomerOrderPlaceMail($event['data'] ?? []);
            }));
        }, $mailEvents));
    }

    public function sendCustomerOrderPlacedEmailIfEligible(int $orderId): bool
    {
        if ($this->hasBeenSent($orderId)) {
            return false;
        }

        $order = Order::query()
            ->with(['customer', 'seller.shop', 'details'])
            ->find($orderId);

        if ($order === null || in_array($order->order_status, ['failed', 'canceled', 'returned'], true)) {
            return false;
        }

        if ($order->order_status !== 'delivered') {
            return false;
        }

        $customer = $order->customer ?? 'offline';
        $mailEvents = OrderManager::getGenerateOrderMailInfo(
            vendorType: $order->seller_is ?? 'admin',
            vendorId: (int) ($order->seller_id ?? 0),
            vendorWiseCart: [
                'shipping_address_id' => $order->shipping_address,
                'billing_address_id' => $order->billing_address,
            ],
            order: $order,
            customer: $customer,
        );

        foreach ($mailEvents as $event) {
            if (($event['data']['templateName'] ?? '') !== self::CUSTOMER_TEMPLATE) {
                continue;
            }

            event(new OrderPlacedEvent(email: $event['email'], data: $event['data']));

            return true;
        }

        return false;
    }

    private function columnExists(): bool
    {
        return Schema::hasColumn('orders', 'order_placed_email_sent_at');
    }
}
