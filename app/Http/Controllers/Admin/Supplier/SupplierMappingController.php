<?php

namespace App\Http\Controllers\Admin\Supplier;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Http\Controllers\BaseController;
use App\Jobs\SyncDenominationsJob;
use App\Jobs\SyncSupplierMappingPricesJob;
use App\Models\Product;
use App\Models\SupplierApi;
use App\Models\SupplierProductMapping;
use App\Services\Supplier\MappedProductCacheService;
use App\Services\Supplier\SupplierCurrencyConverter;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class SupplierMappingController extends BaseController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo,
        private readonly MappedProductCacheService $mappedProductCacheService,
    ) {}

    /**
     * Display product-supplier mappings for a given product.
     */
    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $searchValue = $request->get('searchValue');
        $supplierId = $request->get('supplier_id');

        $mappings = SupplierProductMapping::query()
            ->with(['product', 'supplierApi'])
            ->when($supplierId, fn ($q) => $q->where('supplier_api_id', $supplierId))
            ->when($searchValue, function ($q) use ($searchValue) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$searchValue}%"))
                    ->orWhere('supplier_product_id', 'like', "%{$searchValue}%");
            })
            ->orderBy('priority')
            ->paginate(getWebConfig(name: 'pagination_limit'));

        $suppliers = SupplierApi::orderBy('name')->get(['id', 'name']);

        return view('admin-views.supplier.mapping-list', compact('mappings', 'suppliers', 'searchValue', 'supplierId'));
    }

    /**
     * Show add mapping form.
     */
    public function getAddView(): View
    {
        $suppliers = SupplierApi::orderBy('name')->get(['id', 'name', 'driver', 'is_active', 'supports_direct_top_up']);
        $categories = $this->categoryRepo->getListWhere(filters: ['position' => 0], dataLimit: 'all');

        return view('admin-views.supplier.mapping-add', compact('suppliers', 'categories'));
    }

    /**
     * List in-house digital products for supplier mapping (AJAX).
     */
    public function getInHouseProducts(Request $request): JsonResponse
    {
        $categoryId = (int) $request->get('category_id');
        $subCategoryId = (int) $request->get('sub_category_id');
        $subSubCategoryId = (int) $request->get('sub_sub_category_id');

        if ($categoryId <= 0) {
            return response()->json([
                'success' => true,
                'products' => [],
            ]);
        }

        $supplierApiId = (int) $request->get('supplier_api_id');
        $exceptMappingId = (int) $request->get('except_mapping_id');

        $query = $this->applyCategoryFilters(
            query: $this->inHouseDigitalProductQuery(),
            categoryId: $categoryId,
            subCategoryId: $subCategoryId,
            subSubCategoryId: $subSubCategoryId,
        );

        $query = $this->excludeProductsMappedToSupplier(
            query: $query,
            supplierApiId: $supplierApiId,
            exceptMappingId: $exceptMappingId,
        );

        $products = $query->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'products' => $products,
            'count' => $products->count(),
        ]);
    }

    /**
     * Store a new product-supplier mapping.
     */
    public function add(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'supplier_api_id' => 'required|exists:supplier_apis,id',
            'supplier_product_id' => 'required|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'cost_currency' => 'required|string|max:3',
            'markup_type' => 'required|in:percent,flat',
            'markup_value' => 'required|numeric|min:0',
            'priority' => 'required|integer|min:0',
            'code_source_priority' => 'required|in:local_first,supplier_first',
            'is_customizable' => 'nullable|boolean',
            'is_direct_topup' => 'nullable|boolean',
            'direct_topup_account_label' => 'nullable|string|max:255',
            'direct_topup_region' => 'nullable|string|size:2|alpha',
            'direct_topup_bundle_quantity' => 'required_if:is_direct_topup,1|nullable|numeric|min:0.0001|max:999999999999',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());

            return redirect()->back()->withInput();
        }

        if (! $this->inHouseDigitalProductQuery()->where('id', $request->input('product_id'))->exists()) {
            Toastr::error(translate('supplier_mapping_in_house_product_required') ?: 'Please select a valid in-house digital product.');

            return redirect()->back()->withInput();
        }

        $directTopupAttributes = $this->resolveDirectTopupAttributesFromRequest($request);

        if ($this->productAlreadyMappedToSupplier(
            productId: (int) $request->input('product_id'),
            supplierApiId: (int) $request->input('supplier_api_id'),
        )) {
            Toastr::error(translate('this_product_is_already_mapped_to_this_supplier') ?: 'This product is already mapped to this supplier.');

            return redirect()->back()->withInput();
        }

        $mapping = SupplierProductMapping::create([
            'product_id' => $request->input('product_id'),
            'supplier_api_id' => $request->input('supplier_api_id'),
            'supplier_product_id' => $request->input('supplier_product_id'),
            'supplier_product_name' => $request->input('supplier_product_name'),
            'cost_price' => $request->input('cost_price'),
            'cost_currency' => $request->input('cost_currency', 'USD'),
            'markup_type' => $request->input('markup_type'),
            'markup_value' => $request->input('markup_value', 0),
            'priority' => $request->input('priority', 0),
            'code_source_priority' => $request->input('code_source_priority', SupplierProductMapping::CODE_SOURCE_LOCAL_FIRST),
            'is_active' => true,
            'is_customizable' => (bool) $request->input('is_customizable', false),
            ...$directTopupAttributes,
            'min_amount' => $request->input('is_customizable') ? $request->input('min_amount') : null,
            'max_amount' => $request->input('is_customizable') ? $request->input('max_amount') : null,
        ]);

        SyncDenominationsJob::dispatch($mapping->id);

        $this->mappedProductCacheService->bustForMapping($mapping->fresh(), refreshSupplierStock: false);

        Toastr::success(translate('mapping_added_successfully'));

        return redirect()->route('admin.supplier.mapping.list');
    }

    /**
     * Show edit mapping form.
     */
    public function getUpdateView(int $id): View|RedirectResponse
    {
        $mapping = SupplierProductMapping::with(['product', 'supplierApi'])->findOrFail($id);

        $suppliers = SupplierApi::orderBy('name')->get(['id', 'name', 'driver', 'is_active', 'supports_direct_top_up']);
        $categories = $this->categoryRepo->getListWhere(filters: ['position' => 0], dataLimit: 'all');

        return view('admin-views.supplier.mapping-edit', compact('mapping', 'suppliers', 'categories'));
    }

    /**
     * Update a mapping.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'supplier_api_id' => 'required|exists:supplier_apis,id',
            'supplier_product_id' => 'required|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'cost_currency' => 'required|string|max:3',
            'markup_type' => 'required|in:percent,flat',
            'markup_value' => 'required|numeric|min:0',
            'priority' => 'required|integer|min:0',
            'code_source_priority' => 'required|in:local_first,supplier_first',
            'is_customizable' => 'nullable|boolean',
            'is_direct_topup' => 'nullable|boolean',
            'direct_topup_account_label' => 'nullable|string|max:255',
            'direct_topup_region' => 'nullable|string|size:2|alpha',
            'direct_topup_bundle_quantity' => 'required_if:is_direct_topup,1|nullable|numeric|min:0.0001|max:999999999999',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());

            return redirect()->back()->withInput();
        }

        if (! $this->inHouseDigitalProductQuery()->where('id', $request->input('product_id'))->exists()) {
            Toastr::error(translate('supplier_mapping_in_house_product_required') ?: 'Please select a valid in-house digital product.');

            return redirect()->back()->withInput();
        }

        $mapping = SupplierProductMapping::findOrFail($id);
        $supplierProductChanged = $mapping->supplier_product_id !== $request->input('supplier_product_id');
        $supplierChanged = (int) $mapping->supplier_api_id !== (int) $request->input('supplier_api_id');
        $productChanged = (int) $mapping->product_id !== (int) $request->input('product_id');
        $directTopupAttributes = $this->resolveDirectTopupAttributesFromRequest($request);

        if ($productChanged || $supplierChanged) {
            if ($this->productAlreadyMappedToSupplier(
                productId: (int) $request->input('product_id'),
                supplierApiId: (int) $request->input('supplier_api_id'),
                exceptMappingId: $id,
            )) {
                Toastr::error(translate('this_product_is_already_mapped_to_this_supplier') ?: 'This product is already mapped to this supplier.');

                return redirect()->back()->withInput();
            }
        }

        $mapping->update([
            'product_id' => $request->input('product_id'),
            'supplier_api_id' => $request->input('supplier_api_id'),
            'supplier_product_id' => $request->input('supplier_product_id'),
            'supplier_product_name' => $request->input('supplier_product_name'),
            'cost_price' => $request->input('cost_price'),
            'cost_currency' => $request->input('cost_currency', 'USD'),
            'markup_type' => $request->input('markup_type'),
            'markup_value' => $request->input('markup_value', 0),
            'priority' => $request->input('priority', 0),
            'code_source_priority' => $request->input('code_source_priority', SupplierProductMapping::CODE_SOURCE_LOCAL_FIRST),
            'is_customizable' => (bool) $request->input('is_customizable', false),
            ...$directTopupAttributes,
            'min_amount' => $request->input('is_customizable') ? $request->input('min_amount') : null,
            'max_amount' => $request->input('is_customizable') ? $request->input('max_amount') : null,
        ]);

        if ($supplierChanged || $supplierProductChanged || $productChanged || $mapping->activeDenominations()->count() === 0) {
            SyncDenominationsJob::dispatch($mapping->id);
        }

        $this->mappedProductCacheService->bustForMapping($mapping->fresh(), refreshSupplierStock: false);

        Toastr::success(translate('mapping_updated_successfully'));

        return redirect()->route('admin.supplier.mapping.list');
    }

    /**
     * Toggle mapping active status (AJAX).
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $mapping = SupplierProductMapping::findOrFail($request->input('id'));
        $mapping->update(['is_active' => $request->input('status', 0)]);

        return response()->json([
            'success' => 1,
            'message' => translate('status_updated_successfully'),
        ]);
    }

    /**
     * AJAX: check if the selected supplier supports direct top-up.
     */
    public function validateDirectTopup(Request $request): JsonResponse
    {
        $supplierId = $request->input('supplier_api_id');

        if (! $supplierId) {
            return response()->json([
                'supported' => false,
                'message' => translate('please_select_a_supplier_first') ?: 'Please select a supplier first.',
            ]);
        }

        $supplier = SupplierApi::find($supplierId);

        if (! $supplier) {
            return response()->json([
                'supported' => false,
                'message' => translate('supplier_not_found') ?: 'Supplier not found.',
            ]);
        }

        if (! $supplier->supports_direct_top_up) {
            return response()->json([
                'supported' => false,
                'message' => translate('supplier_does_not_support_direct_topup') ?: 'This supplier does not support direct top-up.',
            ]);
        }

        return response()->json([
            'supported' => true,
        ]);
    }

    /**
     * Delete a mapping.
     */
    public function delete(Request $request): RedirectResponse
    {
        SupplierProductMapping::findOrFail($request->input('id'))->delete();

        Toastr::success(translate('mapping_deleted_successfully'));

        return redirect()->back();
    }

    /**
     * AJAX: convert cost price between currencies using exchange rates.
     */
    public function convertCost(Request $request, SupplierCurrencyConverter $converter): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'from_currency' => 'required|string|max:3',
            'to_currency' => 'required|string|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $amount = $converter->convertBetween(
            (float) $request->input('amount'),
            (string) $request->input('from_currency'),
            (string) $request->input('to_currency'),
        );

        return response()->json([
            'success' => true,
            'amount' => $amount,
            'currency' => strtoupper((string) $request->input('to_currency')),
        ]);
    }

    /**
     * Dispatch a background job to sync prices for all active mappings.
     */
    public function syncPrices(): JsonResponse
    {
        try {
            SyncSupplierMappingPricesJob::dispatchSync();

            return response()->json([
                'success' => true,
                'message' => translate('price_sync_completed_successfully'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => translate('price_sync_failed').': '.$e->getMessage(),
            ], 500);
        }
    }

    private function inHouseDigitalProductQuery(): Builder
    {
        return Product::query()
            ->where('added_by', 'admin')
            ->where('product_type', 'digital')
            ->where('status', 1);
    }

    /**
     * @return array{
     *     is_direct_topup: bool,
     *     direct_topup_account_label: ?string,
     *     direct_topup_region: ?string,
     *     direct_topup_bundle_quantity: ?float
     * }
     */
    private function resolveDirectTopupAttributesFromRequest(Request $request): array
    {
        $supplier = SupplierApi::find($request->input('supplier_api_id'));

        if (! $supplier || ! $supplier->supports_direct_top_up) {
            return [
                'is_direct_topup' => false,
                'direct_topup_account_label' => null,
                'direct_topup_region' => null,
                'direct_topup_bundle_quantity' => null,
            ];
        }

        $isDirectTopup = (bool) $request->input('is_direct_topup', false);

        $accountLabel = $isDirectTopup
            ? trim((string) $request->input('direct_topup_account_label', ''))
            : null;

        if ($isDirectTopup && $accountLabel === '') {
            $accountLabel = translate('player_id') ?: 'Player ID';
        }

        $region = $isDirectTopup
            ? strtoupper(trim((string) $request->input('direct_topup_region', '')))
            : null;

        if ($region === '') {
            $region = null;
        }

        $bundleQuantity = $isDirectTopup
            ? (float) $request->input('direct_topup_bundle_quantity', 0)
            : null;

        if (! $isDirectTopup || $bundleQuantity <= 0) {
            $bundleQuantity = null;
        }

        return [
            'is_direct_topup' => $isDirectTopup,
            'direct_topup_account_label' => $accountLabel,
            'direct_topup_region' => $region,
            'direct_topup_bundle_quantity' => $bundleQuantity,
        ];
    }

    private function excludeProductsMappedToSupplier(
        Builder $query,
        int $supplierApiId,
        int $exceptMappingId = 0,
    ): Builder {
        if ($supplierApiId <= 0) {
            return $query;
        }

        $mappedProductIds = SupplierProductMapping::query()
            ->where('supplier_api_id', $supplierApiId)
            ->when($exceptMappingId > 0, fn (Builder $mappingQuery) => $mappingQuery->where('id', '!=', $exceptMappingId))
            ->pluck('product_id');

        if ($mappedProductIds->isEmpty()) {
            return $query;
        }

        return $query->whereNotIn('id', $mappedProductIds);
    }

    private function productAlreadyMappedToSupplier(
        int $productId,
        int $supplierApiId,
        ?int $exceptMappingId = null,
    ): bool {
        return SupplierProductMapping::query()
            ->where('product_id', $productId)
            ->where('supplier_api_id', $supplierApiId)
            ->when($exceptMappingId, fn (Builder $query) => $query->where('id', '!=', $exceptMappingId))
            ->exists();
    }

    /**
     * Apply the most specific category filter, matching admin product list behaviour.
     * When a sub-sub category is selected, only sub_sub_category_id is used so products
     * with a stale or missing category_id are still included.
     */
    private function applyCategoryFilters(
        Builder $query,
        int $categoryId,
        int $subCategoryId,
        int $subSubCategoryId,
    ): Builder {
        if ($subSubCategoryId > 0) {
            return $query->where('sub_sub_category_id', $subSubCategoryId);
        }

        if ($subCategoryId > 0) {
            return $query->where('sub_category_id', $subCategoryId);
        }

        return $query->where('category_id', $categoryId);
    }
}
