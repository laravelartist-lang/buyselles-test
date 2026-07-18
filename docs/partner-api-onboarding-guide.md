# Partner API — Onboarding & Operations Guide

This document covers **how to create partner accounts, assign catalog products, fund wallets, and integrate with the Partner API**. It complements the live API reference — it does not duplicate endpoint specs, auth headers, idempotency, rate limits, or error code tables already documented there.

**Live API reference:** `/partner-api/documentation`  
**Postman collection:** `/partner-api/documentation/postman`

---

## Table of Contents

1. [Who This Guide Is For](#1-who-this-guide-is-for)
2. [Partner Types](#2-partner-types)
3. [Path A — Customer Partner (Admin-Provisioned)](#3-path-a--customer-partner-admin-provisioned)
4. [Path B — Vendor Partner (Self-Request + Approval)](#4-path-b--vendor-partner-self-request--approval)
5. [Admin — Catalog Assignment](#5-admin--catalog-assignment)
6. [Wallet & Funding](#6-wallet--funding)
7. [Integration Workflow](#7-integration-workflow)
8. [Product Types & Fulfillment](#8-product-types--fulfillment)
9. [What Happens on Order Create (Internal)](#9-what-happens-on-order-create-internal)
10. [Admin & Vendor Menu Reference](#10-admin--vendor-menu-reference)
11. [Integration Checklist](#11-integration-checklist)
12. [Troubleshooting](#12-troubleshooting)
13. [Architecture Overview](#13-architecture-overview)

---

## 1. Who This Guide Is For

| Role | Use this guide for |
|------|-------------------|
| **BuySells admin** | Creating API keys, approving vendor requests, assigning catalog, funding wallets |
| **Partner integrator** | Understanding prerequisites before calling the API |
| **Vendor** | Requesting API access from the vendor panel |

For request/response formats, authentication headers, and endpoint examples, use the **live API documentation** at `/partner-api/documentation`.

---

## 2. Partner Types

Partners access the API through a `ResellerApiKey` linked to **one** identity:

| Type | Linked account | Key created by | Wallet debited on order | Approval |
|------|---------------|----------------|-------------------------|----------|
| **Customer partner** | Storefront customer (`user_id`) | Admin only | Customer wallet | Immediate (`active`) |
| **Vendor partner** | Vendor/seller (`seller_id`) | Vendor requests → admin approves | Vendor earnings wallet | `pending` → `active` |

> There is **no self-service API registration**. Partners need either an admin-generated key (customer) or a vendor key request approved by admin.

---

## 3. Path A — Customer Partner (Admin-Provisioned)

### Step 1 — Ensure a customer account exists

Either:

- Customer registers on the storefront (`/customer/auth/register`), **or**
- Admin creates the customer: **Admin → Customer → Customer List**

### Step 2 — Fund the customer wallet

Partner orders debit the customer wallet. Without balance, orders return **402 Insufficient wallet balance**.

1. Go to **Admin → Customer → [Customer] → Wallet**
2. Add funds (admin top-up)

### Step 3 — Generate an API key

1. **Admin → Partner API → API Keys** (`/admin/reseller-keys/list`)
2. Click **Generate New Key**
3. Select the **customer** and a **key name** (e.g. `Acme Integration`)
4. Click **Generate**
5. **Copy credentials immediately** — shown only once:
   - `X-API-KEY`: `rslr_<40 random chars>`
   - `X-API-SECRET`: `<48 random chars>`
6. Key status is **`active`** immediately — no approval step

### Step 4 — Assign catalog products

See [Section 5](#5-admin--catalog-assignment).

### Step 5 — Hand off to the partner

Share securely:

- API key + secret
- Base URL: `https://<your-domain>/api/v1/partner`
- Documentation: `https://<your-domain>/partner-api/documentation`
- Postman collection: `https://<your-domain>/partner-api/documentation/postman`

---

## 4. Path B — Vendor Partner (Self-Request + Approval)

### Step 1 — Vendor registers

1. Go to `/vendor/auth/register`
2. Complete vendor registration
3. Complete any admin vendor approval steps required by your setup

### Step 2 — Vendor requests an API key

1. Log in to the vendor panel
2. Open **Developer API** (`/vendor/developer`)
3. Submit a key request with:
   - **Key name**
   - Optional **request note**
4. Credentials are displayed **once** (even while pending)
5. Key status: **`pending`** — all API calls return **403** until admin approves

### Step 3 — Admin approves the key

1. **Admin → Partner API → API Keys** (`/admin/reseller-keys/list`)
2. Filter by **Pending**
3. Click **Approve** (optional admin note)
4. Key becomes **`active`**

**Reject** sets status to `inactive` — API access remains blocked.

### Step 4 — Ensure vendor wallet has spendable balance

Vendor partner orders debit:

```
Available = SellerWallet.total_earning − pending_withdraw
```

Vendor balance grows through normal sales or admin adjustments.

### Step 5 — Assign catalog products

Same as Path A — [Section 5](#5-admin--catalog-assignment).

---

## 5. Admin — Catalog Assignment

Partners only see products **explicitly assigned** to them. Unassigned products never appear in `GET /products`.

### Method 1 — Global Catalog (storefront products)

**Use for:** In-house admin digital products that **also remain on the website**.

**Location:** **Admin → Partner API → Global Catalog** (`/admin/partner/global-catalog`)

**Steps:**

1. Open Global Catalog
2. Search or browse for a product
3. Open partner assignment for that product
4. For each partner:
   - Set **partner price** (fixed or per-denomination)
   - Toggle **visibility** (show/hide for that partner)
5. Save

Products stay on the storefront; partners receive custom pricing.

---

### Method 2 — Partner-Exclusive SKUs (supplier catalog)

**Use for:** Supplier-mapped products that **never appear on the storefront** (`partner_api_only=true`).

**Location:** **Admin → Partner API → API Keys → [Key] → Partner API Catalog** (`/admin/reseller-keys/{keyId}/catalog`)

**Steps:**

1. Open the partner's catalog page from the key list
2. Browse the **supplier catalog**
3. Select a SKU → set **exact partner price** → **Allow**
4. Optionally use **Add from global catalog** for existing storefront products

These SKUs are visible **only** via Partner API for assigned partners.

---

### Pricing rules (admin)

| Product type | How partner price is set |
|--------------|--------------------------|
| Fixed product | Single `partner_price` on the catalog item |
| Fixed denomination | Per-denomination partner price |
| Variable denomination | Formula: percent markup or flat add-on on face value |
| Direct top-up bundle | Fixed bundle price; quantity is always 1 |

Partners should always call **`POST /products/{id}/quote`** before ordering — that returns the authoritative `total` (catalog subtotal + service fee when enabled).

---

## 6. Wallet & Funding

### Customer partner

| | |
|-|-|
| **Balance source** | `users.wallet_balance` |
| **Debited on order** | Customer wallet transaction |
| **Funded by** | Admin → Customer Wallet top-up |

### Vendor partner

| | |
|-|-|
| **Balance source** | `SellerWallet.total_earning − pending_withdraw` |
| **Debited on order** | Decrement `SellerWallet.total_earning` |
| **Funded by** | Vendor sales earnings |

### Check balance via API

```http
GET /api/v1/partner/balance
```

Response includes `wallet_source`: `"customer"` or `"vendor"`. See live docs for the full response shape.

> The `reseller_api_keys.wallet_balance` column exists historically but is **not** used by current wallet logic.

---

## 7. Integration Workflow

Recommended sequence for partners (endpoint details in live docs):

```
1. GET  /balance              → Verify sufficient funds
2. GET  /products             → Discover assigned catalog
3. GET  /products/{id}        → Detail, denominations, top-up fields
4. POST /products/{id}/quote  → Authoritative price (incl. service fee)
5. POST /orders               → Place order
   • Header: X-Idempotency-Key (UUID)
   • Body: expected_total from quote
6. GET  /orders/{id}          → Poll if status is pending_fulfillment
```

Always send **`X-Idempotency-Key`** on order POST to prevent double-charging on retries. See live docs → Idempotency section.

---

## 8. Product Types & Fulfillment

| `fulfillment_type` | Source | Typical order status | Notes |
|--------------------|--------|----------------------|-------|
| `local_codes` | Pre-stocked codes in DB | `fulfilled` immediately | Codes returned in create-order response |
| `supplier_codes` | Bamboo, Golf, etc. | Often `pending_fulfillment` | Poll `GET /orders/{id}` until codes arrive |
| `direct_topup` | Supplier direct top-up | Async | Requires `direct_topup_account_id` in order body |

### Denomination products

Products with `pricing.type: "denominations"` **require** `supplier_denomination_id` on quote and order (variable amount enabled or variable denomination). Fixed supplier products with synced face values use `pricing.type: "fixed"` — order with `product_id` + `quantity` only; pass `supplier_denomination_id` optionally to pick a specific denomination.

**Full admin + API guide:** [Partner API Denominations Guide](./partner-api-denominations.md)

Quick API flow:

1. `GET /products/{id}` → read `pricing.denominations[]` and note each denomination **`id`**
2. `POST /products/{id}/quote` with `supplier_denomination_id` (and `custom_amount` for variable types)
3. `POST /orders` with the same `supplier_denomination_id`, `custom_amount` if needed, and `expected_total` from the quote

**Admin must also:** sync supplier denominations on the mapping, then set per-denomination partner prices (see denominations guide).

### Direct top-up products

- Product has `requires_account_id: true`
- Pass `direct_topup_account_id` (player ID, phone, etc.)
- Quantity is always **1** (fixed bundle)

---

## 9. What Happens on Order Create (Internal)

When `POST /orders` succeeds:

1. **Idempotency check** — cached response returned if `X-Idempotency-Key` already used
2. **Catalog eligibility** — product must be assigned to this partner's catalog
3. **Stock / top-up validation**
4. **Quote** — partner price + service fee + settlement breakdown
5. **Wallet check** — 402 if insufficient balance
6. **Debit wallet** — customer or vendor wallet
7. **Create order** — `payment_method=partner_wallet`, `customer_type=partner`
8. **Settlement** — admin margin + service fee recorded; `order_transactions` created
9. **Fulfillment:**
   - Local codes → assign immediately
   - Supplier codes → `SupplierCodeFetchJob` dispatched
   - Direct top-up → `DirectTopUpFulfillmentJob` dispatched
10. **Response** — codes if available immediately; otherwise `pending_fulfillment`

### On permanent supplier failure

- Partner wallet is **refunded**
- Admin settlement is **reversed**
- Order status → `failed`

---

## 10. Admin & Vendor Menu Reference

### Admin sidebar — Partner API

| Menu item | URL |
|-----------|-----|
| Global Catalog | `/admin/partner/global-catalog` |
| API Keys | `/admin/reseller-keys/list` |
| Key edit / permissions / IP whitelist | `/admin/reseller-keys/{id}/edit` |
| Per-key Partner Catalog | `/admin/reseller-keys/{keyId}/catalog` |
| API access logs | `/admin/reseller-keys/{id}/logs` |
| API Documentation (public) | `/partner-api/documentation` |

### Admin key actions

| Action | Route |
|--------|-------|
| Generate (customer) | `POST /admin/reseller-keys/generate` |
| Approve pending | `POST /admin/reseller-keys/approve` |
| Reject | `POST /admin/reseller-keys/reject` |
| Toggle active/inactive | `POST /admin/reseller-keys/toggle-status` |
| Regenerate credentials | `POST /admin/reseller-keys/{id}/regenerate` |

### Vendor panel — Developer API

| Action | URL |
|--------|-----|
| Dashboard | `/vendor/developer` |
| Request key | `POST /vendor/developer/request-key` |
| Update IP whitelist | `POST /vendor/developer/update-ips` |
| Regenerate credentials | `POST /vendor/developer/regenerate-key` |
| Revoke key | `POST /vendor/developer/revoke-key` |
| View access logs | `/vendor/developer/logs` |

### Key statuses

| Status | API access |
|--------|------------|
| `active` | Allowed |
| `pending` | Blocked — `"API key is awaiting admin approval."` |
| `inactive` | Blocked — `"API key is deactivated."` |

### Default permissions on new keys

- `products.list`
- `orders.create`
- `orders.view`
- `balance.view`

Default rate limit: **60 requests/minute** (configurable per key).

---

## 11. Integration Checklist

### Admin (before partner starts integrating)

- [ ] Customer or vendor account exists
- [ ] Wallet funded (customer top-up or vendor earnings)
- [ ] API key generated or vendor request approved
- [ ] Catalog products assigned with partner prices
- [ ] Credentials shared securely (one-time display only)
- [ ] Optional: IP whitelist configured on the key
- [ ] Partner given docs URL and Postman collection link

### Partner (integration)

- [ ] Store `X-API-KEY` and `X-API-SECRET` securely (env vars, secrets manager)
- [ ] Implement auth headers on every request
- [ ] Call `GET /balance` before placing orders
- [ ] Always `POST /quote` before `POST /orders`
- [ ] Pass `expected_total` from quote into order body
- [ ] Use `X-Idempotency-Key` (UUID) on every order POST
- [ ] Handle `pending_fulfillment` by polling `GET /orders/{id}`
- [ ] Handle 402, 409 (`price_changed`, stock), and 429 gracefully
- [ ] Never log or expose digital codes in your access logs

---

## 12. Troubleshooting

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| Empty product list | No catalog assigned to this partner | Admin: Global Catalog or Partner API Catalog |
| 403 awaiting approval | Vendor key still `pending` | Admin: approve key on API Keys page |
| 403 IP not allowed | IP whitelist enabled, caller IP not listed | Admin or vendor: add IP to whitelist |
| 402 on every order | Wallet empty | Fund customer wallet or ensure vendor earnings |
| 404 product not found | Product not assigned to this partner | Admin: assign product to partner catalog |
| 409 `price_changed` | Price changed between quote and order | Re-quote; update `expected_total` |
| 409 insufficient stock | No local or supplier stock | Retry later or reduce quantity |
| `pending_fulfillment` never completes | Supplier API failure | Check admin supplier logs; order may fail and refund |
| Key works locally but not production | Wrong credentials or key inactive | Regenerate key; verify `active` status |

For HTTP status codes and response shapes, see live docs → **Error Codes**.

---

## 13. Architecture Overview

```
Partner HTTP Request
        │
        ▼
ResellerApiAuth
  • X-API-KEY + X-API-SECRET validation
  • Key status (active / pending / inactive)
  • IP whitelist
  • Rate limiting
        │
        ▼
ResellerController  →  ResellerApiService
        │
        ├── PartnerProductCatalogQuery   → eligible assigned products
        ├── PartnerOrderQuoteService     → price + service fee + settlement
        ├── PartnerWalletService         → debit customer or vendor wallet
        ├── PartnerOrderSettlementService → admin margin + order_transactions
        └── Fulfillment
              ├── DigitalProductCodeService  (local codes)
              ├── SupplierCodeFetchJob       (Bamboo / Golf / etc.)
              └── DirectTopUpFulfillmentJob  (direct top-up)
```

### Key database tables

| Table | Purpose |
|-------|---------|
| `reseller_api_keys` | API credentials, permissions, status, rate limit |
| `partner_catalogs` | One catalog per partner identity |
| `partner_catalog_items` | Product assignments + partner prices |
| `partner_order_idempotency` | Cached order responses per idempotency key |
| `partner_api_logs` | Request access logs (no codes stored) |
| `orders` | Partner orders (`payment_method=partner_wallet`) |
| `order_details` | Line items + partner settlement fields |

### Key application files

| File | Role |
|------|------|
| `app/Http/Middleware/ResellerApiAuth.php` | Authentication + rate limit + logging |
| `app/Http/Controllers/Api/ResellerController.php` | HTTP layer |
| `app/Services/ResellerApiService.php` | Catalog, quote, order, balance |
| `app/Http/Controllers/Admin/Supplier/ResellerApiKeyController.php` | Admin key management |
| `app/Http/Controllers/Admin/Supplier/PartnerCatalogController.php` | Per-key catalog |
| `app/Http/Controllers/Admin/Supplier/GlobalPartnerCatalogController.php` | Global catalog |
| `app/Http/Controllers/Vendor/PartnerApiController.php` | Vendor key requests |

---

*For endpoint request/response examples, authentication, idempotency, IP whitelist, rate limits, and error codes — see `/partner-api/documentation`.*
