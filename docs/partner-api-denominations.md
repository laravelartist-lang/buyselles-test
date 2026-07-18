# Partner API — Supplier Denominations Guide

Products mapped to suppliers like **Bamboo** or **Golf** may have **multiple face values** (e.g. $10, $25, $50) synced from the supplier catalog, or a **variable amount range** (e.g. $5–$500) when variable amount is enabled on the mapping.

**Simple fixed Bamboo products** (variable amount **not** enabled in admin) work like the storefront: order with `product_id` + `quantity` only. Bamboo checkout still receives `{ ProductId, Quantity, Value }` — `Value` is resolved from the mapping SKU and supplier catalog when you omit a denomination.

You only **must** pass `supplier_denomination_id` when `order_requirements.pricing_type` is `denominations` (variable amount enabled or a variable denomination exists). Otherwise denominations are **optional** — use them to pick a specific face value and per-denomination partner price.

If you call quote or create order **without** `supplier_denomination_id` on a product that **requires** denomination selection, you get:

```json
{ "error": "A supplier denomination is required for this product." }
```

This guide covers **admin setup** and **API usage**.

---

## 1. How to tell if a product needs a denomination

`GET /api/v1/partner/products/{id}` returns `order_requirements` alongside pricing. Use this block to know exactly which params to send before quote or order:

```json
{
  "data": {
    "id": 42,
    "fulfillment_type": "supplier_codes",
    "order_requirements": {
      "fulfillment_type": "supplier_codes",
      "pricing_type": "denominations",
      "required": ["product_id", "quantity", "supplier_denomination_id"],
      "optional": ["reference", "expected_total"],
      "conditional": {
        "supplier_denomination_id": "Required when pricing.type is denominations.",
        "custom_amount": "Required when the selected denomination type is variable.",
        "direct_topup_account_id": "Not used for this product. Only required for direct_topup fulfillment."
      }
    },
    "pricing": {
      "type": "denominations",
      "currency": "USD",
      "denominations": [
        {
          "id": 7,
          "type": "fixed",
          "name": "$10 USD",
          "face_value": 10,
          "partner_price": 10.50,
          "available": true
        },
        {
          "id": 8,
          "type": "variable",
          "name": "Custom amount",
          "min_face_value": 5,
          "max_face_value": 500,
          "available": true,
          "price_source": "supplier_markup",
          "supplier_markup": { "type": "percent", "value": 5 }
        }
      ]
    }
  }
}
```

| `pricing.type` | API requirement |
|----------------|-----------------|
| `fixed` | Use `product_id` + `quantity` only. If `pricing.denominations[]` is present, `supplier_denomination_id` is **optional** to select a specific face value. |
| `direct_topup_bundle` | Pass `direct_topup_account_id` (only when `fulfillment_type` is `direct_topup`) |
| `denominations` | **Must** pass `supplier_denomination_id` (and `custom_amount` for variable types) |

### Required params by fulfillment type

| `fulfillment_type` | `pricing_type` | Required order params |
|--------------------|----------------|------------------------|
| `local_codes` | `fixed` | `product_id`, `quantity` |
| `supplier_codes` | `fixed` | `product_id`, `quantity` (optional `supplier_denomination_id` when fixed denominations are listed) |
| `supplier_codes` | `denominations` | + `supplier_denomination_id`; + `custom_amount` if variable denom |
| `direct_topup` | `direct_topup_bundle` | `product_id`, `quantity` (must be 1), `direct_topup_account_id` |

