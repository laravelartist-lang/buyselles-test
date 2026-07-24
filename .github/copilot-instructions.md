# 🤖 GitHub Copilot — Buyselles.com (Bona Kart) Developer Instructions

> **Read this entire file before writing a single line of code.**
> This is the complete project bible. Every decision must align with what is written here.

---

## 📌 Project Summary

**Buyselles.com (Bona Kart)** — a hybrid e-commerce platform for selling **digital products** (gift cards, game keys, digital codes, gaming accounts) and **physical products** with full vendor marketplace support.

- **Base Script:** 6valley (Laravel + Flutter) — licensed from CodeCanyon
- **Customization Level:** Deep — core modules extended + new modules built from scratch
- **Stack:** Laravel (Backend + Web Panel) + Flutter (Android & iOS apps)
- **Deployment:** Ubuntu 24.04 VPS, Nginx, MySQL/MariaDB
- **Total Timeline:** 8 Weeks across 7 Milestones
- **Total Budget:** $2,200

---

## 🖥️ Server Configuration — Already Done

> ✅ Do NOT re-install or re-configure any of these.

| Setting | Value |
|---|---|
| Provider | Contabo VPS |
| IP | 62.171.136.95 |
| OS | Ubuntu 24.04.4 LTS |
| Web Server | Nginx ✅ |
| PHP | 8.2.30 ✅ |
| MySQL | 8.0.45 ✅ |
| Redis | Installed ✅ |
| Composer | 2.9.5 ✅ |
| Node.js | v20.x ✅ |
| Certbot | Installed (SSL ready) ✅ |
| Web Root | `/var/www/buyselles` |

**Database Credentials:**
- DB Name: `buyselles_db`
- DB User: `buyselles_user`
- DB Password: `BuySelles@2024#`
- DB Host: `localhost`

**Pending (script not received yet):**
- ⏳ 6valley script upload to `/var/www/buyselles`
- ⏳ Domain pointing to server IP
- ⏳ Nginx virtual host configuration
- ⏳ SSL certificate via Certbot
- ⏳ Laravel `.env` configuration
- ⏳ Composer install + migrations

**Nginx config (use when script arrives):**
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/buyselles/public;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    index index.php;
    charset utf-8;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    error_page 404 /index.php;
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

**Laravel `.env` key values:**
```env
APP_NAME=Buyselles
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=buyselles_db
DB_USERNAME=buyselles_user
DB_PASSWORD=BuySelles@2024#

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────┐
│              BUYSELLES PLATFORM              │
├──────────────┬──────────────────────────────┤
│  Flutter App │     Laravel Web Panel        │
│  Android/iOS │     + REST API               │
├──────────────┴──────────────────────────────┤
│           Laravel Backend Core              │
│  Jobs | Queues | Events | Webhooks          │
├─────────────────────────────────────────────┤
│  MySQL  │  Redis  │  Firebase  │  Storage   │
├─────────────────────────────────────────────┤
│  NOWPayments  │ Visa/MC Gateway │ Suppliers │
└─────────────────────────────────────────────┘
```

---

## 🗂️ What Exists vs What to Build

### ✅ Already in 6valley (Do NOT rebuild)
- Multi-vendor marketplace core
- Basic product management
- Order processing flow
- Basic vendor dashboard
- Basic admin panel
- Basic wallet system
- Basic Flutter app shell
- Basic dispute/ticket system

### ⚠️ Needs Modification (Extend existing)
- Product system → Add digital product types
- Wallet system → Add crypto + auto-refund
- Vendor dashboard → Add KYC, ratings, location
- Admin panel → Add API config, crypto logs, activity logs
- Flutter app → Add new screens for all new features

### 🔨 Build from Scratch (New modules)
- Dual-Way API system (Outbound + Inbound)
- USDT TRC20 crypto payment via **NOWPayments**
- Credit Card payment via Gateway (Visa/Mastercard + 3D Secure)
- Escrow protection module
- KYC identity verification system
- Thermal print system (Web + Flutter)
- AI Fraud Detection
- AI Dynamic Pricing
- Auto-currency converter
- Bulk upload tool
- Firebase push notifications
- WhatsApp/SMS notifications
- Automated daily backup system (Google Drive / S3)
- Vendor Payout Workflow (USDT + Bank Transfer)
- Variable shipping cost engine
- App Store / Play Store deployment config

---

## 📋 Milestone Breakdown

