<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SupplierOrder;
use App\Models\SupplierProductMapping;
use App\Services\DirectTopUp\DirectTopUpWalletCheckoutService;
use App\Services\Supplier\SupplierManager;
use App\Utils\OrderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugDirectTopUpCommand extends Command
{
    protected $signature = 'topup:debug
                            {order? : Order ID (defaults to latest direct top-up order)}
                            {--retry : Run fulfillment synchronously and print the supplier API response}
                            {--force : With --retry, call the supplier API even if already fulfilled}
                            {--fix-account : Re-encrypt plaintext account IDs on order_details}
                            {--poll : Poll pending supplier orders for this platform order}';

    protected $description = 'Inspect direct top-up order fulfillment and optionally retry the supplier API call';

    public function handle(SupplierManager $supplierManager): int
    {
        $orderId = $this->resolveOrderId();

        if ($orderId === null) {
            $this->error('No direct top-up order found.');

            return self::FAILURE;
        }

        $order = Order::with('orderDetails')->find($orderId);

        if (! $order) {
            $this->error("Order {$orderId} not found.");

            return self::FAILURE;
        }

        $this->info("Direct Top-Up Debug — Order #{$order->id}");
        $this->line(str_repeat('─', 60));
        $this->line("Payment   : {$order->payment_status}");
        $this->line("Status    : {$order->order_status}");
        $this->line("Amount    : {$order->order_amount}");
        $this->line('Created   : '.$order->created_at);
        $this->newLine();

        foreach ($order->orderDetails as $detail) {
            if ($detail->direct_topup_quantity === null) {
                continue;
            }

            $this->section('Order detail #'.$detail->id);

            if ($this->option('fix-account')) {
                $this->fixAccountEncryption($detail);
            }

            $accountId = OrderManager::resolveDirectTopUpAccountId($detail);
            $this->line('Product ID        : '.$detail->product_id);
            $this->line('Credits (top-up qty): '.$detail->direct_topup_quantity);
            $this->line('Account ID          : '.($accountId ?? '(missing)'));
            $this->line('Delivery status     : '.$detail->delivery_status);

            $mapping = SupplierProductMapping::query()
                ->where('product_id', $detail->product_id)
                ->active()
                ->byPriority()
                ->with('supplierApi')
                ->first();

            if ($mapping) {
                $this->newLine();
                $this->line('Supplier mapping');
                $this->line('  Mapping ID         : '.$mapping->id);
                $this->line('  Supplier product   : '.$mapping->supplier_product_id);
                $this->line('  Supplier           : '.($mapping->supplierApi?->name ?? 'n/a')
                    .' ('.($mapping->supplierApi?->driver ?? 'n/a').')');
                $this->line('  supports_direct_top_up: '
                    .($mapping->supplierApi?->supports_direct_top_up ? 'yes' : 'no'));
            } else {
                $this->warn('No active supplier mapping found.');
            }
        }

        $supplierOrders = SupplierOrder::query()
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->get();

        $this->newLine();
        $this->section('Supplier orders ('.$supplierOrders->count().')');

        if ($supplierOrders->isEmpty()) {
            $this->warn('No supplier_orders row — the supplier place-order API was likely never called or the row was not saved.');
        } else {
            foreach ($supplierOrders as $supplierOrder) {
                $this->line("  #{$supplierOrder->id} external={$supplierOrder->supplier_order_id} status={$supplierOrder->status}");
                if ($supplierOrder->failed_reason) {
                    $this->line('    failed_reason: '.str($supplierOrder->failed_reason)->limit(120));
                }
            }
        }

        $logs = DB::table('supplier_api_logs')
            ->where('action', 'place_topup_order')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->filter(function ($log): bool {
                $payload = json_decode($log->request_payload ?? '{}', true);

                return is_array($payload);
            });

        $this->newLine();
        $this->section('Recent place_topup_order API logs');

        if ($logs->isEmpty()) {
            $this->warn('No place_topup_order logs yet.');
        } else {
            foreach ($logs->take(5) as $log) {
                $this->line("Log #{$log->id} — HTTP {$log->http_status_code} — {$log->created_at}");
                $this->line('  Request : '.($log->request_payload ?? ''));
                $this->line('  Response: '.($log->response_payload ?? $log->error_message ?? ''));
                $this->newLine();
            }
        }

        $this->line('Queue driver: '.config('queue.default'));
        $this->line('Tip: Admin → Supplier → API Logs also shows these entries.');
        $this->newLine();

        if ($this->option('poll')) {
            return $this->pollSupplierOrders($supplierManager, $order, $supplierOrders);
        }

        if ($this->option('retry')) {
            if ($order->payment_status !== 'paid') {
                $this->error('Order is not paid — cannot retry fulfillment.');

                return self::FAILURE;
            }

            if (
                ! $this->option('force')
                && DirectTopUpWalletCheckoutService::isDirectTopUpAlreadyFulfilled($order)
            ) {
                $this->warn('This order already has a fulfilled supplier order — skipping API call.');
                $this->comment('Use --force with --retry only if you intentionally want a duplicate supplier purchase.');

                return self::SUCCESS;
            }

            $this->info('Running fulfillment synchronously…');

            try {
                $result = $supplierManager->fulfillDirectTopUpOrder($order->fresh(['orderDetails']));

                $this->newLine();
                $this->line('Fulfillment result:');
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                $latestLog = DB::table('supplier_api_logs')
                    ->where('action', 'place_topup_order')
                    ->orderByDesc('id')
                    ->first();

                if ($latestLog) {
                    $this->newLine();
                    $this->info('Latest API log #'.$latestLog->id);
                    $this->line('Response: '.($latestLog->response_payload ?? $latestLog->error_message ?? 'empty'));
                }

                if (($result['fulfilled'] ?? false) && empty($result['error'])) {
                    OrderDetail::where('order_id', $order->id)
                        ->whereNotNull('direct_topup_quantity')
                        ->update(['delivery_status' => 'delivered']);

                    Order::where('id', $order->id)->update(['order_status' => 'delivered']);

                    $this->info('Marked order as delivered after successful top-up.');
                } elseif (! empty($result['error'])) {
                    $this->error('Fulfillment failed: '.$result['error']);
                }
            } catch (\Throwable $e) {
                $this->error('Fulfillment exception: '.$e->getMessage());

                return self::FAILURE;
            }
        } else {
            $this->comment('Run with --retry to call the supplier API now and print the live response.');
            $this->comment('Run with --poll to refresh status of pending supplier orders.');
            $this->comment('Run with --fix-account first if account ID decryption was broken.');
            $this->comment('Secret Orca direct test: php artisan secretorca:place-order --mapping=28 --account=PLAYER_ID --region=EG --poll');
            $this->comment('Or dispatch the job: php artisan queue:work redis --queue=fulfillment --once');
        }

        return self::SUCCESS;
    }

    private function resolveOrderId(): ?int
    {
        if ($this->argument('order')) {
            return (int) $this->argument('order');
        }

        $latestDetail = OrderDetail::query()
            ->whereNotNull('direct_topup_quantity')
            ->orderByDesc('id')
            ->first();

        return $latestDetail?->order_id;
    }

    private function fixAccountEncryption(OrderDetail $detail): void
    {
        $raw = DB::table('order_details')->where('id', $detail->id)->value('direct_topup_account_id');

        if ($raw === null || $raw === '') {
            $this->warn('No account ID stored on this detail.');

            return;
        }

        try {
            decrypt($raw);
            $this->line('Account ID already encrypted.');

            return;
        } catch (\Throwable) {
            DB::table('order_details')->where('id', $detail->id)->update([
                'direct_topup_account_id' => encrypt($raw),
            ]);
            $this->info('Fixed: re-encrypted plaintext account ID.');
        }
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SupplierOrder>  $supplierOrders
     */
    private function pollSupplierOrders(SupplierManager $supplierManager, Order $order, $supplierOrders): int
    {
        if ($supplierOrders->isEmpty()) {
            $this->warn('No supplier orders to poll.');

            return self::FAILURE;
        }

        foreach ($supplierOrders as $supplierOrder) {
            if (! in_array($supplierOrder->status, ['processing', 'pending', 'failed'], true)) {
                continue;
            }

            $supplier = $supplierOrder->supplierApi;

            if ($supplier === null) {
                continue;
            }

            $this->info('Polling supplier order #'.$supplierOrder->id.' ('.$supplierOrder->supplier_order_id.')…');

            try {
                $result = $supplierManager->driver($supplier)->getOrderStatus($supplierOrder->supplier_order_id);
                $this->line('  status: '.$result->status);

                if ($result->status === 'fulfilled') {
                    $supplierManager->completeDirectTopUpSupplierOrder($supplierOrder, 'fulfilled', $result->rawResponse);
                    $this->info('  Marked fulfilled.');
                } elseif ($result->status === 'failed') {
                    $supplierManager->completeDirectTopUpSupplierOrder($supplierOrder, 'failed', $result->rawResponse);
                    $this->error('  Marked failed.');
                }
            } catch (\Throwable $e) {
                $this->error('  Poll error: '.$e->getMessage());
                $this->comment('  Run: php artisan secretorca:connect --repair-settings');
            }
        }

        return self::SUCCESS;
    }
}
