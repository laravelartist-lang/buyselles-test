#!/usr/bin/env bash
# Print base64 for GitHub secret GOOGLE_SERVICE_INFO_PLIST_BASE64 from a valid plist file.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLIST="${1:-$ROOT/ios/GoogleService-Info.plist}"

if [ ! -f "$PLIST" ]; then
  echo "Usage: $(basename "$0") [path/to/GoogleService-Info.plist]" >&2
  exit 1
fi

if [ ! -s "$PLIST" ]; then
  echo "Plist file is missing or empty: $PLIST" >&2
  exit 1
fi

python3 - "$PLIST" <<'PY'
import plistlib
import sys

with open(sys.argv[1], "rb") as handle:
    plistlib.load(handle)
print(f"plist OK: {sys.argv[1]}")
PY

echo "==> Copy this entire single line into GitHub secret GOOGLE_SERVICE_INFO_PLIST_BASE64:"
echo
if base64 --help 2>&1 | grep -q '\-w'; then
  base64 -w 0 "$PLIST"
else
  base64 -i "$PLIST" | tr -d '\n'
fi
echo
echo
echo "Do NOT paste the raw XML plist. Only paste the base64 line above."
