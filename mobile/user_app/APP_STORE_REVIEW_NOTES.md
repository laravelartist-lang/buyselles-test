# Buyselles Customer App — App Store Review Notes

**Version:** 1.0.0 (build **4**)  
**Bundle ID:** `com.buyselles.app`

Use this in **App Review Information** when submitting to App Store Connect.

---

## Demo account

- **Username:** `[FILL IN — customer email or phone]`
- **Password:** `[FILL IN]`

Ensure this account can:

- Browse products and add items to cart
- Complete checkout for **physical** goods (COD or external gateway)
- Complete checkout for **digital** goods via **App Store** (IAP) on iOS
- Use **Sign in with Apple** if other social logins are enabled
- **Delete account** from Profile

---

## What this app is

Buyselles is a **multi-vendor e-commerce marketplace** (consumer app). Vendors sell physical and digital products. This is not a P2P payments or gambling app.

---

## Sign in with Apple (Guideline 4.8)

When admin enables Apple login (`customer_login.social_media_login_options.apple`), the app shows **Continue with Apple** on iOS alongside Google/Facebook when those are enabled.

Apple login sends the **identity token** to our backend for verification (native Sign in with Apple flow).

---

## Digital products & In-App Purchase (Guideline 3.1)

On **iOS**, when the cart contains **only digital products** and admin enables **iOS IAP** (`ios_iap_status`):

- External payment gateways (Stripe, PayPal, etc.) are **hidden**
- Checkout shows **App Store** payment
- Purchase is completed via StoreKit; the server verifies the transaction before fulfilling the order

**App Store Connect:** consumable IAP products must exist for each digital SKU (default ID pattern: `com.buyselles.app.digital.{product_id}` unless overridden in admin).

**Review steps — digital IAP checkout:**

1. Log in with demo account
2. Add a **digital-only** product to cart (no physical items)
3. Proceed to checkout → payment sheet shows **App Store** (not external gateways)
4. Complete sandbox purchase → order is placed

---

## Physical goods & wallet

- **Physical products** and mixed carts may use COD, wallet balance, or external payment gateways (allowed for real-world goods/services).
- **Wallet add fund** uses external gateways — wallet credit is store balance for marketplace purchases, not withdrawn as cash.

---

## Account deletion (Guideline 5.1.1)

Profile → **Delete Account** permanently deletes the customer account via API (`/api/v1/customer/account-delete`).

---

## Privacy

Privacy policy is available in-app: **More → Privacy Policy** (CMS page slug `privacy-policy`).

Location, camera, photo library, and Bluetooth purpose strings are in `ios/Runner/Info.plist` (delivery address, profile photo, thermal printer).

---

## Pre-submission checklist (from app-store-review skill audit)

### Fixed in repo

- [x] Xcode build number synced with Flutter (`CURRENT_PROJECT_VERSION` / `MARKETING_VERSION` use `$(FLUTTER_BUILD_NUMBER)` / `$(FLUTTER_BUILD_NAME)`)
- [x] Location purpose strings present (ITMS-90683)
- [x] Sign in with Apple entitlement + iOS-only UI
- [x] Account deletion in profile
- [x] IAP checkout path for digital-only carts on iOS (main checkout flow)
- [x] Privacy policy accessible in app
- [x] External payment hidden on iOS for digital-only checkout and order due payment
- [x] Wallet add-fund via external gateways disabled on iOS
- [x] ATS tightened (`NSAllowsArbitraryLoadsInWebContent` + domain exception for buyselles.com)
- [x] Push entitlement set to `production` for App Store archives

### Required before / during submission (manual)

- [ ] Enable **In-App Purchase** capability on App ID `com.buyselles.app` in Apple Developer
- [ ] Create IAP products in App Store Connect matching backend `apple_product_id` values
- [ ] Enable **Sign in with Apple** on App ID + App Store Connect
- [ ] Set `ios_iap_status` ON in admin for production review build
- [ ] Enable Apple social login in admin if Google/Facebook are enabled
- [ ] Sandbox Apple ID for reviewer to test IAP
- [ ] Fill demo account credentials above

### Notes for reviewer

| Topic | Notes |
|-------|-------|
| **Digital order due payment on iOS** | External gateways are blocked; wallet balance may be used for unpaid digital order adjustments |
| **Wallet add fund on iOS** | Hidden — wallet credit on iOS comes from refunds/admin bonuses, not external top-up |

---

## Appeal / clarification snippets

**Guideline 3.1 (digital goods):**

> Digital products on iOS are sold exclusively through Apple In-App Purchase. External payment methods are shown only for physical goods, mixed carts with physical items, or regions/features where IAP is disabled by admin configuration.

**Guideline 4.8 (Sign in with Apple):**

> Sign in with Apple is offered on iOS whenever other third-party login options (Google, Facebook) are enabled in our admin configuration.

---

## Skill used for this audit

Project skill: `.cursor/skills/app-store-review/` (from [safaiyeh/app-store-review-skill](https://github.com/safaiyeh/app-store-review-skill)) + Flutter overlay `6-flutter.md`.

This is an **AI guideline checklist**, not an automated test runner. Re-run the review in Cursor before each submission.