| # | Milestone | Amount | Due Date | Status |
|---|---|---|---|---|
| M1 | Environment Setup, Base Install & SSL | $200 | Mar 24, 2026 | 🟡 Active |
| M2 | Security Core & AES-256 Encryption | $350 | Mar 31, 2026 | ⏳ Pending |
| M3 | Dual API Integration (Inbound/Outbound) | $350 | Apr 14, 2026 | ⏳ Pending |
| M4 | Dispute Center & Escrow Logic | $350 | Apr 21, 2026 | ⏳ Pending |
| M5 | Payment Gateways & Location Logic | $350 | Apr 28, 2026 | ⏳ Pending |
| M6 | Mobile Apps Update & Bitmap Printing | $300 | May 14, 2026 | ⏳ Pending |
| M7 | Final Testing & Store Publishing | $300 | May 28, 2026 | ⏳ Pending |
| | **Total** | **$2,200** | | |

---

## 🔧 M1 — Environment Setup, KYC, 2FA

### 1.3 KYC System

**New table: `vendor_kyc`**
```
id, vendor_id (FK→vendors), id_type (enum: passport|national_id|driving_license),
id_front_image, id_back_image, selfie_image,
status (enum: pending|approved|rejected), rejection_reason (nullable),
reviewed_by (FK→admins, nullable), reviewed_at, submitted_at,
created_at, updated_at
```

**Rules:**
- Vendor CANNOT list products until KYC approved
- Vendor CANNOT withdraw funds until KYC approved
- Admin notified on every new KYC submission
- Rejection triggers email + in-app notification to vendor

**Files to create:**
- `app/Models/VendorKyc.php`
- `app/Http/Controllers/Vendor/KycController.php`
- `app/Http/Controllers/Admin/KycManagementController.php`
- `app/Services/KycService.php`
- `app/Notifications/KycStatusNotification.php`
- Migration file
- Blade views for vendor KYC upload form + admin KYC review panel

### 1.4 Two-Factor Authentication (2FA)

**Scope:**
- 2FA **mandatory** for Admin and Vendor login — cannot be disabled
- 2FA mandatory for all wallet actions: withdraw, transfer, deposit
- Methods: **Google Authenticator (TOTP)** + **Email OTP**
- Regular customers: optional

**DB columns to add to `users`:** `two_factor_secret`, `two_factor_enabled`, `two_factor_method`

**Files to create:**
- `app/Http/Middleware/Require2FA.php`
- `app/Http/Controllers/TwoFactorController.php`
- `app/Services/TwoFactorService.php`
- Views for 2FA setup, verify (Google Auth + Email), and backup codes

### 1.5 Automated Daily Backups

- **Package:** `spatie/laravel-backup`
- DB backup: every day at 2:00 AM
- Source code backup: every day at 2:30 AM
- Retention: 14 days
- Destinations: Google Drive + AWS S3
- On failure: email admin immediately + log to `backup_logs` table
- `.env` file excluded from backup

**Files to create:**
- `config/backup.php`
- `app/Jobs/DatabaseBackupJob.php`
- `app/Notifications/BackupFailedNotification.php`

---

## 🔧 M2/M3 — Security Core & Dual API Integration

### 2.1 AES-256-CBC Encryption

- All digital codes encrypted at rest using Laravel `Crypt` facade (AES-256-CBC)
- Encrypt on save, decrypt **only** at moment of delivery — never in list endpoints
- API keys stored hashed — never plain text

### 2.2 Outbound Supplier API

**Architecture:**
```
Order Placed → Check local stock → (if empty) Dispatch SupplierFetchJob
→ Call Supplier API → Save code → Update order → Notify customer
```

**New table: `supplier_apis`**
```
id, name, base_url, api_key (encrypted), api_secret (encrypted),
rate_limit_per_minute, is_active, last_sync_at, created_at, updated_at
```

**New table: `supplier_api_logs`**
```
id, supplier_api_id, endpoint, request_payload (JSON), response_payload (JSON),
status (success|failed), http_code, response_time_ms, created_at
```

**Files to create:**
- `app/Services/SupplierApiService.php`
- Price sync: `SyncSupplierMappingPricesJob` — daily + manual admin Sync Prices (read-only `fetch_stock`, no purchases).
- `app/Jobs/SupplierCodeFetchJob.php` — retry 3x with exponential backoff (30s/60s/120s)
- `app/Http/Controllers/Api/SupplierWebhookController.php` — `POST /api/supplier/webhook`
- `app/Models/SupplierApi.php`
- `app/Models/SupplierApiLog.php`

### 2.3 Inbound Reseller API

**Auth:** API Key via `X-API-KEY` header

**New table: `reseller_api_keys`**
```
id, user_id (FK→users), api_key (unique, hashed), api_secret (hashed),
allowed_ips (JSON), rate_limit_per_minute (default: 60),
is_active, last_used_at, created_at, updated_at
```

