#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"${ROOT}/scripts/verify-vendor-android-setup.sh"

echo
echo "Building signed Play Store AAB..."
cd "${ROOT}/mobile/vendor_app"
flutter pub get
flutter build appbundle --release

AAB="${ROOT}/mobile/vendor_app/build/app/outputs/bundle/release/app-release.aab"
if [[ -f "${AAB}" ]]; then
  echo
  echo "Build complete:"
  echo "  ${AAB}"
  echo
  echo "Upload this .aab file in Google Play Console."
else
  echo "Build finished but AAB not found at expected path." >&2
  exit 1
fi
