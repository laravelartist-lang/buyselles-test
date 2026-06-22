# BuySelles — Firebase, Push Notifications & OAuth Credentials Request

**Prepared for:** BuySelles Client / Project Owner  
**Prepared by:** Development Team  
**Date:** June 2026  
**Purpose:** Request fresh, complete credentials so **website**, **customer app**, and **vendor app** can support real-time push notifications, Google/Facebook/Apple login, and Firebase OTP.

---

## 1. Executive Summary

We currently have partial credential files, but they are **incomplete, mismatched, or from the wrong Google/Firebase projects**. Because of this, the following features are not working reliably:

- Website push notifications (browser)
- Mobile app push notifications (customer + vendor)
- Google OAuth / social login
- Firebase phone OTP (if enabled)

**What we need from you:** A **fresh, unified setup** under **one Firebase/Google Cloud project** owned by BuySelles, with all apps and platforms registered correctly.

> **Recommendation:** Create **one production Firebase project** (example: `buyselles-production`) and register all platforms inside it. Do not mix credentials from different Google accounts or demo projects.

### Flutter apps — one codebase, platform config files still required

Mobile apps are **Flutter** (one Dart codebase per app — not separate native Android/iOS projects). When you run `flutter build apk` or `flutter build ios`, Flutter produces each platform build from the same code.

Firebase still needs **separate registrations and config files per platform**:

| App | Flutter project | Android config | iOS config |
|-----|-----------------|----------------|------------|
| Customer | `mobile/User app` | `android/app/google-services.json` | `ios/Runner/GoogleService-Info.plist` |
| Vendor | `mobile/vendor_app` | `android/app/google-services.json` | `ios/GoogleService-Info.plist` |

**Confirmed identifiers in code:**

| App | Android package | iOS bundle ID |
|-----|-----------------|---------------|
| Customer | `com.buyselles.app` | `com.buyselles.app` ✅ |
| Vendor | `com.buyselles.vendor` ✅ | `com.buyselles.vendor` ✅ |

In Firebase Console, register **5 apps** in one project: **Web**, Customer Android, Customer iOS, Vendor Android, Vendor iOS.

---

## 2. Current Issues (Why Existing Files Don't Work)

| File | Problem |
|------|---------|
| `firebase-messaging-sw.js` | `apiKey`, `authDomain`, `messagingSenderId`, `appId` are **empty** — web push cannot work |
| `google-services.json` (root) | Only customer Android app (`com.buyselles.app`) — **vendor app not configured** |
| `client_secret_*.json` | Belongs to a **different project** (`gen-lang-client-0008391673`), not BuySelles Firebase (`buyselles-dae62`); also **missing `client_secret`** |
| `client-secret.txt` | Contains a UUID only — **not a valid Google OAuth client secret** |
| Vendor app `google-services.json` | Still contains old **6amTech / SixValley** package names, not BuySelles |
| Vendor app code | Firebase init still has **placeholder values** (`current_key here`, etc.) |

**Conclusion:** We need new credentials from scratch under one correct project.

---

## 3. What You Must Provide (Master Checklist)

Please provide **all items** below. Mark each as ✅ when shared.

### A. Account Access (Preferred)

| # | Item | Why needed |
|---|------|------------|
| A1 | **Firebase Console** access (Owner or Editor role) | To verify apps, Cloud Messaging, Authentication |
| A2 | **Google Cloud Console** access (Owner or Editor) | For OAuth clients, APIs, service accounts |
| A3 | **Google Play Console** access (if Android apps published) | For release SHA-1 fingerprints |
| A4 | **Apple Developer** access (if iOS apps planned) | For APNs, Sign in with Apple, iOS bundle IDs |
| A5 | **Facebook Developer** access (only if Facebook login needed) | For Facebook App ID & Secret |

> If you cannot share console access, send all files/values listed in sections B–G below.

---

### B. Firebase — Backend (Server Push Notifications)

**Where to get:**  
Firebase Console → Project Settings → **Service accounts** → **Generate new private key**

| # | Deliverable | Format |
|---|-------------|--------|
| B1 | **Firebase Service Account JSON** (full file) | `.json` file |