**Endpoints:**

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/reseller/products` | List products |
| GET | `/api/reseller/products/{id}` | Product details + stock |
| POST | `/api/reseller/orders` | Create order |
| GET | `/api/reseller/orders/{id}` | Order status |
| GET | `/api/reseller/balance` | Wallet balance |

**Files to create:**
- `app/Http/Controllers/Api/ResellerController.php`
- `app/Http/Middleware/ResellerApiAuth.php`
- `app/Services/ResellerApiService.php`
- `app/Models/ResellerApiKey.php`

### 2.4 Bulk Upload Tool

- Package: `maatwebsite/excel`
- Process in chunks of 100 rows via Jobs
- Validate: product exists, code not duplicate
- Report: X successful, X failed, X duplicate

**Files to create:**
- `app/Imports/DigitalCodesImport.php`
- `app/Jobs/ProcessBulkUploadJob.php`
- `app/Http/Controllers/Vendor/BulkUploadController.php`

### 2.5 Auto-Currency Converter

- Fetch rates from CoinGecko / exchangerate-api every 30 min
- Store in `currency_rates` table
- All prices stored in USD base, displayed in user's locale
- Rate applied at checkout — never stored in order

**Files to create:**
- `app/Services/CurrencyRateService.php`
- `app/Jobs/SyncCurrencyRatesJob.php`
- Migration for `currency_rates`

---

## 🔧 M4/M5 — Payments, Location, Escrow, Dispute

### 3.1 USDT TRC20 via NOWPayments

**API Docs:** https://documenter.getpostman.com/view/7907941/S1a32n38

**Payment flow:**
```
User selects USDT → Call NOWPayments API → Get address + amount
→ Create order (status: pending_payment) → Show QR + 30min countdown
→ NOWPayments IPN webhook → Verify HMAC-SHA512
→ payment_status = "finished" → Update order → Deliver code
```

**New table: `crypto_transactions`**
```
id, order_id (FK), user_id (FK), nowpayments_payment_id, nowpayments_invoice_id,
wallet_address, pay_currency (USDTTRC20), pay_amount, actually_paid,
price_amount (USD), payment_status (waiting|confirming|confirmed|finished|failed|expired|partially_paid),
ipn_payload (JSON), confirmed_at, expires_at, created_at, updated_at
```

**Fallback cron:** Every 10 min, poll `GET /v1/payment/{id}` for pending orders > 5 min old where webhook not received.
**Partial payment:** Flag for admin review — never auto-approve, never auto-refund.

**Files to create:**
- `app/Services/NowPaymentsService.php`
- `app/Http/Controllers/Api/CryptoCallbackController.php` — route: `POST /api/crypto/callback` (exempt from CSRF + auth)
- `app/Jobs/VerifyCryptoPendingOrdersJob.php`
- `app/Models/CryptoTransaction.php`

### 3.2 Escrow Protection

**States:** `held` → `released` / `disputed` / `refunded`

**Flow:** Payment received → held → Code delivered → Buyer confirms (or auto 48h) → Released to vendor
On dispute: Admin reviews → releases or refunds

**Files to create:**
- `app/Models/Escrow.php`
- `app/Services/EscrowService.php`
- `app/Jobs/AutoReleaseEscrowJob.php` — runs hourly, releases after 48h
- `app/Http/Controllers/EscrowController.php`

### 3.3 COD Logic

- COD only for physical products
- If cart has ANY digital product → hide COD completely (enforce server-side)

```php
if ($cart->hasDigitalProducts()) {
    $availablePaymentMethods = $availablePaymentMethods->reject('cod');
}
```

### 3.4 Location Filtering — Country > City > Area

**New tables:** `countries`, `cities`, `areas`

**Variable shipping:** `vendor_shipping_rates` (vendor_id, area_id, price, estimated_days)
- If vendor has no rate for selected area → "Shipping not available"
- COD availability can be restricted by area

**Files to create:**
- Migrations for `countries`, `cities`, `areas`, `vendor_shipping_rates`
- `app/Http/Controllers/Api/LocationController.php`
- `app/Http/Controllers/Vendor/ShippingRatesController.php`

### 4.5 Dispute Center

**New table: `disputes`**
```
id, order_id (FK), buyer_id (FK→users), vendor_id (FK→users), reason (text),
status (enum: open|under_review|resolved_refund|resolved_release|closed),
admin_decision (text nullable), resolved_by (FK→admins nullable), resolved_at,
created_at, updated_at
```

**New table: `dispute_evidence`**
```
id, dispute_id (FK), uploaded_by (FK→users), user_type (enum: buyer|vendor|admin),
file_path, file_type (enum: image|video), caption (nullable), created_at
```

**Upload limits:** Images JPG/PNG max 5MB, Videos MP4 max 50MB, max 5 files per submission

**Files to create:**
- `app/Models/Dispute.php`
- `app/Models/DisputeEvidence.php`
- `app/Services/DisputeService.php`
- `app/Http/Controllers/DisputeController.php`
- `app/Http/Controllers/Admin/DisputeArbitrationController.php`
- `app/Notifications/DisputeStatusNotification.php`
- Blade views: dispute form, evidence upload, admin arbitration panel

---

## 🔧 M6 — Mobile, Thermal Printing, Notifications

### Thermal Printing — Web Panel

- "Print Receipt" button on every order detail page
- CSS `@media print` for 80mm and 58mm paper
- Receipt: logo, order ID + date, product, code (masked → visible on print), amount, vendor, QR code

**Files to create:**
- `resources/css/thermal-print.css`
- `resources/views/print/receipt.blade.php`
- `app/Http/Controllers/PrintController.php`

### Thermal Printing — Flutter

> ⚠️ CRITICAL: **NEVER** send Arabic text as raw ESC/POS bytes. Always render to image first, then send as bitmap.

```dart
Future<List<int>> arabicTextToBitmap(String text) async {
  // 1. Render Arabic text on Canvas off-screen
  // 2. Convert to ui.Image
  // 3. Convert to ESC/POS raster format
  // 4. Send as bitmap command
}
```

**Flutter packages:**
```yaml
esc_pos_bluetooth: ^0.4.0
esc_pos_wifi: ^0.4.0
esc_pos_utils: ^1.1.0
flutter_bluetooth_serial: ^0.4.0
```

**Files to create:**
- `lib/services/thermal_printer_service.dart`
- `lib/services/arabic_bitmap_service.dart`
- `lib/screens/vendor/printer_settings_screen.dart`
- `lib/widgets/print_receipt_button.dart`

### Flutter New Screens

| Screen | Description |
|---|---|
| KYC Upload Screen | ID front, ID back, Selfie upload with camera |
| KYC Status Screen | Show pending/approved/rejected |
| 2FA Setup Screen | QR code + verify |
| 2FA Verify Screen | Shown before wallet actions |
| Location Filter Screen | Country + City dropdown |
| Crypto Payment Screen | Wallet address + QR + countdown timer |
| Escrow Status Screen | Order escrow state + confirm/dispute |
| Printer Settings Screen | Scan + pair Bluetooth/WiFi printer |
| Print Receipt Screen | Preview + print |

**All Flutter screens must:** Support Arabic RTL, handle loading/error/empty states, use existing app theme.

### Notifications — Multi-Channel

**Priority:** Push (always) → WhatsApp (if enabled) → SMS (fallback)

| Event | Push | WhatsApp | SMS |
|---|---|---|---|
| New order | ✅ Vendor | ✅ Vendor | ❌ |
| Payment received | ✅ Vendor | ✅ Vendor | ✅ Vendor |
| Code delivered | ✅ Customer | ❌ | ❌ |
| Dispute opened | ✅ Vendor + Admin | ✅ Admin | ❌ |
| KYC status change | ✅ Vendor | ✅ Vendor | ❌ |
| Withdrawal processed | ✅ Vendor | ✅ Vendor | ✅ Vendor |
| Escrow released | ✅ Vendor | ❌ | ❌ |

**Files to create:**
- `app/Services/NotificationService.php`
- `app/Notifications/OrderNotification.php`
- `app/Notifications/PaymentNotification.php`
- `app/Notifications/DisputeNotification.php`
- `app/Services/WhatsAppService.php`
- `app/Services/SmsService.php`

---

## 💸 Vendor Payout Workflow

**Methods:** USDT TRC20 (via NOWPayments) + Bank Transfer (manual)

**Flow:** Vendor requests → KYC check → 2FA verify → Admin reviews → Deduct commission → Pay + notify

**New table: `withdrawal_requests`**
```
id, vendor_id (FK→users), amount_requested, commission_rate, commission_amount,
amount_after_commission, method (enum: usdt|bank_transfer),
usdt_wallet_address (nullable), bank_details (JSON nullable),
status (enum: pending|approved|rejected|paid), admin_note, txn_reference,
processed_by (FK→admins nullable), processed_at, created_at, updated_at
```

**Files to create:**
- `app/Models/WithdrawalRequest.php`
- `app/Http/Controllers/Vendor/WithdrawalController.php`
- `app/Http/Controllers/Admin/PayoutManagementController.php`
- `app/Services/PayoutService.php`

---

## 🔒 Security Requirements

### Rate Limiting (Redis-backed)
- Public API: 60 req/min per IP
- Reseller API: configurable per key (default 60/min)
- Login endpoint: 5 attempts per minute

### Activity Logging

Every admin and vendor action must be logged:

```php
// app/Models/ActivityLog.php
// Fields: user_id, user_type, action, entity_type, entity_id,
//         ip_address, device_id, device_type, user_agent,
//         payload (JSON), created_at
```

**Device ID:** From `X-Device-ID` header (Flutter) or fingerprint from user-agent + IP (web).

**Actions to log:** login, logout, KYC approve/reject, order status change, withdrawal approve,
API key create/revoke, product create/delete, settings change, dispute open/resolve, backup success/fail

### Input Validation
- All requests use Laravel Form Requests
- Sanitize all file uploads
- KYC images: jpg/png/pdf only, max 5MB

---

## 🌍 Multi-Language & RTL Support

**Languages:** Arabic, English, Chinese, Vietnamese

**Web Panel:**
- Detect language → Arabic → add `dir="rtl"` to `<html>`
- Use CSS logical properties (`margin-inline-start` not `margin-left`)
- Tailwind CSS: use `rtl:` variant classes

**Flutter:**
- Wrap entire app with `Directionality` widget
- `TextDirection.rtl` when locale = Arabic
- Mirror directional icons in RTL
- Use `flutter_localizations` package

---

## 📦 Required Laravel Packages

```json
{
  "require": {
    "pragmarx/google2fa-laravel": "^2.0",
    "bacon/bacon-qr-code": "^2.0",
    "maatwebsite/excel": "*",
    "laravel-notification-channels/fcm": "^4.0",
    "spatie/laravel-activitylog": "^4.0",
    "spatie/laravel-backup": "^8.0",
    "guzzlehttp/guzzle": "^7.0",
    "twilio/sdk": "^7.14",
    "nao-pon/laravel-backup-driver-google-drive": "^1.0"
  }
}
```

---

## ⚠️ Critical Rules — Apply to Every Task

1. **Never call Supplier API in a live request** — always Jobs/Queues
2. **Never send Arabic text to thermal printer as raw bytes** — convert to bitmap first
3. **Always verify NOWPayments IPN with HMAC-SHA512** — fake callbacks are a real threat
4. **Never auto-approve partial crypto payments** — flag for admin review
5. **Never store API keys or secrets in plain text** — always encrypt/hash
6. **Never allow vendor to sell/withdraw without KYC approval**
7. **Always validate file uploads** — KYC images must be jpg/png/pdf only, max 5MB
8. **COD must be hidden if cart has any digital product** — enforce server-side
9. **Escrow auto-release only after 48 hours** — never immediate
10. **All wallet actions AND admin/vendor login require 2FA** — no exceptions
11. **Digital codes MUST use AES-256-CBC encryption** — encrypt on save, decrypt only at delivery
12. **Dispute funds stay in escrow until admin resolves** — never auto-release during active dispute
13. **Dispute evidence: JPG/PNG max 5MB, MP4 max 50MB** — validate server-side
14. **Daily backups must run every night** — alert admin immediately on failure
15. **Vendor withdrawal requires KYC + 2FA + minimum balance check**
16. **Location selection is 3-level: Country → City → Area** — never skip a level
17. **RTL layout must work on ALL pages** — test every screen in Arabic before marking done
18. **WhatsApp/SMS for critical events only** — do not spam vendors
19. **Never use inline `@php()` in Blade files** — `@php($var = expr)` compiles without closing `?>` in Laravel 12, breaking all subsequent directives. Always use `@php $var = expr; @endphp` block form.

---

## 🚀 Task Execution Process

When given any task:

1. Check which **Milestone** it belongs to
2. Check the **files list** for that section — create exactly those files
3. Follow the **database schema** as defined — do not add/remove fields without asking
4. Respect **Critical Rules** at all times
5. Ask if anything is unclear **before** writing code
6. **One task at a time** — complete and confirm before moving to next

---

## 🛡️ Post-Launch Warranty (30 Days)

**Covered:** Bug fixes for agreed scope, server-side errors, payment flow failures, app crashes on supported devices.

**NOT Covered:** New features, third-party API changes, design changes, client-modified code issues.

**Response Time:** Within 48 hours of reported bug.

---

---

## 🗺️ Actual Codebase Map — Read Before Every Change

> ✅ The 6valley script IS already installed and running in this workspace. All information below reflects the real codebase.

### Authentication & Guards

Three separate auth guards — never mix them up:

| Guard | Model | Driver | Used For |
|---|---|---|---|
| `web` / `customer` | `App\Models\User` | session + Passport API | Customers (web + Flutter app) |
| `admin` | `App\Models\Admin` | session | Admin panel |
| `seller` | `App\Models\Seller` | session + `auth_token` | Vendor panel + seller API |

- Flutter/API auth uses **Laravel Passport** (`HasApiTokens` on `User`)
- Sellers have a raw `auth_token` column for mobile API — see `SellerApiAuthMiddleware`
- Admins have no Passport — session only

### Middleware Aliases (registered in `bootstrap/app.php`)

```
admin          → AdminMiddleware
seller         → SellerMiddleware
customer       → CustomerMiddleware
seller_api_auth → SellerApiAuthMiddleware   ← existing vendor API auth
api_lang       → APILocalizationMiddleware
maintenance_mode → MaintenanceModeMiddleware
delivery_man_auth → DeliveryManAuth
guestCheck     → GuestMiddleware
apiGuestCheck  → APIGuestMiddleware
actch          → ActivationCheckMiddleware
module         → ModulePermissionMiddleware
```

### Route File Locations

```
routes/
  admin/routes.php          ← all admin panel routes
  vendor/routes.php         ← all vendor/seller panel routes
  web/routes.php            ← all frontend/customer web routes
  rest_api/
    v1/api.php              ← REST API v1 (Flutter app)
    v2/api.php              ← REST API v2 (Flutter app)
    v3/                     ← REST API v3
  shared.php
  console.php
  channels.php
