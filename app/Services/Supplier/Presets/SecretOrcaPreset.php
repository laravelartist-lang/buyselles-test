<?php

namespace App\Services\Supplier\Presets;

/**
 * Shared Secret Orca connector settings for CLI and admin UI.
 */
class SecretOrcaPreset
{
    public const SUPPLIER_NAME = 'SecretOrca';

    public const BASE_URL = 'https://api.secretorca.com';

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return [
            'api_key_header' => 'X-API-Key',

            'auth_test_endpoint' => '/api/v1/external/catalog/products/',
            'health_endpoint' => '/api/v1/external/catalog/products/',

            'pagination_page_param' => 'page',
            'pagination_per_page_param' => 'page_size',
            'pagination_per_page_default' => '100',
            'pagination_page_base' => '1',
            'pagination_data_path' => 'results',
            'pagination_total_path' => 'count',

            'products_endpoint' => '/api/v1/external/catalog/products/',
            'products_response_path' => 'results',
            'product_id_field' => 'id',
            'product_name_field' => 'name',
            'product_price_field' => 'default_rate',
            'product_category_field' => 'bot_name',
            'product_region_field' => 'code',
            'product_stock_default' => '999999',

            'topup_order_endpoint' => '/api/v1/external/orders/create/',
            'topup_product_id_field' => 'product_id',
            'topup_quantity_field' => 'quantity',
            'topup_account_field' => 'target_account',
            'topup_region_field' => 'region',
            'topup_idempotency_key_field' => 'idempotency_key',
            'topup_client_order_id_field' => 'client_order_id',
            'topup_quantity_as_string' => '1',
            'topup_order_id_response_path' => 'order_number',
            'topup_status_response_path' => 'status',
            'topup_success_status_values' => ['completed'],
            'topup_pending_status_values' => ['pending', 'queued', 'processing'],
            'order_status_endpoint' => '/api/v1/external/orders/{order_id}/',
            'order_status_response_path' => 'status',

            'webhook_signature_header' => 'X-Webhook-Signature',
            'webhook_signature_prefix' => 'sha256=',
            'webhook_hash_algo' => 'sha256',
            'webhook_type_path' => 'event_type',
            'webhook_order_id_path' => 'data.order_number',
            'webhook_status_path' => 'data.status',
            'webhook_event_fulfilled_types' => json_encode(['order.completed']),
            'webhook_event_failed_types' => json_encode(['order.failed', 'order.cancelled', 'order.refunded']),

            'status_map' => json_encode([
                'pending' => 'processing',
                'queued' => 'processing',
                'processing' => 'processing',
                'completed' => 'fulfilled',
                'failed' => 'failed',
                'cancelled' => 'failed',
                'refunded' => 'failed',
            ]),

            'source_currency' => 'USD',
            'price_decimal_places' => '10',
            'http_verify_ssl' => env('SECRETORCA_HTTP_VERIFY_SSL', app()->environment('local') ? '0' : '1'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminConnectorPreset(): array
    {
        return [
            'label' => 'Secret Orca',
            'driver' => 'generic_rest',
            'name' => self::SUPPLIER_NAME,
            'base_url' => self::BASE_URL,
            'auth_type' => 'api_key',
            'auth_types' => ['api_key'],
            'supports_direct_top_up' => true,
            'rate_limit_per_minute' => 120,
            'is_sandbox' => true,
            'settings' => self::settings(),
            'credential_fields_by_auth' => [
                'api_key' => ['api_key'],
            ],
        ];
    }
}
