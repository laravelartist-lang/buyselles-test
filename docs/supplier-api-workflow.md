# Supplier API — Complete Workflow Guide

This document explains the full lifecycle of a supplier product: from fetching their catalog into your admin panel, to mapping it to your store product, to automatic fulfillment when a customer places an order.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Stage 1 — Fetching the Supplier Catalog](#2-stage-1--fetching-the-supplier-catalog)
3. [Stage 2 — Mapping a Supplier Product to Your Store](#3-stage-2--mapping-a-supplier-product-to-your-store)
4. [Stage 3 — Customer Places an Order](#4-stage-3--customer-places-an-order)
5. [Stage 4 — Fetching Codes from Supplier](#5-stage-4--fetching-codes-from-supplier)
6. [Stage 5 — Webhook Delivery (Async Suppliers)](#6-stage-5--webhook-delivery-async-suppliers)
7. [Stage 6 — Code Delivered to Customer](#7-stage-6--code-delivered-to-customer)
8. [Stage 7 — Price sync (daily + manual)](#8-stage-7--price-sync-daily--manual)
9. [Price Calculation Logic](#9-price-calculation-logic)
10. [Supplier Fallback Chain](#10-supplier-fallback-chain)
11. [Key Database Tables](#11-key-database-tables)

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     SUPPLIER API SYSTEM                         │
├───────────────────────────┬─────────────────────────────────────┤
│   ADMIN PANEL             │   BACKGROUND JOBS (Queue)           │
│                           │                                     │
│  Browse Catalog ──────────┼──► SyncSupplierCatalogJob           │
│  Add Mapping              │       └─► BambooDriver::fetchProducts│
│  Sync Prices Button ──────┼──► SyncSupplierMappingPricesJob (manual) │
│                           │       └─► syncStock() per mapping   │
├───────────────────────────┴─────────────────────────────────────┤
│                 ORDER FULFILLMENT PIPELINE                      │
│                                                                 │
│  Customer pays → OrderObserver → SupplierCodeFetchJob           │
│                                     └─► SupplierManager::fulfillOrder()
│                                           └─► BambooDriver::placeOrder()
│                                                 └─► Codes delivered
│                                                       (sync or webhook)
├─────────────────────────────────────────────────────────────────┤
│  Bamboo Webhook → SupplierWebhookController                     │
│                     └─► processReceivedCodes()                  │
│                           └─► assignAndNotify() → Email to customer
└─────────────────────────────────────────────────────────────────┘
```

**Key components:**

| Component | File | Role |
|-----------|------|------|
| `SupplierManager` | `app/Services/Supplier/SupplierManager.php` | Central orchestrator for all supplier operations |
| `BambooDriver` | `app/Services/Supplier/Drivers/BambooDriver.php` | Bamboo-specific API implementation |
| `SyncSupplierCatalogJob` | `app/Jobs/SyncSupplierCatalogJob.php` | Background catalog fetch with progress tracking |
| `SupplierCodeFetchJob` | `app/Jobs/SupplierCodeFetchJob.php` | On-demand code fetch when a customer order needs codes |
| `SyncSupplierMappingPricesJob` | `app/Jobs/SyncSupplierMappingPricesJob.php` | Daily + manual price sync via `fetch_stock` (no purchases) |
| `DigitalProductCodeService` | `app/Services/DigitalProductCodeService.php` | Manages the code pool, assigns codes to orders |

---

## 2. Stage 1 — Fetching the Supplier Catalog

Before you can map anything, you need to see the supplier's product list. This is done via a background job to avoid timeouts (Bamboo has ~9,358 products).

### How to trigger it (Admin Panel)

Go to **Admin → Supplier API → Product Mapping → Add Mapping -> Select Supplier ** and click **"Browse Catalog"**.

### What happens behind the scenes

```
Admin clicks "Browse Catalog"
        │
        ▼
POST /admin/supplier/{id}/catalog/sync
SupplierController::dispatchCatalogSync()
        │
        ├─► Sets cache key "supplier_catalog_sync_status_{id}" = { state: 'running', progress: 0 }
        │         (prevents duplicate dispatches — 20-minute staleness guard)
        │
        └─► Dispatches SyncSupplierCatalogJob to queue
                │
                ▼
        BambooDriver::fetchProducts(['fetch_all' => true, 'on_page' => callback])
                │
                ├─► GET /api/integration/v2.0/catalog?pageIndex=0&pageSize=100
                ├─► GET /api/integration/v2.0/catalog?pageIndex=1&pageSize=100
                ├─► ... (27 pages, 100 brands per page)
                │
                │   After each page → updates progress cache:
                │   { state: 'running', progress: 37, message: 'Page 10/27' }
                │
                └─► Flattens brand→product structure into array of DTOs:
                    { id, name, price, currency, stock, region, image }
                │
                ▼
        Cache::put("supplier_catalog_{id}", $products, TTL: 6 hours)
        Cache::put("supplier_catalog_sync_status_{id}", { state: 'done', total_products: 9358 })
```

### Polling mechanism (JS in admin panel)

The browser polls `GET /admin/supplier/{id}/catalog/status` every 2 seconds and shows a progress bar. When state becomes `done`, it calls `GET /admin/supplier/{id}/catalog/browse` to load the cached catalog.

> **Note:** Closing the modal does NOT stop the sync. The job runs server-side. Re-opening the modal resumes polling.

---

## 3. Stage 2 — Mapping a Supplier Product to Your Store

A **mapping** connects one of your store's digital products to a supplier SKU. This tells the system: *"When product X is out of local stock, fetch it from supplier Y using their product ID Z."*

### Steps in the Admin Panel

1. Go to **Admin → Suppliers → Mappings → Add Mapping**
2. Select your **local product** (e.g., "Steam $20 Gift Card")
3. Click **"Browse Catalog"** — the cached catalog loads in a modal
4. Find the matching product and click **"Select"** — fills the `supplier_product_id` field
5. Set **markup** (e.g., 10% percent or $2.00 flat)
6. Save

> **Note:** Automatic pre-stocking (`auto_restock`) was **removed on 2026-07-13**. The scheduled stock sync job only updates remote prices and availability — it does **not** place supplier orders. Codes are fetched from suppliers only when a **customer or partner order** needs fulfillment.

### What gets saved to `supplier_product_mappings`

| Field | Example | Meaning |
|-------|---------|---------|
| `product_id` | `27` | Your local product ID |
| `supplier_api_id` | `1` | Which supplier API to use |
| `supplier_product_id` | `"4521"` | The supplier's SKU/product ID |
| `cost_price` | `13.01` | What you pay the supplier per unit |
| `markup_type` | `percent` | `percent` or `flat` |
| `markup_value` | `10.00` | 10% markup → sell price = 13.01 × 1.10 = **14.31** |
| `priority` | `0` | Lower = tried first when multiple suppliers |

---

## 4. Stage 3 — Customer Places an Order

When a customer pays, the system first tries to assign codes from the **local pool**. If there aren't enough, it falls back to the supplier.

### Full trigger chain

```
Customer completes payment
        │
        ▼
Order::payment_status changes to 'paid'
        │
        ▼
OrderObserver::updated() fires
        │
        ├─► Step 1: Try local pool
        │   DigitalProductCodeService::assignAndNotify($order)
        │       Queries DigitalProductCode where:
        │         product_id = X, status = 'available', source = 'manual' or 'supplier_api'
        │
        └─► Step 2: Check for shortfall
            dispatchSupplierFallbackIfNeeded($order)
                │
                ├─► For each order detail:
                │     assigned = codes already linked to this detail (status = 'sold')
                │     needed   = detail.qty - assigned
                │
                │     If needed > 0 AND product has an active mapping:
                │         needsSupplierFetch = true
                │
                └─► If needsSupplierFetch:
                        SupplierCodeFetchJob::dispatch($order->id)
```

> **Important:** The supplier is NEVER called synchronously in a web request. It always goes through the queue.

---

## 5. Stage 4 — Fetching Codes from Supplier

`SupplierCodeFetchJob` runs in the background with **3 retries** and exponential backoff (30s → 60s → 120s).

### Job flow

```
SupplierCodeFetchJob::handle()
        │
        ▼
SupplierManager::fulfillOrder($order)
        │
        ├─► For each order detail needing codes:
        │       productId = detail.product_id
        │       needed    = qty - already_assigned
        │
        │       └─► fetchAndStockCodes($product, $needed)
        │               │
        │               ├─► Get all active mappings sorted by priority (ASC)
        │               │
        │               └─► For each mapping (fallback chain):
        │                       ├─► Check supplier is_active, health_status != 'down'
        │                       ├─► Check rate limit (Redis)
        │                       ├─► placeSupplierOrder($supplier, $mapping, $qty)
        │                       │       │
        │                       │       ├─► BambooDriver::placeOrder(sku, qty, price)
        │                       │       │       POST /api/integration/v1.0/orders/checkout
        │                       │       │       Returns supplierOrderId (Bamboo is ASYNC)
        │                       │       │
        │                       │       ├─► Creates SupplierOrder record in DB
        │                       │       │
        │                       │       └─► If codes returned synchronously:
        │                       │               processReceivedCodes()
        │                       │               (see Stage 5 for async path)
        │                       │
        │                       └─► If codes inserted > 0: STOP, return success
        │                           If codes inserted = 0: continue to next supplier
        │
        └─► If any codes obtained:
                $codeService->assignAndNotify($order)
                    ├─► Assigns codes to order detail (status → 'sold')
                    └─► Sends email to customer with the codes
```

### Bamboo is Async

Bamboo's API (`v1.0/orders/checkout`) returns an order ID but **not the actual codes**. The codes arrive later via webhook (see Stage 5).

---

## 6. Stage 5 — Webhook Delivery (Async Suppliers)

For async suppliers like Bamboo, codes are pushed back to your server when ready.

### Webhook endpoint

```
POST /api/supplier/webhook/{supplierId}
     ↓ (no CSRF, no auth middleware)
SupplierWebhookController::handle()
        │
        ▼
SupplierManager::handleWebhook($supplier, $request)
        │
        ├─► BambooDriver::parseWebhook($request)
        │       ├─► Verify secretKey (from payload or X-Secret-Key header)
        │       │     hash_equals($expectedSecret, $receivedSecret)
        │       │     → If mismatch: return WebhookResult(verified: false) → REJECTED
        │       │
        │       ├─► Extract codes from Response array
        │       └─► Map status to type:
        │               'completed'/'success' → 'order_fulfilled'
        │               'failed'/'error'      → 'order_failed'
        │
        ├─► Find SupplierOrder by supplier_order_id
        │
        ├─► processReceivedCodes($supplierOrder, $mapping, $codes)
        │       ├─► Encrypt codes with AES-256-CBC
        │       ├─► bulkAddToPool() — adds to DigitalProductCode table (source: 'supplier_api')
        │       ├─► Update supplier_order.status → 'fulfilled'
        │       └─► applyApiPriceIfManualDepleted() — update product unit_price if needed
        │
        └─► If supplier order is tied to a customer order:
                $codeService->assignAndNotify($order)
                    → Assign codes to customer and send email
```

---

## 7. Stage 6 — Code Delivered to Customer

`assignAndNotify()` handles the final assignment of codes to the customer order.

```
assignAndNotify($order)
        │
        ├─► For each order detail:
        │       Pull codes from DigitalProductCode pool:
        │           WHERE product_id = X
        │             AND status = 'available'
        │             AND is_active = true
        │             AND (expiry_date IS NULL OR expiry_date >= today)
        │       Update codes: status → 'sold', order_detail_id = detail.id
        │
        ├─► Send email to customer with all assigned codes
        │
        └─► Update order status if all items fulfilled
```

---

## 8. Stage 7 — Price sync (daily + manual)

`SyncSupplierMappingPricesJob` runs **daily at 01:00** and can be triggered manually from **Admin → Supplier Mappings → Sync Prices**. It calls `SupplierManager::syncStock()` per mapping (API read only).

```
Schedule (daily) or admin Sync Prices → SyncSupplierMappingPricesJob
        │
        ▼
For each active SupplierProductMapping:
        │
        ├─► Check supplier is_active, health not 'down'
        ├─► Check rate limit
        │
        ├─► BambooDriver::fetchStock($supplier_product_id)
        │       GET /api/integration/v2.0/catalog/{productId}
        │       Returns: { available: 120, price: 13.50, currency: 'USD' }
        │
        ├─► If price changed (stockResult.price != mapping.cost_price):
        │       Update mapping.cost_price, mapping.cost_currency
        │
        ├─► Always: applyApiPriceIfManualDepleted($productId)
        │       If local manual stock is 0:
        │           product.unit_price = mapping.calculateSellPrice()
        │           product.purchase_price = mapping.cost_price
        │
        ├─► Update mapping.last_synced_at = now()
        │
        └─► Does NOT place supplier orders (auto_restock removed 2026-07-13).
            Supplier `place_order` calls happen only via customer/partner fulfillment jobs.
            Run `php artisan supplier:audit-recent-placements` to compare API logs vs orders.
```

You can also trigger this manually from the admin panel via the **"Sync Prices"** button on the Mappings list page.

---

## 9. Price Calculation Logic

The product's selling price is determined by the mapping's markup settings.

```
cost_price  = what you pay the supplier (e.g., 13.01 USD)
markup_type = 'percent' or 'flat'
markup_value = the markup amount (e.g., 10 for 10%)

If markup_type = 'percent':
    sell_price = cost_price × (1 + markup_value / 100)
    Example:   = 13.01 × 1.10 = 14.31

If markup_type = 'flat':
    sell_price = cost_price + markup_value
    Example:   = 13.01 + 2.00 = 15.01
```

**When does the product price update?**

The product's `unit_price` field is updated to `sell_price` automatically when:

1. Manual stock in your local pool runs out (checked in `applyApiPriceIfManualDepleted`)
2. New codes arrive from the supplier via webhook or customer-order fulfillment
3. The periodic 15-minute sync job runs and detects depleted manual stock
4. You click "Sync Prices" in the admin panel

As long as you still have manually uploaded codes, the original price is preserved.

---

## 10. Supplier Fallback Chain

You can map **multiple suppliers** to a single product. The system tries them in `priority` order (lowest number first).

```
Product 27 — "Steam $20 Gift Card"
├── Mapping priority=0 → Supplier: Bamboo
├── Mapping priority=1 → Supplier: AnotherVendor
└── Mapping priority=2 → Supplier: BackupVendor

When order is placed and local stock is empty:
    Try Bamboo first
        └─► Success (codes returned) → STOP
    If Bamboo is down or rate-limited:
        Try AnotherVendor
            └─► Success → STOP
    If AnotherVendor fails:
        Try BackupVendor
```

A supplier is skipped if:
- `is_active = false` on the supplier record
- `health_status = 'down'`
- Rate limit exceeded for this API

---

## 11. Key Database Tables

### `supplier_apis`
The registered supplier API accounts.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | PK |
| `name` | string | Display name (e.g., "Bamboo") |
| `base_url` | string | API base URL |
| `api_key` | encrypted | Stored encrypted, never plain text |
| `api_secret` | encrypted | Stored encrypted |
| `driver` | string | PHP driver class: `bamboo`, `generic` |
| `rate_limit_per_minute` | int | Max API calls per minute |
| `health_status` | string | `up` or `down` |
| `is_active` | bool | Enable/disable the supplier |
| `last_sync_at` | timestamp | Last successful sync |

### `supplier_product_mappings`
Links your products to supplier SKUs.

| Column | Type | Description |
|--------|------|-------------|
| `product_id` | bigint FK | Your local product |
| `supplier_api_id` | bigint FK | Which supplier |
| `supplier_product_id` | string | Supplier's product SKU/ID |
| `cost_price` | decimal | Cost per unit (kept up to date by sync) |
| `markup_type` | enum | `percent` or `flat` |
| `markup_value` | decimal | Markup amount |
| `priority` | int | Fallback order (lower = higher priority) |
| `is_active` | bool | Enable/disable this mapping |
| `last_synced_at` | timestamp | When this mapping was last synced |

### `supplier_orders`
Records every API call made to a supplier to purchase codes.

| Column | Type | Description |
|--------|------|-------------|
| `supplier_api_id` | bigint FK | Which supplier was called |
| `supplier_product_mapping_id` | bigint FK | Which mapping triggered this |
| `order_id` | bigint FK (nullable) | Which customer order this fulfills |
| `order_detail_id` | bigint FK (nullable) | Which order line item |
| `supplier_order_id` | string | Supplier's reference ID |
| `quantity` | int | Codes requested |
| `cost_per_unit` | decimal | Price paid |
| `status` | string | `pending`, `processing`, `fulfilled`, `partial`, `failed` |
| `fulfilled_at` | timestamp | When codes were received |
| `codes_received` | int | How many codes actually received |

### `digital_product_codes`
The code pool. Every code (manual or from supplier) lives here.

| Column | Type | Description |
|--------|------|-------------|
| `product_id` | bigint FK | Which product this code is for |
| `code` | encrypted | AES-256-CBC encrypted code value |
| `source` | string | `manual` (admin uploaded) or `supplier_api` (fetched) |
| `status` | string | `available` → `sold` → `expired` |
| `order_detail_id` | bigint FK (nullable) | Set when assigned to a customer |
| `is_active` | bool | Soft disable without deleting |
| `expiry_date` | date (nullable) | Code expiry date |

### `supplier_api_logs`
Full request/response log for every API call.

| Column | Type | Description |
|--------|------|-------------|
| `supplier_api_id` | bigint FK | Which supplier |
| `action` | string | `place_order`, `fetch_stock`, `webhook`, `fetch_catalog` |
| `endpoint` | string | URL called |
| `request_payload` | JSON | What was sent |
| `response_payload` | JSON | What was received |
| `status` | string | `success` or `failed` |
| `http_code` | int | HTTP response code |
| `response_time_ms` | int | Latency in milliseconds |

---

## Summary Flow — End to End

```
1. Admin adds Supplier API credentials
          ↓
2. Admin syncs catalog (background job, cached 6 hours)
          ↓
3. Admin creates Mapping:
   Local Product ←→ Supplier SKU + Markup
          ↓
4. Customer places order → pays
          ↓
5. OrderObserver fires → tries local code pool first
          ↓
6. If local pool empty → dispatches SupplierCodeFetchJob
          ↓
7. Job calls SupplierManager::fulfillOrder()
   → Tries suppliers in priority order
   → Calls BambooDriver::placeOrder()
   → Bamboo returns order ID (async)
          ↓
8. Bamboo sends webhook → receives codes
   → Codes added to pool (encrypted)
   → assignAndNotify() → Customer gets email with codes
          ↓
9. Daily / manual: SyncSupplierMappingPricesJob
   → Updates cost_price from API (fetch_stock)
   → Updates product unit_price if manual stock depleted
   → Never places supplier orders (auto_restock removed 2026-07-13)
```