Bamboo checkout ([Place Order](https://docs.bamboocardportal.com/docs/place-order)) always sends `Products[].Value` (face value). For simple fixed orders we resolve `Value` from the mapping SKU and supplier catalog; when you pass `supplier_denomination_id`, we use that denomination's face value and SKU instead.

Bamboo and other supplier-code products never require `direct_topup_account_id`. Sending a non-empty value returns `422` with *"direct_topup_account_id is not applicable for this product."*

Use the denomination **`id`** from the list above as `supplier_denomination_id` in quote and order requests.

---

## 2. Admin setup (required before partners can order)

Denomination products need **two layers** configured:

### Step A — Supplier denominations must exist on the product mapping

Denominations live in `supplier_product_denominations`, linked to the product's supplier mapping.

#### Storefront / Global Catalog products

1. **Admin → Supplier → Product Mappings**
2. Create or edit a mapping for the product (Bamboo/Golf SKU)
3. On save, **`SyncDenominationsJob`** runs automatically and imports face values from the supplier

Wait for the queue worker (Horizon) to finish. Verify denominations exist in the mapping edit screen.

#### Partner-exclusive SKUs (from supplier catalog)

1. **Admin → Partner API → API Keys → [Key] → Partner API Catalog**
2. **Add from supplier catalog** → pick SKU → set partner price → Allow
3. **`SyncDenominationsJob`** is dispatched automatically for new mappings

Again, wait for Horizon to sync denominations.

---

### Step B — Partner price per denomination (required for API)

For each denomination a partner can buy, set a **partner price** in `partner_catalog_denomination_prices`.

#### Option 1 — Fixed denominations: set price per denomination

POST to the partner catalog store endpoint (admin session required):

```
POST /admin/reseller-keys/{keyId}/catalog
```

```json
{
  "product_id": 42,
  "currency": "USD",
  "is_active": true,
  "denomination_prices": [
    { "denomination_id": 7, "partner_price": 10.50 },
    { "denomination_id": 9, "partner_price": 25.00 }
  ]
}
```

Each `denomination_id` must match a **fixed** denomination on that product's mapping.

#### Option 2 — Variable denomination: partner formula (optional override)

By default, variable denominations use the **supplier mapping markup** already configured when the product was assigned to the supplier (same as the web storefront range). No extra admin step is required.

Optionally set a **partner-specific** formula to override supplier markup:

```json
{
  "product_id": 42,
  "currency": "USD",
  "is_active": true,
  "variable_price_type": "percent",
  "variable_price_value": 5
}
```

- `percent` — partner price = `custom_amount × (1 + value/100)`
- `flat` — partner price = `custom_amount + value`

When no formula is set, partner price = supplier markup applied to `custom_amount` (`price_source: supplier_markup` in product detail).

#### Option 3 — Simple fixed-price products (no denominations)

If the product has **no** supplier denominations (badge shows `fixed` in partner catalog), set a single **`partner_price`** via Global Catalog or the inline price field on the partner catalog page.

> **Note:** The partner catalog UI inline price field sets a flat `partner_price` only. For multi-denomination products, use the store endpoint above with `denomination_prices` until a dedicated denomination UI is added.

---

## 3. API workflow (partner integrator)

### Fixed denomination (e.g. $10 card)

```http
POST /api/v1/partner/products/42/quote
Content-Type: application/json

{
  "quantity": 1,
  "supplier_denomination_id": 7
}
```

Then order with the same fields plus `expected_total` from the quote:

```http
POST /api/v1/partner/orders
X-Idempotency-Key: <uuid>
Content-Type: application/json

{
  "product_id": 42,
  "quantity": 1,
  "supplier_denomination_id": 7,
  "expected_total": 10.71,
  "reference": "order-001"
}
```

### Variable denomination (e.g. $5–$500 custom amount)

```http
POST /api/v1/partner/products/42/quote
Content-Type: application/json

{
  "quantity": 1,
  "supplier_denomination_id": 8,
  "custom_amount": 50
}
```

Order:

```json
{
  "product_id": 42,
  "quantity": 1,
  "supplier_denomination_id": 8,
  "custom_amount": 50,
  "expected_total": 52.50,
  "reference": "order-002"
}
```

`custom_amount` is the amount entered within `min_face_value`–`max_face_value`. Partner price is calculated from supplier markup unless a partner formula override is configured.

---

## 4. Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `A supplier denomination is required for this product.` | Quote/order on customizable/variable product without `supplier_denomination_id` | Enable only when `pricing.type` is `denominations`; otherwise use `product_id` + `quantity` |
| `The selected denomination is not available for this product.` | Wrong `supplier_denomination_id` | Use `id` from product detail response |
| `This denomination has no partner price.` | Admin did not set `denomination_prices` | Admin: POST catalog store with per-denomination prices |
| `A custom amount is required for this denomination.` | Variable denomination without `custom_amount` | Pass `custom_amount` within min/max range |
| `The custom amount is outside the allowed range.` | `custom_amount` below min or above max | Use value within `min_face_value`–`max_face_value` from product detail |
| `This product has no partner pricing formula.` | Legacy error — should not occur when supplier markup is configured | Ensure product has active supplier mapping with markup; re-download API docs if using an old build |

---

## 5. Postman collection

Download the latest collection from **Partner API Documentation → Postman**.

**Fill the Params tab** on each request — no JSON body. Check `order_requirements` on product detail and use the matching request:

| Request | Use when |
|---------|----------|
| **Quote Product (simple)** | Fixed price or direct top-up |
| **Quote Product (denomination)** | `pricing.type` is `denominations` |
| **Create Order (simple)** | Fixed price / supplier codes (Bamboo) |
| **Create Order (denomination)** | Denomination products |
| **Create Order (direct top-up)** | `fulfillment_type` is `direct_topup` |

Empty optional params (e.g. `supplier_denomination_id=`) are treated as absent — you will not get spurious validation errors on fixed-price products.

---

*See also: [Partner API Onboarding Guide](./partner-api-onboarding-guide.md) · Live API docs at `/partner-api/documentation`*
