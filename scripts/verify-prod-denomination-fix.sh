#!/usr/bin/env bash
# Run on production after deploy: ssh buyselles 'bash -s' < scripts/verify-prod-denomination-fix.sh
set -euo pipefail
cd /var/www/buyselles

if ! grep -q resolveDefaultDenomination app/Models/SupplierProductMapping.php; then
  echo "FAIL: backend not deployed (resolveDefaultDenomination missing)"
  exit 1
fi

php artisan tinker --execute '
$m = App\Models\SupplierProductMapping::query()
  ->where("product_id", 48)
  ->where("is_active", true)
  ->with("activeDenominations")
  ->first();
if (! $m) { echo "FAIL: no mapping for product 48\n"; exit(1); }
$d = $m->resolveDefaultDenomination();
echo "default_denom_id=" . ($d?->id ?? "null") . " face=" . ($d?->face_value ?? "null") . " supplier_product_id=" . ($d?->supplier_product_id ?? "null") . "\n";
if (! $d || (float) $d->face_value <= 0) { echo "FAIL: could not resolve default denomination\n"; exit(1); }
echo "OK: default denomination resolves for product 48\n";
'

echo "Next: place a new wallet test order for product 48 and confirm order_details.supplier_denomination_id is set."
