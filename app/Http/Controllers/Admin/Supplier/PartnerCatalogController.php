<?php

namespace App\Http\Controllers\Admin\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PartnerCatalogAssignExistingRequest;
use App\Http\Requests\Admin\PartnerCatalogAssignFromSupplierRequest;
use App\Http\Requests\Admin\PartnerCatalogItemRequest;
use App\Models\PartnerCatalogItem;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\SupplierApi;
use App\Services\Partner\GlobalPartnerCatalogQuery;
use App\Services\Partner\PartnerCatalogAssignmentService;
use App\Services\Partner\PartnerIdentityService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Partner API curated catalog management.
 * Separate from storefront supplier mapping screens.
 */
class PartnerCatalogController extends Controller
{
    public function __construct(
        private readonly PartnerIdentityService $partnerIdentity,
        private readonly PartnerCatalogAssignmentService $assignmentService,
        private readonly GlobalPartnerCatalogQuery $globalCatalogQuery,
    ) {}

    public function index(Request $request, int $keyId): View
    {
        $key = ResellerApiKey::query()
            ->with(['user', 'seller'])
            ->findOrFail($keyId);
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);

        $items = $catalog->items()
            ->with([
                'product.supplierMapping.supplierApi',
                'product.supplierMapping.activeDenominations',
                'activeDenominationPrices.denomination',
            ])
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $suppliers = SupplierApi::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'driver', 'supports_direct_top_up']);

        return view('admin-views.reseller.catalog', compact(
            'key',
            'catalog',
            'items',
            'suppliers',
        ));
    }

    /**
     * Assign an existing global catalog (storefront) product to this partner.
     */
    public function assignExistingProduct(
        PartnerCatalogAssignExistingRequest $request,
        int $keyId,
    ): JsonResponse|RedirectResponse {
        $key = ResellerApiKey::query()->findOrFail($keyId);
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);

        try {
            $item = $this->assignmentService->assignExistingProduct(
                catalog: $catalog,
                productId: (int) $request->validated('product_id'),
                partnerPrice: (float) $request->validated('partner_price'),
                options: [
                    'currency' => $request->validated('currency') ?? 'USD',
                    'is_active' => $request->boolean('is_active', true),
                ],
            );
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            Toastr::error($e->getMessage());

            return redirect()->back()->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => translate('partner_catalog_item_saved') ?: 'Product added to partner catalog.',
                'item' => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'partner_price' => (float) $item->partner_price,
                ],
            ]);
        }

        Toastr::success(translate('partner_catalog_item_saved') ?: 'Product added to partner catalog.');

        return redirect()->route('admin.reseller-keys.catalog', $keyId);
    }

    /**
     * Assign a supplier catalog item to this partner with an exact partner price.
     * Creates partner-exclusive products that never appear on the storefront.
     */
    public function assignFromSupplier(
        PartnerCatalogAssignFromSupplierRequest $request,
        int $keyId,
    ): JsonResponse|RedirectResponse {
        $key = ResellerApiKey::query()->findOrFail($keyId);
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);

        try {
            $item = $this->assignmentService->assignFromSupplierCatalog(
                $catalog,
                $request->validated()
            );
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            Toastr::error($e->getMessage());

            return redirect()->back()->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => translate('partner_catalog_item_saved') ?: 'Product added to partner catalog.',
                'item' => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'partner_price' => (float) $item->partner_price,
                ],
            ]);
        }

        Toastr::success(translate('partner_catalog_item_saved') ?: 'Product added to partner catalog.');

        return redirect()->route('admin.reseller-keys.catalog', $keyId);
    }

    /**
     * Update exact partner pricing / denomination pricing for an already-assigned item.
     */
    public function store(PartnerCatalogItemRequest $request, int $keyId): RedirectResponse
    {
        $key = ResellerApiKey::query()->findOrFail($keyId);
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);
        $product = $this->findPartnerCatalogProduct((int) $request->validated('product_id'));

        $this->validatePricingConfiguration($request, $product);

        if (! $catalog->items()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Assign this product to the partner catalog first.',
            ]);
        }

        DB::transaction(function () use ($request, $catalog, $product): void {
            $catalogItem = PartnerCatalogItem::query()
                ->where('partner_catalog_id', $catalog->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $catalogItem->update([
                'partner_price' => $request->validated('partner_price'),
                'variable_price_type' => $request->validated('variable_price_type'),
                'variable_price_value' => $request->validated('variable_price_value'),
                'currency' => strtoupper((string) $request->validated('currency')),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $catalogItem->denominationPrices()->delete();
            $catalogItem->denominationPrices()->createMany(
                collect($request->validated('denomination_prices', []))
                    ->map(fn (array $price): array => [
                        'supplier_product_denomination_id' => (int) $price['denomination_id'],
                        'partner_price' => (float) $price['partner_price'],
                        'is_active' => true,
                    ])
                    ->all()
            );
        });

        Toastr::success(translate('partner_catalog_item_saved') ?: 'Partner catalog pricing updated.');

        return redirect()->route('admin.reseller-keys.catalog', $keyId);
    }

    public function destroy(Request $request, int $keyId, int $itemId): RedirectResponse|JsonResponse
    {
        $key = ResellerApiKey::query()->findOrFail($keyId);
        $catalog = $this->partnerIdentity->findCatalog($key);

        if ($catalog !== null) {
            $catalog->items()->whereKey($itemId)->delete();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => translate('partner_catalog_item_removed') ?: 'Removed from partner catalog.',
            ]);
        }

        Toastr::success(translate('partner_catalog_item_removed') ?: 'Removed from partner catalog.');

        return redirect()->route('admin.reseller-keys.catalog', $keyId);
    }

    public function toggle(Request $request, int $keyId, int $itemId): JsonResponse
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $key = ResellerApiKey::query()->findOrFail($keyId);
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);

        $item = $catalog->items()->whereKey($itemId)->firstOrFail();
        $item->update(['is_active' => $request->boolean('is_active')]);

        return response()->json([
            'success' => true,
            'message' => translate('status_updated_successfully'),
            'item' => [
                'id' => $item->id,
                'is_active' => (bool) $item->is_active,
            ],
        ]);
    }

    public function updatePrice(Request $request, int $keyId, int $itemId): JsonResponse
    {
        $request->validate([
            'partner_price' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $key = ResellerApiKey::query()->findOrFail($keyId);
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);

        $item = $catalog->items()->whereKey($itemId)->firstOrFail();
        $item->update([
            'partner_price' => (float) $request->input('partner_price'),
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('partner_catalog_item_saved') ?: 'Partner price updated.',
            'item' => [
                'id' => $item->id,
                'partner_price' => (float) $item->partner_price,
            ],
        ]);
    }

    private function findPartnerCatalogProduct(int $productId): Product
    {
        $partnerOnlyProduct = Product::query()
            ->withoutGlobalScope(Product::STOREFRONT_SCOPE)
            ->where('partner_api_only', true)
            ->with(['supplierMapping.activeDenominations'])
            ->find($productId);

        if ($partnerOnlyProduct !== null) {
            return $partnerOnlyProduct;
        }

        $globalProduct = $this->globalCatalogQuery->findEligibleProduct($productId);

        if ($globalProduct === null) {
            abort(404);
        }

        $globalProduct->loadMissing(['supplierMapping.activeDenominations']);

        return $globalProduct;
    }

    private function validatePricingConfiguration(
        PartnerCatalogItemRequest $request,
        Product $product,
    ): void {
        $mapping = $product->supplierMapping;
        $denominations = $mapping?->activeDenominations ?? collect();
        $fixedDenominationIds = $denominations
            ->where('type', 'fixed')
            ->pluck('id');
        $submittedDenominationIds = collect($request->validated('denomination_prices', []))
            ->pluck('denomination_id')
            ->map(fn (mixed $id): int => (int) $id);

        if ($submittedDenominationIds->diff($fixedDenominationIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'denomination_prices' => 'A selected denomination does not belong to this product.',
            ]);
        }

        if ($mapping?->is_direct_topup || $denominations->isEmpty()) {
            if (! $request->filled('partner_price')) {
                throw ValidationException::withMessages([
                    'partner_price' => 'An exact partner price is required for this product.',
                ]);
            }

            return;
        }

        $hasFixedPrice = $submittedDenominationIds->isNotEmpty();
        $hasVariableFormula = $denominations->contains('type', 'variable')
            && $request->filled('variable_price_type')
            && $request->filled('variable_price_value');
        $hasVariableDenomination = $denominations->contains('type', 'variable');

        if (! $hasFixedPrice && ! $hasVariableFormula && ! $hasVariableDenomination) {
            throw ValidationException::withMessages([
                'partner_price' => 'Configure at least one denomination price or a variable pricing formula.',
            ]);
        }
    }
}