```

When adding new routes, follow existing pattern: admin routes → `routes/admin/routes.php`, API → `routes/rest_api/v1/api.php` or v2.

### Existing Key Models (DO NOT recreate)

**Core commerce models already in `app/Models/`:**
- `User` — customer, has `wallet_balance`, `loyalty_point`, `cm_firebase_token`, `referral_code`
- `Seller` — vendor, has `auth_token`, `sales_commission_percentage`, `minimum_order_amount`, `stock_limit`
- `Admin` — admin panel user
- `Shop` — vendor's shop (belongs to Seller)
- `Product` — **already has digital product support**: `product_type`, `digital_product_type`, `digital_file_ready`, `preview_file`
- `ProductStock` — stock variants
- `Order` — has `payment_method`, `payment_status`, `order_status`, `order_type`, `seller_id`, `seller_is`
- `OrderDetail` — has `product_type`, `digital_product_type`, `digital_file_ready`
- `OrderTransaction`
- `Cart`, `CartShipping`
- `WithdrawRequest` — existing withdrawal model (extend, don't replace)
- `WithdrawalMethod` — withdrawal method configs
- `SellerWallet`, `SellerWalletHistory`
- `CustomerWallet`, `CustomerWalletHistory`
- `AdminWallet`, `AdminWalletHistory`
- `WalletTransaction`
- `RefundRequest`, `RefundStatus`, `RefundTransaction`
- `SupportTicket`, `SupportTicketConv`
- `Notification`, `NotificationMessage`, `NotificationSeen`
- `ShippingAddress`, `BillingAddress`
- `ShippingMethod`, `ShippingType`, `CategoryShippingCost`
- `DeliveryMan`, `DeliveryHistory`, `DeliveryManTransaction`, `DeliverymanWallet`
- `DeliveryZipCode`, `DeliveryCountryCode`
- `Coupon`, `FlashDeal`, `FlashDealProduct`, `DealOfTheDay`
- `Review`, `ReviewReply`
- `Category`, `Brand`, `Color`, `Tag`, `Attribute`
- `Currency` — existing multi-currency model
- `BusinessSetting` — key-value app settings store
- `OfflinePaymentMethod`, `OfflinePayments`
- `Transaction`
- `ErrorLogs`
- `Storage` — file storage tracking
- `StockClearanceSetup`, `StockClearanceProduct`
- `RestockProduct`, `RestockProductCustomer`
- `DigitalProductVariation`, `DigitalProductOtpVerification`
- `Author`, `PublishingHouse`, `DigitalProductAuthor`, `DigitalProductPublishingHouse`
- `VendorWithdrawMethodInfo` — vendor payout info (already exists, extend this)

### Existing Controllers Structure

```
app/Http/Controllers/
  Admin/             ← admin panel controllers (many sub-folders)
  Vendor/            ← vendor panel controllers (many sub-folders)
  Customer/          ← customer-facing controllers
  RestAPI/
    v1/              ← API v1 controllers (full set of endpoints)
    v2/              ← API v2 controllers
    v3/              ← API v3 controllers
  Payment_Methods/   ← 12+ payment gateways already integrated
  Auth/              ← login/register
  Web/               ← frontend web controllers
