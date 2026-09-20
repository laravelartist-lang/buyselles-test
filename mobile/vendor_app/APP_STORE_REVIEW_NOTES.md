# Buyselles Vendor — App Store Review Notes

**Version:** 1.0.0 (build **6**) — marketing version unchanged; build number incremented for resubmission.

Use this text in **App Review Information** when submitting to App Store Connect.

---

## Demo account

- **Username:** `[FILL IN — vendor email or phone]`
- **Password:** `[FILL IN]`

Ensure this account:

- Is approved/active (not pending seller review)
- Has a positive **withdrawable balance** in Wallet
- Has at least one **payment / withdraw method** saved (My Shop → Payment options)
- Has **POS enabled** if you want reviewers to test POS (`pos_active` in admin + seller profile)
- Has **Wallet Transfer to Customer** module enabled (admin vendor permissions)

---

## What this app is

Buyselles Vendor is a **B2B seller dashboard** for marketplace vendors. It is not a consumer P2P payments app.

---

## Wallet feature (important for Guideline 5.6)

### Vendor withdraw (payout)

**Wallet → Withdraw** sends a payout request from vendor earnings to the vendor's configured bank/payment method. This is **not** peer-to-peer transfer between consumers.

### Wallet transfer to customer (store credit)

**Menu → Wallet Transfer to Customer** (also linked from Wallet screen) lets an approved vendor send **their own shop earnings** to a **registered customer's wallet** as store credit for future purchases on the marketplace.

- Vendor balance decreases; customer wallet balance increases
- Customer receives push/email notification (same as web vendor panel)
- Failed transfers show the **server error message** — never a fake success toast
- Feature is hidden when admin disables `vendor_wallet_transfer` for that vendor

### Steps to test withdraw

1. Log in with the demo vendor account
2. Open the **Menu** (bottom navigation → More / menu icon)
3. Tap **Wallet**
4. Tap **Withdraw**
5. Select a saved payment method (or add one via **My Shop → Payment options** first)
6. Enter amount (must be greater than 1 and not exceed available balance)
7. Submit — a pending withdraw request appears under transactions

If withdraw fails (insufficient balance, invalid method), the app shows an **error message** and does **not** show success.

### Steps to test wallet transfer to customer

1. Log in with demo vendor account (with available balance)
2. **Menu → Wallet Transfer to Customer** (or **Wallet** → transfer button)
3. Search for a registered customer by name, email, or phone
4. Enter amount and optional reference note
5. Submit — vendor balance decreases; transfer appears in history

Test failure: enter amount greater than balance → error message, no success toast.

---

## POS feature

Point-of-sale is shown only when:

- Admin enables seller POS (`pos_active` in system config), **and**
- The seller account has POS enabled

POS supports cash/card; customer wallet payment appears only when `wallet_status` is enabled in system config.

---

## No hidden features for review

- We do **not** hide or enable features specifically for App Review
- Production API config is used during review
- **Do not** enable vendor-app maintenance mode during review
- Remote config toggles (AI, digital products, wallet-in-POS) reflect production admin settings only

---

## Appeal note (Guideline 5.6)

If rejected for "hidden features" or misleading behavior:

> A wallet withdraw bug previously showed a success message when the server rejected the request. This has been fixed — failed wallet actions now display the server error. Wallet transfer to customer sends vendor shop earnings to registered customer wallets (B2C store credit), matching the vendor web panel. There is no review-mode bypass or intentionally hidden functionality.

---

## Pre-submission checklist

- [ ] Demo account funded with withdrawable balance
- [ ] Withdraw method configured in admin panel
- [ ] Vendor maintenance mode **off** for vendor app
- [ ] Test withdraw failure (amount > balance) → shows error, not success
- [ ] Test valid withdraw → success + pending transaction
- [ ] Test wallet with no payment methods → setup warning shown
- [ ] Test wallet transfer to customer (success + insufficient balance failure)
- [ ] POS menu visible only when POS enabled for seller
- [ ] Wallet transfer menu hidden when `vendor_wallet_transfer` module disabled
