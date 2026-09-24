<?php

namespace App\Console\Commands;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardFreshStart extends Command
{
    protected $signature = 'dashboard:fresh';

    protected $description = 'Truncate all transactional/order data for a fresh dashboard without losing products or users';

    public function handle(): void
    {
        $this->newLine();
        $this->line(str_repeat('=', 70));
        $this->line('  <fg=bright-yellow>⚠ DASHBOARD FRESH START</>');
        $this->line(str_repeat('=', 70));
        $this->newLine();

        $this->line('  This command will permanently delete the following data:');
        $this->newLine();
        $this->line('  <fg=red>❌ All Orders</> (pending, confirmed, delivered, canceled — everything)');
        $this->line('  <fg=red>❌ All Order Transactions & Earnings</>');
        $this->line('  <fg=red>❌ All Digital Product Codes</> (including the ones you uploaded)');
        $this->line('  <fg=red>❌ All Wallet Balances</> (admin, vendors, customers, transfers)');
        $this->line('  <fg=red>❌ All Chats & Support Tickets</>');
        $this->line('  <fg=red>❌ All Reviews & Wishlists</>');
        $this->line('  <fg=red>❌ All Notifications</>');
        $this->line('  <fg=red>❌ All Carts, Disputes, Refunds, Restock Requests</>');
        $this->newLine();
        $this->line('  <fg=green>✓ Products</> — will NOT be deleted');
        $this->line('  <fg=green>✓ Users / Sellers / Customers</> — will NOT be deleted');
        $this->line('  <fg=green>✓ Categories, Brands, Banners</> — will NOT be deleted');
        $this->line('  <fg=green>✓ All System Settings & Configurations</> — will NOT be deleted');
        $this->newLine();
        $this->line('  <fg=bright-yellow>After running: Admin wallets and vendor wallets will be reset to zero.</>');
        $this->line('  <fg=bright-yellow>Product stock counters will be reset to 0 (codes deleted).</>');
        $this->line('  <fg=bright-yellow>Both Admin and Vendor dashboards will show zero stats.</>');
        $this->newLine();
        $this->line(str_repeat('=', 70));
        $this->newLine();

        if (! $this->confirm('Are you sure you want to proceed?')) {
            $this->info('Aborted.');

            return;
        }

        Schema::disableForeignKeyConstraints();

        $tables = [
            // Disputes / Escrow
            'dispute_evidence',
            'dispute_messages',
            'dispute_status_logs',
            'disputes',
            'escrows',

            // Refunds
            'refund_statuses',
            'refund_transactions',
            'refund_requests',

            // Order sub-tables
            'order_details_rewards',
            'order_delivery_verifications',
            'order_status_histories',
            'order_expected_delivery_histories',
            'order_edit_histories',
            'order_taxes',
            'offline_payments',

            // Order transactions
            'order_transactions',
            'transactions',
            'payment_requests',

            // Core orders
            'order_details',
            'orders',

            // Supplier / Partner
            'supplier_orders',
            'supplier_api_logs',
            'partner_api_logs',
            'partner_order_idempotency',

            // Digital product codes (these are tied to orders, so they must be wiped)
            'digital_product_codes',
            'digital_product_otp_verifications',

            // Carts
            'cart_shippings',
            'carts',

            // Wallets
            'wallet_transfers',
            'admin_wallet_histories',
            'admin_wallets',
            'seller_wallet_histories',
            'seller_wallets',
            'customer_wallet_histories',
            'customer_wallets',
            'wallet_transactions',
            'loyalty_point_transactions',
            'deliveryman_wallets',
            'delivery_man_transactions',
            'withdraw_requests',

            // Support / Chat
            'support_ticket_convs',
            'support_tickets',
            'chattings',

            // Notifications
            'notification_seens',
            'notifications',
            'deliveryman_notifications',

            // Reviews / Social
            'review_replies',
            'reviews',
            'wishlists',
            'shop_followers',

            // Restock
            'restock_product_customers',
            'restock_products',

            // Referral
            'referral_customers',

            // Other operational
            'contacts',
            'guest_users',
            'delivery_histories',
            'shipping_addresses',
            'billing_addresses',
            'city_requests',
            'area_requests',
            'recent_searches',
            'attachments',
            'paytabs_invoices',
        ];

        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $this->warn("  Skipping missing table: {$table}");
                $bar->advance();

                continue;
            }

            DB::table($table)->truncate();
            $bar->advance();
        }

        Schema::enableForeignKeyConstraints();
        $bar->finish();

        $this->newLine(2);

        // Reset denormalized wallet columns on users
        if (DB::getSchemaBuilder()->hasColumn('users', 'wallet_balance')) {
            $userReset = ['wallet_balance' => 0];
            if (DB::getSchemaBuilder()->hasColumn('users', 'loyalty_point')) {
                $userReset['loyalty_point'] = 0;
            }
            DB::table('users')->update($userReset);
            $this->info('✓ User wallet balances and loyalty points reset to zero.');
        }

        if (DB::getSchemaBuilder()->hasTable('reseller_api_keys')
            && DB::getSchemaBuilder()->hasColumn('reseller_api_keys', 'wallet_balance')) {
            DB::table('reseller_api_keys')->update(['wallet_balance' => 0]);
            $this->info('✓ Reseller API key wallet balances reset to zero.');
        }

        // Re-create admin wallet row
        DB::table('admin_wallets')->insert([
            'admin_id' => 1,
            'inhouse_earning' => 0,
            'commission_earned' => 0,
            'delivery_charge_earned' => 0,
            'pending_amount' => 0,
            'total_tax_collected' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info('✓ Admin wallet re-created with zero balance.');

        // Re-create seller wallet rows for all existing sellers
        $sellers = Seller::query()->get(['id']);
        $walletData = $sellers->map(fn ($s) => [
            'seller_id' => $s->id,
            'total_earning' => 0,
            'pending_balance' => 0,
            'withdrawn' => 0,
            'pending_withdraw' => 0,
            'commission_given' => 0,
            'delivery_charge_earned' => 0,
            'collected_cash' => 0,
            'total_tax_collected' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (! empty($walletData)) {
            DB::table('seller_wallets')->insert($walletData);
            $this->info('✓ '.count($walletData).' seller wallet(s) re-created.');
        }

        // Re-create customer wallet rows for all existing users
        $customerWalletData = User::query()->pluck('id')->map(fn ($userId) => [
            'customer_id' => $userId,
            'balance' => 0,
            'royality_points' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (! empty($customerWalletData)) {
            DB::table('customer_wallets')->insert($customerWalletData);
            $this->info('✓ '.count($customerWalletData).' customer wallet(s) re-created.');
        }

        // Reset product current_stock to 0 for all products (since codes are wiped)
        DB::table('products')->update(['current_stock' => 0]);
        $this->info('✓ Product stock counters reset to 0.');

        $this->newLine();
        $this->info('Dashboard is now fresh!');
    }
}