```

**Existing Payment Gateways** (already in `Payment_Methods/`):
Stripe, PayPal, RazorPay, PayTabs, SslCommerz, Flutterwave, Bkash,
LiqPay, MercadoPago, Paystack, Senangpay, Paytm, Paymob

### Existing Services (DO NOT duplicate)

All in `app/Services/`:
- `NotificationService` — already exists, extend it for new channels
- `PushNotificationService` — Firebase push already implemented
- `FirebaseService` — Firebase integration
- `MailService` — email sending
- `OrderService`, `OrderDetailsService` — order processing
- `ProductService` — product management
- `VendorWalletService`, `CustomerWalletService` — wallet operations
- `WithdrawRequestService`, `WithdrawalMethodService` — withdrawal handling
- `RefundRequestService`, `RefundTransactionService` — refund handling
- `SupportTicketService` — ticket system
- `ShippingMethodService`, `ShippingTypeService` — shipping
- `CartService` — cart management
- `PayoutService` — vendor payouts part of service layer
- Many more...

### Modules System

Uses **nwidart/laravel-modules** (`^10.0`). Existing modules in `Modules/`:
- `AI/` — AI features module
- `Blog/` — Blog module
- `TaxModule/` — Tax calculation module

When building Milestones that are large enough, new modules may go in `Modules/`.
Otherwise, follow the existing `app/` structure.

### Digital Products — Already Partially Implemented

The `products` table already has:
- `product_type` (physical / digital)
- `digital_product_type`
- `digital_file_ready`
- `preview_file`

The `order_details` table already has:
- `product_type`
- `digital_product_type`
- `digital_file_ready`

`DigitalProductVariation`, `DigitalProductOtpVerification` models exist.
**Do NOT create these from scratch** — extend what exists.

### Jobs

Only `app/Jobs/SendEmailJob.php` exists. All new jobs go in `app/Jobs/`.

### Key Config Files

- `config/auth.php` — auth guards config
- `bootstrap/app.php` — middleware registration (Laravel 12 style, no Kernel.php)
- `bootstrap/providers.php` — service providers
- `config/` — all Laravel config including payment-specific configs

### Coding Conventions Observed

1. Models use `@property` PHPDoc blocks extensively — maintain this
2. Services follow `XxxService.php` naming pattern
3. API versioned under `v1/`, `v2/`, `v3/`
4. Traits in `app/Traits/` — `StorageTrait`, `CacheManagerTrait`, `DemoMaskingTrait`
5. Existing `WithdrawRequest` model (note singular) — new `withdrawal_requests` table entries extend this
6. `BusinessSetting` model is the key-value config store — use for feature flags/settings
7. Use `app/Http/Middleware/` for new middleware and register in `bootstrap/app.php`

#### ⚠️ Blade `@php()` Inline Syntax Bug — CRITICAL

**NEVER** use the inline `@php()` syntax in Blade files:

```blade
{{-- ❌ BROKEN — compiles to <?php($var = expr) WITHOUT closing ?> --}}
@php($extensionIndex = 0)
@php($guestCheckout = getWebConfig(name: 'guest_checkout'))
```

In this Laravel 12 codebase, `@php($var = expr)` compiles to `<?php($var = expr)` **without a closing `?>`**. This swallows all subsequent Blade directives as raw PHP, causing:
- Blade directives appearing as raw text on page
- "Cannot end a push stack" errors
- Sections of the page silently disappearing

**ALWAYS** use the block form instead:

```blade
{{-- ✅ CORRECT — always use block form --}}
@php $extensionIndex = 0; @endphp
@php $guestCheckout = getWebConfig(name: 'guest_checkout'); @endphp

