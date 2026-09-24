#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="${ROOT}/mobile/vendor_app"
ANDROID_DIR="${APP_DIR}/android"
KEY_PROPS="${ANDROID_DIR}/key.properties"
KEYSTORE="${APP_DIR}/upload-keystore.jks"
GOOGLE_SERVICES="${ANDROID_DIR}/app/google-services.json"
PACKAGE_NAME="com.buyselles.vendor"
EXPECTED_SHA1="9441b1c0629e20e51096918d1e4002054869b98c"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok() { echo -e "${GREEN}OK${NC}  $1"; }
fail() { echo -e "${RED}FAIL${NC} $1"; ERRORS=1; }
warn() { echo -e "${YELLOW}WARN${NC} $1"; }

ERRORS=0

echo "=== Buyselles Vendor — Android Play Store setup check ==="
echo

if command -v flutter >/dev/null 2>&1; then
  ok "Flutter installed: $(flutter --version | head -1)"
else
  fail "Flutter not found in PATH"
fi

if [[ -f "${ANDROID_DIR}/local.properties" ]]; then
  ok "local.properties exists"
else
  warn "local.properties missing — run 'flutter pub get' once in mobile/vendor_app"
fi

if [[ -f "${KEY_PROPS}" ]]; then
  ok "key.properties exists"
  if grep -q "YOUR_" "${KEY_PROPS}" 2>/dev/null; then
    fail "key.properties still has placeholder values"
  fi
else
  fail "key.properties missing — copy android/key.properties.example and fill values"
fi

if [[ -f "${KEYSTORE}" ]]; then
  ok "upload-keystore.jks exists"
else
  fail "upload-keystore.jks missing at mobile/vendor_app/upload-keystore.jks"
fi

if [[ -f "${GOOGLE_SERVICES}" ]]; then
  ok "google-services.json exists"
  if grep -q "\"package_name\": \"${PACKAGE_NAME}\"" "${GOOGLE_SERVICES}"; then
    ok "google-services.json contains ${PACKAGE_NAME}"
  else
    fail "google-services.json missing package ${PACKAGE_NAME}"
  fi
else
  fail "google-services.json missing at android/app/google-services.json"
fi

if [[ -f "${KEY_PROPS}" && -f "${KEYSTORE}" ]]; then
  # shellcheck disable=SC1090
  source <(grep -E '^(storePassword|keyPassword|keyAlias|storeFile)=' "${KEY_PROPS}" | sed 's/\r$//')
  KEYSTORE_PATH="$(cd "${ANDROID_DIR}" && realpath "${storeFile}")"
  if [[ -f "${KEYSTORE_PATH}" ]]; then
    SHA1="$(
      keytool -list -v \
        -keystore "${KEYSTORE_PATH}" \
        -alias "${keyAlias}" \
        -storepass "${storePassword}" 2>/dev/null \
        | awk -F': ' '/SHA1:/ {print $2; exit}' \
        | tr -d ':' \
        | tr '[:upper:]' '[:lower:]'
    )"
    if [[ "${SHA1}" == "${EXPECTED_SHA1}" ]]; then
      ok "Keystore SHA1 matches Firebase (${EXPECTED_SHA1})"
    else
      fail "Keystore SHA1 (${SHA1}) does not match Firebase (${EXPECTED_SHA1})"
    fi
  else
    fail "Keystore path from key.properties not found: ${KEYSTORE_PATH}"
  fi
fi

VERSION_LINE="$(grep '^version:' "${APP_DIR}/pubspec.yaml" | awk '{print $2}')"
if [[ -n "${VERSION_LINE}" ]]; then
  ok "pubspec version: ${VERSION_LINE}"
else
  fail "Could not read version from pubspec.yaml"
fi

APP_ID="$(grep 'applicationId' "${ANDROID_DIR}/app/build.gradle.kts" | head -1 | sed 's/.*"\(.*\)".*/\1/')"
if [[ "${APP_ID}" == "${PACKAGE_NAME}" ]]; then
  ok "Gradle applicationId: ${APP_ID}"
else
  fail "Gradle applicationId is '${APP_ID}', expected '${PACKAGE_NAME}'"
fi

if [[ -f "${ANDROID_DIR}/app/build.gradle.kts" ]] && grep -q 'androidx.datastore:datastore:1.2.1' "${ANDROID_DIR}/app/build.gradle.kts"; then
  ok "DataStore forced to 1.2.1 (16 KB page size fix)"
else
  fail "build.gradle.kts missing DataStore 1.2.1 force (16 KB page size)"
fi

echo
if [[ "${ERRORS}" -eq 0 ]]; then
  echo -e "${GREEN}All checks passed.${NC} Ready for:"
  echo "  cd mobile/vendor_app && flutter build appbundle --release"
  echo "  Output: build/app/outputs/bundle/release/app-release.aab"
else
  echo -e "${RED}Fix the FAIL items above before building.${NC}"
  exit 1
fi
