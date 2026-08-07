#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VENDOR="${ROOT}/mobile/vendor_app"

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'
ERRORS=0

ok() { echo -e "${GREEN}OK${NC}  $1"; }
fail() { echo -e "${RED}FAIL${NC} $1"; ERRORS=1; }

echo "=== Vendor financial UX fix verification (static) ==="
echo

WALLET_CTRL="${VENDOR}/lib/features/wallet/controllers/wallet_controller.dart"
WALLET_SVC="${VENDOR}/lib/features/wallet/domain/services/wallet_service.dart"
DIALOG="${VENDOR}/lib/common/basewidgets/custom_edit_dialog_widget.dart"
MENU="${VENDOR}/lib/features/menu/widgets/menu_widget.dart"
TX_WIDGET="${VENDOR}/lib/features/transaction/widgets/transaction_widget.dart"
SHOP_CTRL="${VENDOR}/lib/features/shop/controllers/shop_controller.dart"
DM_CTRL="${VENDOR}/lib/features/delivery_man/controllers/delivery_man_controller.dart"
DM_SVC="${VENDOR}/lib/features/delivery_man/domain/services/delivery_service.dart"
WT_CTRL="${VENDOR}/lib/features/wallet_transfer/controllers/wallet_transfer_controller.dart"
WT_SVC="${VENDOR}/lib/features/wallet_transfer/domain/services/wallet_transfer_service.dart"
APP_CONST="${VENDOR}/lib/utill/app_constants.dart"
WALLET_SCREEN="${VENDOR}/lib/features/wallet/screens/wallet_screen.dart"

grep -q "apiResponse.response?.statusCode == 200" "${WALLET_CTRL}" \
  && ok "Withdraw only succeeds on HTTP 200" \
  || fail "wallet_controller missing statusCode check on withdraw"

grep -q "ApiChecker.checkApi(apiResponse)" "${WALLET_CTRL}" \
  && ok "Withdraw failure surfaces ApiChecker error" \
  || fail "wallet_controller missing ApiChecker on withdraw failure"

grep -q "response.response != null && response.response!.statusCode == 200" "${WALLET_CTRL}" \
  && ok "getWithdrawMethods is null-safe" \
  || fail "getWithdrawMethods missing null-safe API check"

grep -q "closeWithdrawRequest" "${WALLET_SVC}" \
  && grep -q "ApiChecker.checkApi(apiResponse)" "${WALLET_SVC}" \
  && ok "Cancel withdraw surfaces API errors in wallet_service" \
  || fail "wallet_service closeWithdrawRequest missing ApiChecker"

grep -q "withdraw_request_deleted" "${WALLET_CTRL}" \
  && grep -q "ApiChecker.checkApi(apiResponse)" "${WALLET_CTRL}" \
  && ok "Cancel withdraw success/error handled in wallet_controller" \
  || fail "wallet_controller closeWithdrawRequest missing proper handling"

grep -q "value.response!.statusCode" "${TX_WIDGET}" \
  && fail "transaction_widget still force-unwraps cancel-withdraw response" \
  || ok "transaction_widget no unsafe cancel-withdraw response unwrap"

grep -q "_hasNoWithdrawMethods" "${DIALOG}" \
  && ok "Withdraw dialog empty-method warning wired" \
  || fail "custom_edit_dialog missing _hasNoWithdrawMethods"

grep -q "posActive == 1" "${MENU}" \
  && ok "POS menu gated by posActive" \
  || fail "menu_widget POS gate not restored"

grep -q "apiResponse.response != null && apiResponse.response!.statusCode == 200" "${SHOP_CTRL}" \
  && grep -A20 "deletePaymentMethodStatus" "${SHOP_CTRL}" | grep -q "ApiChecker.checkApi" \
  && ok "Shop payment-method mutations null-safe with error feedback" \
  || fail "shop_controller payment methods missing null-safe checks or ApiChecker"

grep -q "apiResponse.response != null && apiResponse.response!.statusCode == 200" "${DM_CTRL}" \
  && grep -q "getDeliveryManWithdrawList" "${DM_CTRL}" \
  && ok "Delivery-man withdraw list is null-safe" \
  || fail "delivery_man_controller withdraw list missing null-safe check"

grep -q "ApiChecker.checkApi(apiResponse)" "${DM_SVC}" \
  && grep -A12 "deliveryManWithdrawApprovedDenied" "${DM_SVC}" | grep -q "ResponseModel(false" \
  && ok "Delivery-man withdraw approve/deny uses ApiChecker on failure" \
  || fail "delivery_service withdraw approve/deny missing proper failure handling"

grep -q "vendorWalletTransferEnabled" "${MENU}" \
  && grep -q "WalletTransferScreen" "${MENU}" \
  && ok "Wallet transfer menu entry gated by module flag" \
  || fail "menu_widget missing wallet transfer entry"

grep -q "vendorWalletTransferEnabled" "${WALLET_SCREEN}" \
  && grep -q "WalletTransferScreen" "${WALLET_SCREEN}" \
  && ok "Wallet screen links to wallet transfer" \
  || fail "wallet_screen missing transfer CTA"

grep -q "walletTransferUri" "${APP_CONST}" \
  && grep -q "walletTransferSubmitUri" "${APP_CONST}" \
  && ok "Wallet transfer API constants defined" \
  || fail "app_constants missing wallet transfer URIs"

grep -q "response.response?.statusCode == 200" "${WT_CTRL}" \
  && grep -q "ApiChecker.checkApi(response)" "${WT_CTRL}" \
  && ok "Wallet transfer controller uses proper success/error handling" \
  || fail "wallet_transfer_controller missing HTTP 200 / ApiChecker handling"

grep -q "ApiChecker.checkApi(apiResponse)" "${WT_SVC}" \
  && ok "Wallet transfer service surfaces API errors" \
  || fail "wallet_transfer_service missing ApiChecker"

if [[ -f "${VENDOR}/APP_STORE_REVIEW_NOTES.md" ]]; then
  grep -q "Wallet Transfer to Customer" "${VENDOR}/APP_STORE_REVIEW_NOTES.md" \
    && ok "App Store review notes include wallet transfer" \
    || fail "APP_STORE_REVIEW_NOTES.md missing wallet transfer section"
else
  fail "Missing APP_STORE_REVIEW_NOTES.md"
fi

echo
echo "Running flutter analyze on financial-flow files..."
if (cd "${VENDOR}" && flutter analyze --no-fatal-warnings \
  lib/features/wallet \
  lib/features/wallet_transfer \
  lib/features/transaction/widgets/transaction_widget.dart \
  lib/features/shop/controllers/shop_controller.dart \
  lib/features/delivery_man/controllers/delivery_man_controller.dart \
  lib/features/delivery_man/domain/services/delivery_service.dart \
  lib/common/basewidgets/custom_edit_dialog_widget.dart \
  lib/features/menu/widgets/menu_widget.dart \
  lib/features/profile/domain/models/profile_info.dart >/dev/null 2>&1); then
  ok "flutter analyze passed"
else
  fail "flutter analyze failed — run manually in mobile/vendor_app"
fi

echo
if [[ "${ERRORS}" -eq 0 ]]; then
  echo -e "${GREEN}Code fixes verified.${NC}"
  echo "Complete device checks from mobile/vendor_app/APP_STORE_REVIEW_NOTES.md before iOS resubmission."
else
  echo -e "${RED}Fix failures above before resubmitting.${NC}"
  exit 1
fi
