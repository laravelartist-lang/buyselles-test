<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditSupplierPlaceOrdersCommand extends Command
{
    protected $signature = 'supplier:audit-recent-placements
                            {--days=1 : Number of days to look back}
                            {--fail-on-orphan : Exit with failure if orphan place_order calls exceed customer orders}';

    protected $description = 'Compare supplier place_order API logs against customer/partner orders to detect orphan supplier placements';

    public function handle(): int
    {
        if (! Schema::hasTable('supplier_api_logs')) {
            $this->error('supplier_api_logs table not found.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $placeOrderCounts = DB::table('supplier_api_logs')
            ->selectRaw('supplier_api_id, COUNT(*) as total')
            ->where('action', 'place_order')
            ->where('created_at', '>=', $since)
            ->groupBy('supplier_api_id')
            ->get();

        $customerOrderCount = Order::query()
            ->where('created_at', '>=', $since)
            ->whereNotIn('payment_method', ['partner_wallet'])
            ->count();

        $partnerOrderCount = Order::query()
            ->where('created_at', '>=', $since)
            ->where('payment_method', 'partner_wallet')
            ->count();

        $totalPlaceOrders = (int) $placeOrderCounts->sum('total');
        $totalCustomerOrders = $customerOrderCount + $partnerOrderCount;

        $adminTestCount = DB::table('supplier_api_logs')
            ->where('action', 'place_order')
            ->where('created_at', '>=', $since)
            ->where(function ($query): void {
                $query->where('request_payload', 'like', '%admin-test%')
                    ->orWhere('request_payload', 'like', '%admin_test%');
            })
            ->count();

        $this->info("Supplier placement audit (last {$days} day(s), since {$since->toDateTimeString()})");
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Storefront + wallet orders', $customerOrderCount],
                ['Partner API orders', $partnerOrderCount],
                ['Total customer orders', $totalCustomerOrders],
                ['Supplier API place_order calls', $totalPlaceOrders],
                ['Admin test place_order calls', $adminTestCount],
            ]
        );

        if ($placeOrderCounts->isNotEmpty()) {
            $this->newLine();
            $this->info('place_order by supplier_api_id:');
            $this->table(
                ['supplier_api_id', 'place_order count'],
                $placeOrderCounts->map(fn ($row) => [(string) $row->supplier_api_id, (string) $row->total])->all()
            );
        }

        $orphanEstimate = max(0, $totalPlaceOrders - $totalCustomerOrders - $adminTestCount);

        if ($orphanEstimate > 0) {
            $this->warn("Possible orphan supplier placements: ~{$orphanEstimate} (place_order minus customer orders minus admin tests).");
            $this->warn('If this is high, investigate duplicate fulfillment dispatches or manual supplier API tests.');
        } else {
            $this->info('No orphan supplier placement pattern detected for this window.');
        }

        if ($this->option('fail-on-orphan') && $orphanEstimate > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
