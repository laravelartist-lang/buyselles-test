#!/usr/bin/env bash
# Static iOS release checks for user_app (runs on Linux/macOS CI without a device).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "==> Verifying user_app iOS release configuration"

PBXPROJ="ios/Runner.xcodeproj/project.pbxproj"
INFO_PLIST="ios/Runner/Info.plist"
ENTITLEMENTS="ios/Runner/Runner.entitlements"
PUBSPEC="pubspec.yaml"

if grep -q 'CURRENT_PROJECT_VERSION = $(FLUTTER_BUILD_NUMBER);' "$PBXPROJ"; then
  echo "::error::Invalid Xcode project: CURRENT_PROJECT_VERSION must be quoted as \"\$(FLUTTER_BUILD_NUMBER)\""
  exit 1
fi

if ! grep -q 'CURRENT_PROJECT_VERSION = "$(FLUTTER_BUILD_NUMBER)";' "$PBXPROJ"; then
  echo "::error::Xcode project is not synced with pubspec.yaml (missing FLUTTER_BUILD_NUMBER reference)"
  exit 1
fi

if ! grep -q 'MARKETING_VERSION = "$(FLUTTER_BUILD_NAME)";' "$PBXPROJ"; then
  echo "::error::Xcode project is not synced with pubspec.yaml (missing FLUTTER_BUILD_NAME reference)"
  exit 1
fi

required_plist_keys=(
  NSLocationWhenInUseUsageDescription
  NSLocationAlwaysAndWhenInUseUsageDescription
  NSLocationAlwaysUsageDescription
  NSCameraUsageDescription
  NSPhotoLibraryUsageDescription
  NSBluetoothAlwaysUsageDescription
)

for key in "${required_plist_keys[@]}"; do
  if ! grep -q "<key>${key}</key>" "$INFO_PLIST"; then
    echo "::error::Missing required Info.plist key: ${key}"
    exit 1
  fi
done

if grep -q '<key>NSAllowsArbitraryLoads</key>' "$INFO_PLIST"; then
  echo "::error::Info.plist uses NSAllowsArbitraryLoads=true (App Store risk). Use domain exceptions instead."
  exit 1
fi

if ! grep -q 'com.apple.developer.applesignin' "$ENTITLEMENTS"; then
  echo "::error::Runner.entitlements missing Sign in with Apple capability"
  exit 1
fi

if ! grep -q '<string>production</string>' "$ENTITLEMENTS"; then
  echo "::warning::aps-environment is not production in Runner.entitlements (required for App Store archives)"
fi

pubspec_version="$(grep '^version:' "$PUBSPEC" | awk '{print $2}')"
build_name="${pubspec_version%%+*}"
build_number="${pubspec_version#*+}"

echo "pubspec version: ${pubspec_version} (name=${build_name}, number=${build_number})"

if command -v flutter >/dev/null 2>&1; then
  flutter pub get >/dev/null
  if [ -f ios/Flutter/Generated.xcconfig ] && [ "$(uname -s)" = "Darwin" ]; then
    generated_number="$(grep '^FLUTTER_BUILD_NUMBER=' ios/Flutter/Generated.xcconfig | cut -d= -f2)"
    generated_name="$(grep '^FLUTTER_BUILD_NAME=' ios/Flutter/Generated.xcconfig | cut -d= -f2)"
    if [ "${generated_number}" != "${build_number}" ] || [ "${generated_name}" != "${build_name}" ]; then
      echo "::error::Generated.xcconfig does not match pubspec.yaml"
      exit 1
    fi
    echo "Generated.xcconfig matches pubspec.yaml"
  elif [ -f ios/Flutter/Generated.xcconfig ]; then
    echo "Skipping Generated.xcconfig sync check on non-macOS (macOS CI compile job validates this)"
  fi
fi

if [ -f ios/Runner/Assets.xcassets/AppIcon.appiconset/1024.png ]; then
  echo "App Store icon file present: 1024.png"
else
  echo "::warning::1024.png not found in repo (may be generated locally or in CI checkout)"
fi

echo "iOS release static verification passed."