This file is pasted into the admin panel field: **Firebase → Service Account Content**.  
The backend uses it to send push notifications to apps and web.

**Required permissions in JSON:** Firebase Cloud Messaging API must be enabled in Google Cloud.

---

### C. Firebase — Website (Web Push Notifications)

**Where to get:**  
Firebase Console → Project Settings → **Your apps** → Add app → **Web** (`</>`)

| # | Field | Example format |
|---|-------|----------------|
| C1 | `apiKey` | `AIzaSy...` |
| C2 | `authDomain` | `your-project.firebaseapp.com` |
| C3 | `projectId` | `buyselles-production` |
| C4 | `storageBucket` | `your-project.firebasestorage.app` |
| C5 | `messagingSenderId` | `787330592870` (numeric) |
| C6 | `appId` | `1:787330592870:web:xxxxxxxx` |
| C7 | `measurementId` (optional) | `G-XXXXXXXX` |

**Also required for browser push:**

| # | Deliverable | Where |
|---|-------------|-------|
| C8 | **Web Push certificate / VAPID key pair** | Firebase Console → Project Settings → **Cloud Messaging** → Web configuration |

**Website domain to authorize in Firebase:**
- `https://buyselles.com`
- `https://www.buyselles.com` (if used)
- Staging domain (if any), e.g. `https://staging.buyselles.com`

---

### D. Firebase — Mobile Apps (Customer + Vendor)

We need **separate Firebase app registrations** for each platform.

#### Please confirm final app identifiers first:

| App | Platform | Current package/bundle in code | Suggested final ID |
|-----|----------|-------------------------------|-------------------|
| **Customer App** | Android | `com.buyselles.app` ✅ | `com.buyselles.app` ✅ |
| **Customer App** | iOS | `com.buyselles.app` ✅ | `com.buyselles.app` ✅ |
| **Vendor App** | Android | `com.buyselles.vendor` ✅ | `com.buyselles.vendor` ✅ |
| **Vendor App** | iOS | `com.buyselles.vendor` ✅ | `com.buyselles.vendor` ✅ |

> ⚠️ Old 6amTech/SixValley Firebase config files must still be replaced with new BuySelles project files. App identifiers in code are now unified (see table above).

#### Files to provide per app:

| # | App | File |
|---|-----|------|
| D1 | Customer Android | `google-services.json` |
| D2 | Customer iOS | `GoogleService-Info.plist` |
| D3 | Vendor Android | `google-services.json` |
| D4 | Vendor iOS | `GoogleService-Info.plist` |

**Where to get:** Firebase Console → Project Settings → Your apps → Add Android / Add iOS

---

### E. Android SHA Fingerprints (Critical for Google Login + FCM)

For **each Android app** (Customer + Vendor), provide **both**:

| # | Keystore type | Required fingerprints |
|---|---------------|---------------------|
| E1 | **Debug keystore** | SHA-1 and SHA-256 |
| E2 | **Release/Upload keystore** (Play Store) | SHA-1 and SHA-256 |

**How to generate (run on your machine):**
```bash
keytool -list -v -keystore /path/to/keystore.jks -alias your_alias
```

**Where to add in Firebase:**  
Firebase Console → Project Settings → Your Android app → **Add fingerprint**

Without correct SHA fingerprints:
- Google Sign-In on Android **will fail**
- Firebase Auth on Android may **not work**

---

### F. Google OAuth — Website Social Login

**Where to get:**  
Google Cloud Console → APIs & Services → **Credentials** → Create OAuth client → **Web application**

Use the **same Google Cloud project** linked to your Firebase project.

| # | Deliverable | Notes |
|---|-------------|-------|
| F1 | **Web Client ID** | For admin panel: Social Login → Google → Client ID |
| F2 | **Web Client Secret** | For admin panel: Social Login → Google → Client Secret |
| F3 | Full OAuth JSON (optional) | Must include both `client_id` and `client_secret` |

#### Authorized JavaScript origins (add all that apply):
```
https://buyselles.com
https://www.buyselles.com
```

#### Authorized redirect URIs (required — copy exactly):
```
https://buyselles.com/customer/auth/login/google/callback
```

