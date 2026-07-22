# BuySelles — iOS Signing: Mac Steps Only

**Prepared for:** BuySelles Client / Project Owner  
**Prepared by:** Development Team  
**Purpose:** Complete the **remaining Mac-only steps** so we can build and publish the BuySelles Customer App on iOS (TestFlight / App Store).

---

## Already completed ✅

The following setup is **already done** — no action needed:

| Item | Status |
|------|--------|
| Apple Developer Program enrollment | ✅ Done |
| App ID registered: `com.buyselles.app` | ✅ Done |
| Capabilities enabled (Push, Sign In with Apple, Associated Domains) | ✅ Done |
| App created in App Store Connect | ✅ Done |
| Team ID configured in project: `JJJTN557FB` | ✅ Done |

**What remains:** Apple requires a **Distribution certificate** to be created and exported on a **Mac** using **Keychain Access**. This cannot be done on Windows or Linux.

---

## App details (for reference)

| Item | Value |
|------|--------|
| App name | BuySelles |
| Bundle ID | `com.buyselles.app` |
| Team ID | `JJJTN557FB` |

---

## What you need

- A **Mac** (MacBook, Mac mini, iMac, or rented cloud Mac)
- Your **Apple ID** (same account used for Developer Program)
- About **20–30 minutes**

---

## Step 1 — Create Certificate Signing Request (CSR) on Mac

The CSR links your certificate to this specific Mac.

1. On the Mac, open **Keychain Access**  
   *(Spotlight → type `Keychain Access`)*
2. Menu bar: **Keychain Access** → **Certificate Assistant** → **Request a Certificate From a Certificate Authority…**
3. Fill in:
   - **User Email Address:** your Apple ID email
   - **Common Name:** `BuySelles Distribution`
   - **CA Email Address:** leave **empty**
   - ✅ **Saved to disk**
   - ✅ **Let me specify key pair information** *(if shown)* → **Continue**
4. Key size: **2048 bits**, Algorithm: **RSA** → **Continue**
5. Save the file as:  
   `CertificateSigningRequest.certSigningRequest`

---

## Step 2 — Create Apple Distribution Certificate

*Browser step, but certificate must be installed on the same Mac from Step 1.*

1. Open [Apple Developer → Certificates](https://developer.apple.com/account/resources/certificates/list)
2. Click **+**
3. Select **Apple Distribution**  
   *(For App Store and TestFlight)*
4. Click **Continue**
5. Upload the **CSR file** from Step 1
6. Click **Continue** → **Download** the `.cer` file
7. On the Mac: **double-click** the `.cer` file to install it in Keychain

**Verify:** Open Keychain Access → **My Certificates**. You should see:

`Apple Distribution: Your Name (JJJTN557FB)`

---

## Step 3 — Export Certificate as `.p12` (Mac only)

This file is required for automated iOS builds (GitHub Actions).

1. Open **Keychain Access**
2. Select **My Certificates** in the left sidebar
3. Find **Apple Distribution: … (JJJTN557FB)**
4. Click the **arrow** to expand — a **private key** must appear underneath  
   ⚠️ If there is **no private key**, go back to Step 1 and create a new CSR **on this same Mac**, then repeat Steps 2–3.
5. Right-click the **Apple Distribution** certificate → **Export "Apple Distribution…"**
6. Format: **Personal Information Exchange (.p12)**
7. Save as: `buyselles-distribution.p12`
8. Set a **strong password** when prompted  
   **Write down this password** — we need it separately.

> Treat the `.p12` file like a password. Do not share it publicly.

---

## Step 4 — Download Provisioning Profile (browser, after Step 2)

*Do this after the Distribution certificate exists (Step 2).*

1. Open [Apple Developer → Profiles](https://developer.apple.com/account/resources/profiles/list)
2. Click **+**
3. Select **App Store Connect** (under Distribution)
4. **App ID:** `com.buyselles.app`
5. **Certificate:** select your new **Apple Distribution** certificate
6. **Profile name:** `BuySelles User App AppStore`
7. Click **Generate** → **Download**
8. Save as: `Buyselles_AppStore.mobileprovision`

---

## Step 5 — Send files to the development team

Send the following via a **secure channel** (encrypted zip, password manager vault — **not plain email**):

### Required

| # | File / info | Notes |
|---|-------------|--------|
| 1 | `buyselles-distribution.p12` | From Step 3 |
| 2 | `.p12` password | From Step 3 |
| 3 | `Buyselles_AppStore.mobileprovision` | From Step 4 |

### Already known (no need to send)

| Item | Value |
|------|--------|
| Team ID | `JJJTN557FB` |
| Bundle ID | `com.buyselles.app` |

When ready, reply to the dev team:

> **"iOS signing assets ready"**

We will then enable **signed iOS builds** and **TestFlight** upload.

---

## Optional — Add secrets directly to GitHub

If you prefer to add files yourself instead of sending them:

**GitHub repo → Settings → Secrets and variables → Actions → New repository secret**

On Mac, run:

```bash
base64 -i buyselles-distribution.p12 | pbcopy
# Paste into secret IOS_DISTRIBUTION_CERTIFICATE_BASE64

base64 -i Buyselles_AppStore.mobileprovision | pbcopy
# Paste into secret IOS_PROVISIONING_PROFILE_BASE64
```

| Secret name | Value |
|-------------|--------|
| `IOS_TEAM_ID` | `JJJTN557FB` |
| `IOS_DISTRIBUTION_CERTIFICATE_BASE64` | Base64 of `.p12` |
| `IOS_DISTRIBUTION_CERTIFICATE_PASSWORD` | `.p12` password |
| `IOS_PROVISIONING_PROFILE_BASE64` | Base64 of `.mobileprovision` |
| `IOS_KEYCHAIN_PASSWORD` | Any random string, e.g. `ci-keychain-buyselles-2026` |

---

## Troubleshooting (Mac)

### No private key under the certificate

The CSR **must** be created on the **same Mac** where you install the certificate. Start again from Step 1 on that Mac.

### "Certificate limit reached"

Revoke an unused old Distribution certificate at [Apple Developer → Certificates](https://developer.apple.com/account/resources/certificates/list), then create a new one.

### Mac export asks for login password

Enter your **Mac login password** to allow Keychain export. This is normal.

### No Mac available

Options:
- Borrow a Mac for ~30 minutes
- Rent a cloud Mac (e.g. MacinCloud, MacStadium)
- Ask the dev team to guide you through a one-time screen-share session

---

## Checklist (Mac work only)

- [ ] Step 1: CSR created on Mac
- [ ] Step 2: Apple Distribution certificate downloaded and installed in Keychain
- [ ] Step 3: `.p12` exported with password saved
- [ ] Step 4: App Store provisioning profile downloaded
- [ ] Step 5: Files sent securely to dev team (or GitHub Secrets added)

---

*Document version: 1.1 — Mac steps only. App Store Connect and App ID setup already complete.*