{{-- ✅ ALSO CORRECT — multi-line block --}}
@php
    $companyReliability = getWebConfig('company_reliability');
@endphp
```

**When editing any Blade file**, grep for `@php(` and convert any inline occurrences to block form before finishing.

### UI / Frontend Conventions

#### Icons — Flaticon (`fi`) only, NOT Bootstrap Icons (`bi`)

Both admin and vendor panels use **Flaticon** as the icon library. **Never** use Bootstrap Icons (`bi bi-*`).

| Style | Class prefix | Usage |
|---|---|---|
| Regular (outline) | `fi fi-rr-*` | Default for body text, tables, buttons |
| Solid (filled) | `fi fi-sr-*` | Emphasis, alerts, status indicators |

**Common icon mapping (Bootstrap → Flaticon):**

| Concept | ❌ Wrong (Bootstrap) | ✅ Correct (Flaticon) |
|---|---|---|
| Upload | `bi bi-upload` | `fi fi-sr-inbox-in` |
| Download | `bi bi-download` | `fi fi-rr-download` |
| Delete / Trash | `bi bi-trash` | `fi fi-rr-trash` |
| Lock | `bi bi-lock-fill` | `fi fi-rr-lock` or `fi fi-sr-lock` |
| Warning | `bi bi-exclamation-triangle-fill` | `fi fi-sr-triangle-warning` |
| Info | `bi bi-info-circle-fill` | `fi fi-sr-info` |
| Check / Success | `bi bi-check-circle-fill` | `fi fi-sr-check` |
| Close / Error | `bi bi-x-circle-fill` | `fi fi-sr-cross` |
| Table / List | `bi bi-table` | `fi fi-rr-list` |
| Inbox / Empty | `bi bi-inbox` | `fi fi-sr-inbox-in` |

#### Delete / Destructive Confirmation — SweetAlert2 (`Swal.fire`), NOT native `confirm()`

**Never** use `window.confirm()` or `confirm()` for delete actions. The platform uses **SweetAlert2** with pre-translated text stored in hidden `<span>` elements.

**Admin panel pattern:**
```javascript
const getText = document.getElementById('get-confirm-and-cancel-button-text-for-delete');
Swal.fire({
    title: getText?.dataset.sure || 'Are you sure?',
    text: getText?.dataset.text || 'You will not be able to revert this!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    cancelButtonText: getText?.dataset.cancel || 'Cancel',
    confirmButtonText: getText?.dataset.confirm || 'Yes, delete it!',
    reverseButtons: true,
}).then((result) => {
    if (result.isConfirmed) {
        // proceed with delete
    }
});
```

**Vendor panel pattern:**
```javascript
const getText = document.getElementById('get-sweet-alert-messages');
Swal.fire({
    title: getText?.dataset.areYouSure || 'Are you sure?',
    text: 'This action cannot be undone',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    cancelButtonText: getText?.dataset.cancel || 'Cancel',
    confirmButtonText: getText?.dataset.confirm || 'Confirm',
    reverseButtons: true,
}).then((result) => {
    if (result.isConfirmed) {
        // proceed with delete
    }
});
```

These hidden `<span>` elements are included via layout partials:
- Admin: `layouts/admin/partials/_translator-for-js.blade.php` → `#get-confirm-and-cancel-button-text-for-delete`
- Vendor: `layouts/vendor/partials/_translator-for-js.blade.php` → `#get-sweet-alert-messages`

---

## 💰 Commission & Customer Service Fee Architecture

### Vendor Commission (admin earns from vendor sales)
- Configured in admin panel → Business Settings → "Vendor Commission"
- Type: `percent` or `flat` — stored in `business_settings` key `sales_commission_type`
- Value stored in `business_settings` key `sales_commission`
- Per-vendor override: `sellers.sales_commission_percentage` (always percent, legacy)
- Calculated via `App\Services\CommissionService::calculate(sellerIs, sellerId, orderTotal)`
- Stored on `orders.admin_commission` at order placement
- Stored type on `orders.commission_type`
- Deducted from **vendor** payout — customer never sees this

### Customer Service Fee (customer pays on top of order total)
- Configured in admin panel → Business Settings → "Customer Service Fee"
- Enable/disable: `business_settings` key `customer_service_fee_status`
- Type: `percent` or `flat` — stored in `business_settings` key `customer_service_fee_type`
- Value stored in `business_settings` key `customer_service_fee`
- Calculated via `App\Services\CustomerServiceFeeService::calculate(orderTotal)`
- Stored on `orders.customer_service_fee` and `orders.customer_service_fee_type`
- Added **on top** of `order_amount` at placement — customer sees and pays the full amount
- Goes 100% to **admin** wallet as `commission_earned` on order delivery

### Wallet Settlement Flow (in `OrderManager::getWalletManageOnOrderStatusChange`)
- `$order_amount` = subtotal − discount (vendor portion, no service fee)
- `$commission` = `orders.admin_commission` (vendor commission only)
- `$serviceFee` = `orders.customer_service_fee`
- Admin `commission_earned` += `$commission + $serviceFee`
- Seller `total_earning` += `$order_amount − $commission` (no service fee credit to vendor)
- Transaction `admin_commission` = `$commission + $serviceFee`
- Transaction `seller_amount` = `$order_amount − $commission`

### Important Rules
- **Never** write service fee calculation logic in blade files — always call `CustomerServiceFeeService`
- **Never** credit `$serviceFee` to vendor wallets — it belongs to admin only
- Checkout display uses `$serviceFee` from `CustomerServiceFeeService::calculate()` in `_order-summary.blade.php`

---

*Last updated: March 29, 2026. Commission + Service Fee system implemented.*