> If you use `www` or a staging domain, add those variants too.

#### Google Cloud APIs to enable:
- Google+ API / Google Identity Services (as applicable)
- Firebase Authentication API
- Firebase Cloud Messaging API
- Google People API (if profile data needed)

---

### G. Google OAuth — Mobile Apps

These are usually **auto-created** when you register Android/iOS apps in Firebase with correct SHA/bundle IDs.

Still, please confirm/provide:

| # | Platform | Deliverable |
|---|----------|-------------|
| G1 | Android (Customer) | OAuth Client ID (Android type) |
| G2 | Android (Vendor) | OAuth Client ID (Android type) |
| G3 | iOS (Customer) | OAuth Client ID (iOS type) + reversed client ID |
| G4 | iOS (Vendor) | OAuth Client ID (iOS type) + reversed client ID |

---

### H. Facebook Login (Only if you want Facebook sign-in)

**Where to get:** [Facebook Developers](https://developers.facebook.com/)

| # | Deliverable |
|---|-------------|
| H1 | Facebook App ID |
| H2 | Facebook App Secret |
| H3 | OAuth redirect URI configured in Facebook app |

**Redirect URI for website:**
```
https://buyselles.com/customer/auth/login/facebook/callback
```

---

### I. Apple Sign In (Only if you want Apple login on website/iOS)

**Where to get:** Apple Developer → Certificates, Identifiers & Profiles

| # | Deliverable | Admin panel field |
|---|-------------|-------------------|
| I1 | **Services ID** (Client ID) | Client ID |
| I2 | **Team ID** | Team ID |
| I3 | **Key ID** | Key ID |
| I4 | **AuthKey_XXXXXXXX.p8** file | Upload in admin panel |

**Return URL / Redirect URI for website:**
```
https://buyselles.com/customer/auth/login/apple/callback
```

---

### J. iOS Push Notifications (APNs) — For real-time alerts on iPhone

**Where to add:** Firebase Console → Project Settings → Cloud Messaging → **Apple app configuration**

Provide **one** of the following:

| # | Option | File |
|---|--------|------|
| J1 | APNs Authentication Key (recommended) | `.p8` key + Key ID + Team ID |
| J2 | APNs SSL Certificate (legacy) | `.p12` certificate |

Required for each iOS app (Customer + Vendor) if both are on App Store.

---

## 4. Where We Will Configure These (After You Provide)

| Credential | Configured in |
|------------|---------------|
| Service Account JSON + Web Firebase config | Admin Panel → **3rd Party → Firebase Configuration** |
| Google/Facebook/Apple OAuth | Admin Panel → **3rd Party → Social Login** |
| `firebase-messaging-sw.js` | Auto-generated on server after Firebase web config is saved |
| `google-services.json` | Customer/Vendor Flutter apps → `android/app/` |
| `GoogleService-Info.plist` | Customer/Vendor Flutter apps → `ios/Runner/` |

---

## 5. Step-by-Step Setup Guide for Client (Firebase Console)

### Step 1 — Create or select Firebase project
1. Go to [https://console.firebase.google.com](https://console.firebase.google.com)
2. Create project: `buyselles-production` (or use existing `buyselles-dae62` and fix it)
3. Enable **Google Analytics** (optional)

### Step 2 — Enable Authentication methods
Firebase Console → **Build → Authentication → Sign-in method**
- Enable **Google**
- Enable **Phone** (if OTP login needed)
- Enable **Apple** (if needed)
- Enable **Facebook** (if needed — requires Facebook app setup)

### Step 3 — Enable Cloud Messaging
Firebase Console → **Build → Cloud Messaging**  
Ensure FCM is active (enabled by default on new projects).

### Step 4 — Register all apps
Add these apps under **Project Settings → Your apps**:

1. **Web** — for buyselles.com
2. **Android** — Customer (`com.buyselles.app` or confirmed ID)
3. **Android** — Vendor (`com.buyselles.vendor`)
4. **iOS** — Customer (confirmed bundle ID)
5. **iOS** — Vendor (confirmed bundle ID)

### Step 5 — Download config files
Download and send us:
- Service Account JSON (Section B)
- Web config values (Section C)
- All `google-services.json` files (Section D)
- All `GoogleService-Info.plist` files (Section D)

### Step 6 — Google Cloud OAuth
1. Go to [https://console.cloud.google.com](https://console.cloud.google.com)
2. Select the **same project** as Firebase
3. Create **Web OAuth Client** with redirect URIs from Section F
4. Send Client ID + Client Secret

### Step 7 — Add SHA fingerprints
For each Android app, add debug + release SHA-1 and SHA-256 in Firebase app settings.

### Step 8 — iOS APNs (if applicable)
Upload APNs `.p8` key in Firebase Cloud Messaging settings for each iOS app.

---

## 6. Delivery Format — How to Send Us the Credentials

Please send a **single ZIP folder** named:

```
buyselles-firebase-oauth-credentials.zip
```

With this structure:

```
buyselles-firebase-oauth-credentials/
├── README.txt                          (your contact + project name)
├── firebase-service-account.json       (Section B)
├── web-firebase-config.txt             (Section C values)
├── web-push-vapid-key.txt              (Section C8)
├── oauth-web-google.json               (Section F - with client_id + client_secret)
├── oauth-facebook.txt                  (Section H - if applicable)
├── apple-signin/                       (Section I - if applicable)
│   ├── AuthKey_XXXXXXXX.p8
│   ├── team-id.txt
│   ├── key-id.txt
│   └── services-id.txt
├── android-sha-fingerprints.txt        (Section E - all SHA-1/SHA-256)
├── customer-app/
│   ├── android/google-services.json
│   └── ios/GoogleService-Info.plist
├── vendor-app/
│   ├── android/google-services.json
│   └── ios/GoogleService-Info.plist
└── apns/                               (Section J - if applicable)
    └── AuthKey_XXXXXXXX.p8
```

### `web-firebase-config.txt` template:
```
apiKey=
authDomain=
projectId=
storageBucket=
messagingSenderId=
appId=
measurementId=
```

### `android-sha-fingerprints.txt` template:
```
CUSTOMER APP (com.buyselles.app)
Debug SHA-1:
Debug SHA-256:
Release SHA-1:
Release SHA-256:

VENDOR APP (com.buyselles.vendor)
Debug SHA-1:
Debug SHA-256:
Release SHA-1:
Release SHA-256:
```

---

## 7. Security Notes

- Share credentials via a **secure channel** (encrypted email, password-protected ZIP, or secrets manager).
- Do **not** commit credential files to public Git repositories.
- After we configure production, rotate any credentials that were previously shared insecurely.
- Use a Google/Firebase account **owned by BuySelles**, not a freelancer or third-party demo account.

---

## 8. Quick Reference — Admin Panel URLs (After Deployment)

| Feature | Admin path |
|---------|------------|
| Firebase config | Admin → 3rd Party → **Firebase Configuration** |
| Social login | Admin → 3rd Party → **Social Login** |
| Push notification messages | Admin → **Notification** settings |

---

## 9. Client Confirmation Required Before We Proceed

Please reply with:

1. ✅ Final **Customer App** Android package name: `com.buyselles.app`
2. ✅ Final **Customer App** iOS bundle ID: `com.buyselles.app`
3. ✅ Final **Vendor App** Android package name: `com.buyselles.vendor`
4. ✅ Final **Vendor App** iOS bundle ID: `com.buyselles.vendor`
5. ✅ Production domain: `https://buyselles.com` (confirm)
6. ✅ Which login methods do you need?
   - [ ] Google
   - [ ] Facebook
   - [ ] Apple
   - [ ] Phone OTP (Firebase)
7. ✅ Will you share console access OR send ZIP with all files?

---

## 10. Support Contact

Once you provide the above, our team will:
1. Configure the Laravel admin panel (Firebase + OAuth)
2. Update website service worker for web push
3. Integrate correct `google-services.json` / `GoogleService-Info.plist` in both mobile apps
4. Test push notifications on website, customer app, and vendor app
5. Test Google/Facebook/Apple login end-to-end

**Estimated setup time after receiving complete credentials:** 1–2 business days.

---

*Document version: 1.0 — BuySelles Platform*
