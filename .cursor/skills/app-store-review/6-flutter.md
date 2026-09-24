# Flutter apps (Buyselles user_app)

The upstream skill lists Swift, Objective-C, React Native, and Expo. **Flutter is not listed**, but the same Apple guidelines apply because Flutter ships a native iOS shell plus Dart business logic.

## Where to look in this repo

| Area | Path |
|------|------|
| App root | `mobile/user_app/` |
| iOS native | `mobile/user_app/ios/Runner/Info.plist`, `Runner.entitlements`, `Runner.xcodeproj/project.pbxproj` |
| Version / build | `mobile/user_app/pubspec.yaml` (`version: x.y.z+build`) |
| Sign in with Apple | `lib/features/auth/widgets/social_login_widget.dart` |
| Digital checkout IAP | `lib/features/iap/`, `lib/features/checkout/widgets/payment_method_bottom_sheet_widget.dart` |
| Account deletion | `lib/features/profile/widgets/delete_account_bottom_sheet_widget.dart` |
| Privacy policy UI | `lib/features/more/screens/more_screen_view.dart` |
| Wallet external pay | `lib/features/wallet/widgets/add_fund_dialogue_widget.dart` |

## Dart equivalents of common rejection patterns

```dart
// 🔴 External payment for digital goods on iOS (Guideline 3.1)
if (Platform.isIOS && onlyDigital) {
  launchStripeCheckout(); // Use in_app_purchase + StoreKit instead
}

// 🟡 Sign in with Apple required when offering other third-party login (4.8)
if (googleEnabled || facebookEnabled) {
  // Must also offer Sign in with Apple on iOS
}

// 🟡 Hardcoded secrets in Dart
const apiKey = 'sk_live_xxxxx'; // Move to server-side

// 🟡 Missing purpose strings — check Info.plist, not Dart
// NSLocation*, NSCamera*, NSPhoto*, NSBluetooth*, NSUserTracking*
```

## Flutter-specific checks

1. **Build number sync** — `pubspec.yaml` `+N` must flow to Xcode via `CURRENT_PROJECT_VERSION = "$(FLUTTER_BUILD_NUMBER)"` (quoted).
2. **Platform gating** — Use `Platform.isIOS` or `defaultTargetPlatform == TargetPlatform.iOS` for iOS-only rules (Apple login, IAP).
3. **IAP plugin** — `in_app_purchase` requires In-App Purchase capability on the App ID in Apple Developer + matching products in App Store Connect.
4. **Entitlements** — `Runner.entitlements` must include `com.apple.developer.applesignin` when Sign in with Apple is used.
5. **ATS** — `NSAllowsArbitraryLoads` in Info.plist is a common review question; prefer domain exceptions.
6. **Push** — `aps-environment` in entitlements should be `production` for App Store archives (Xcode/provisioning profile).

## Buyselles user_app review focus

- Marketplace with **physical + digital** products: external gateways OK for physical; **digital-only carts on iOS must use IAP** when `ios_iap_status` is enabled.
- **Wallet add-fund** via external gateways may trigger 3.1.1 if treated as digital currency — document or restrict on iOS.
- **Retry payment** on unpaid digital orders (`order_payment_bottomsheet_widget.dart`) must not bypass IAP on iOS.
