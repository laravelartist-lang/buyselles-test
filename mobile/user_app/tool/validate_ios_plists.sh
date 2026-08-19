#!/usr/bin/env bash
# Validate iOS plist files the same way Xcode does (fail before expensive archive).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

REQUIRE_FIREBASE=0
for arg in "$@"; do
  case "$arg" in
    --require-firebase) REQUIRE_FIREBASE=1 ;;
    --help|-h)
      echo "Usage: validate_ios_plists.sh [--require-firebase]"
      echo "  --require-firebase  Fail if gitignored Firebase plists are missing (CI after secret decode)"
      exit 0
      ;;
  esac
done

PLIST_PATHS=(
  ios/Runner/Info.plist
  ios/Runner/Runner.entitlements
  ios/Flutter/AppFrameworkInfo.plist
)

FIREBASE_PATHS=(
  ios/GoogleService-Info.plist
  ios/Runner/GoogleService-Info.plist
)

for path in "${FIREBASE_PATHS[@]}"; do
  if [ -f "$path" ]; then
    PLIST_PATHS+=("$path")
  elif [ "$REQUIRE_FIREBASE" -eq 1 ]; then
    echo "::error::Missing Firebase plist: ${path} (decode GOOGLE_SERVICE_INFO_PLIST_BASE64 first)"
    exit 1
  else
    echo "Skipping optional Firebase plist (gitignored / not injected yet): ${path}"
  fi
done

if [ -f ios/ExportOptions.plist ]; then
  PLIST_PATHS+=(ios/ExportOptions.plist)
fi

echo "==> Validating iOS plist files"

for path in "${PLIST_PATHS[@]}"; do
  if grep -Eq '<(true|false)>[[:space:]]*</(true|false)>' "$path"; then
    echo "::error::Invalid plist boolean syntax in ${path}. Use <true/> or <false/> (not <true></true>)."
    exit 1
  fi
done

if command -v plutil >/dev/null 2>&1; then
  for path in "${PLIST_PATHS[@]}"; do
    if ! plutil -lint "$path" >/dev/null; then
      echo "::error::plutil rejected ${path}"
      plutil -lint "$path" || true
      exit 1
    fi
    echo "plutil OK: ${path}"
  done
else
  for path in "${PLIST_PATHS[@]}"; do
    python3 - "$path" <<'PY'
import plistlib
import sys

path = sys.argv[1]
with open(path, "rb") as handle:
    plistlib.load(handle)
print(f"plistlib OK: {path}")
PY
  done
fi

echo "All iOS plist files validated."
