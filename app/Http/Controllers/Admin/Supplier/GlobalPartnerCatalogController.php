<?php

namespace App\Http\Controllers\Admin\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GlobalPartnerCatalogAssignRequest;
use App\Models\Product;
use App\Models\ResellerApiKey;
use App\Models\SupplierApi;
use App\Services\Partner\GlobalPartnerCatalogQuery;
use App\Services\Partner\PartnerCatalogAssignmentService;
use App\Services\Partner\PartnerIdentityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GlobalPartnerCatalogController extends Controller
{
    public function __construct(
        private readonly GlobalPartnerCatalogQuery $globalCatalogQuery,
        private readonly PartnerIdentityService $partnerIdentity,
        private readonly PartnerCatalogAssignmentService $assignmentService,
    ) {}

    public function index(Request $request): View
    {
        $products = $this->globalCatalogQuery->paginate(
            search: $request->get('searchValue'),
            supplierId: $request->integer('supplier_id') ?: null,
            fulfillmentType: $request->get('fulfillment_type'),
            perPage: (int) getWebConfig(name: 'pagination_limit') ?: 30,
        );

        $suppliers = SupplierApi::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $partnerKeys = ResellerApiKey::query()
            ->active()
            ->with(['user', 'seller'])
            ->orderBy('name')
            ->get(['id', 'name', 'user_id', 'seller_id']);

        return view('admin-views.partner.global-catalog', compact(
            'products',
            'suppliers',
            'partnerKeys',
        ));
    }

    public function search(Request $request): JsonResponse
    {
        $products = $this->globalCatalogQuery->paginate(
            search: $request->get('q') ?? $request->get('searchValue'),
            supplierId: $request->integer('supplier_id') ?: null,
            fulfillmentType: $request->get('fulfillment_type'),
            perPage: min(50, max(1, (int) $request->get('limit', 20))),
        );

        return response()->json([
            'success' => true,
            'products' => $products->getCollection()->map(function (Product $product): array {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'unit_price' => (float) $product->unit_price,
                    'reference_price' => $this->globalCatalogQuery->resolveReferencePrice($product),
                    'fulfillment_type' => $this->globalCatalogQuery->resolveFulfillmentType($product),
                    'supplier_name' => $product->supplierMapping?->supplierApi?->name,
                ];
            })->values(),
            'total' => $products->total(),
        ]);
    }

    public function partners(int $productId): JsonResponse
    {
        $product = $this->globalCatalogQuery->findEligibleProduct($productId);

        if ($product === null) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $assignments = $this->globalCatalogQuery->partnerAssignmentsForProduct($productId)
            ->keyBy('partner_catalog_id');

        $partnerKeys = ResellerApiKey::query()
            ->active()
            ->with(['user', 'seller'])
            ->orderBy('name')
            ->get();

        $rows = $partnerKeys->map(function (ResellerApiKey $key) use ($assignments): array {
            $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);
            $item = $assignments->get($catalog->id);

            return [
                'reseller_key_id' => $key->id,
                'partner_name' => $key->name,
                'account_name' => $key->user?->f_name ?? ($key->seller ? trim($key->seller->f_name.' '.$key->seller->l_name) : null),
                'catalog_id' => $catalog->id,
                'assigned' => $item !== null,
                'is_active' => (bool) ($item?->is_active ?? false),
                'partner_price' => $item !== null ? (float) $item->partner_price : null,
                'currency' => $item?->currency ?? 'USD',
            ];
        })->values();

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'reference_price' => $this->globalCatalogQuery->resolveReferencePrice($product),
                'fulfillment_type' => $this->globalCatalogQuery->resolveFulfillmentType($product),
            ],
            'partners' => $rows,
        ]);
    }

    public function assign(GlobalPartnerCatalogAssignRequest $request, int $productId): JsonResponse
    {
        $product = $this->globalCatalogQuery->findEligibleProduct($productId);

        if ($product === null) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $key = ResellerApiKey::query()->findOrFail((int) $request->validated('reseller_key_id'));
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);

        try {
            $item = $this->assignmentService->assignExistingProduct(
                catalog: $catalog,
                productId: $productId,
                partnerPrice: $request->filled('partner_price')
                    ? (float) $request->validated('partner_price')
                    : null,
                options: [
                    'currency' => $request->validated('currency') ?? 'USD',
                    'is_active' => $request->boolean('is_active', true),
                ],
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => translate('partner_catalog_item_saved') ?: 'Partner catalog updated.',
            'item' => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'partner_price' => $item->partner_price !== null ? (float) $item->partner_price : null,
                'is_active' => (bool) $item->is_active,
            ],
        ]);
    }

    public function toggle(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'reseller_key_id' => ['required', 'integer', 'exists:reseller_api_keys,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $product = $this->globalCatalogQuery->findEligibleProduct($productId);

        if ($product === null) {
            return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
        }

        $key = ResellerApiKey::query()->findOrFail((int) $request->integer('reseller_key_id'));
        $catalog = $this->partnerIdentity->resolveOrCreateCatalog($key);

        try {
            $item = $this->assignmentService->toggleVisibility(
                catalog: $catalog,
                productId: $productId,
                isActive: $request->boolean('is_active'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => translate('status_updated_successfully'),
            'item' => [
                'id' => $item->id,
                'is_active' => (bool) $item->is_active,
            ],
        ]);
    }
}
