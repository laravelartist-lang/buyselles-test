#!/usr/bin/env bash
# Validate iOS plist files the same way Xcode does (fail before expensive archive).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PLIST_PATHS=(
  ios/Runner/Info.plist
  ios/Runner/Runner.entitlements
  ios/Flutter/AppFrameworkInfo.plist
  ios/GoogleService-Info.plist
  ios/Runner/GoogleService-Info.plist
)

if [ -f ios/ExportOptions.plist ]; then
  PLIST_PATHS+=(ios/ExportOptions.plist)
fi

echo "==> Validating iOS plist files"

for path in "${PLIST_PATHS[@]}"; do
  if [ ! -f "$path" ]; then
    echo "::error::Missing plist: ${path}"
    exit 1
  fi

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
