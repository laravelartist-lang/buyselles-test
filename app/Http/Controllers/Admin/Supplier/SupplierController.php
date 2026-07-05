<?php

namespace App\Http\Controllers\Admin\Supplier;

use App\Http\Controllers\BaseController;
use App\Models\SupplierApi;
use App\Services\Supplier\SupplierHealthMonitor;
use App\Services\Supplier\SupplierManager;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;

class SupplierController extends BaseController
{
    public function __construct(
        private readonly SupplierManager $supplierManager,
        private readonly SupplierHealthMonitor $healthMonitor,
    ) {}

    /**
     * Display list of all suppliers.
     */
    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $searchValue = $request->get('searchValue');

        $suppliers = SupplierApi::query()
            ->when($searchValue, fn ($q) => $q->where('name', 'like', "%{$searchValue}%"))
            ->orderBy('priority')
            ->orderByDesc('id')
            ->paginate(getWebConfig(name: 'pagination_limit'));

        $balances = collect($this->supplierManager->getSupplierBalances())
            ->keyBy('id');

        return view('admin-views.supplier.list', compact('suppliers', 'searchValue', 'balances'));
    }

    /**
     * Show the form for adding a new supplier.
     */
    public function getAddView(): View
    {
        $drivers = $this->supplierManager->getAvailableDrivers();
        $driverSchemas = $this->supplierManager->getAvailableDriversWithSchemas();
        $defaultDriver = $drivers[0] ?? 'generic_rest';

        $defaultSchema = $driverSchemas[$defaultDriver] ?? ['credentials' => [], 'settings' => []];

        return view('admin-views.supplier.add', compact(
            'drivers',
            'driverSchemas',
            'defaultDriver',
            'defaultSchema',
        ));
    }

    /**
     * Store a new supplier.
     */
    public function add(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'driver' => 'required|string|in:'.implode(',', $this->supplierManager->getAvailableDrivers()),
            'base_url' => 'required|url|max:500',
            'auth_type' => 'required|in:api_key,bearer_token,oauth2,basic,hmac,login_via',
            'rate_limit_per_minute' => 'required|integer|min:1|max:1000',
            'priority' => 'required|integer|min:0',
            'is_sandbox' => 'nullable|boolean',
            'supports_direct_top_up' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());

            return redirect()->back()->withInput();
        }

        $supplier = new SupplierApi;
        $supplier->name = $request->input('name');
        $supplier->driver = $request->input('driver');
        $supplier->base_url = rtrim($request->input('base_url'), '/');
        $supplier->auth_type = $request->input('auth_type');
        $supplier->rate_limit_per_minute = (int) $request->input('rate_limit_per_minute', 60);
        $supplier->priority = (int) $request->input('priority', 0);
        $supplier->is_active = true;
        $supplier->is_sandbox = (bool) $request->input('is_sandbox', false);
        $supplier->supports_direct_top_up = (bool) $request->input('supports_direct_top_up', false);
        $supplier->health_status = 'unknown';

        // Encrypt credentials
        $credentials = $request->input('credentials', []);
        if (is_array($credentials) && count($credentials) > 0) {
            $supplier->setEncryptedCredentials($credentials);
        }

        // Driver-specific settings
        $settings = $request->input('settings', []);
        $supplier->settings = is_array($settings) ? $settings : [];

        $supplier->save();

        Toastr::success(translate('supplier_added_successfully'));

        return redirect()->route('admin.supplier.list');
    }

    /**
     * Show the form for editing a supplier.
     */
    public function getUpdateView(int $id): View|RedirectResponse
    {
        $supplier = SupplierApi::findOrFail($id);
        $drivers = $this->supplierManager->getAvailableDrivers();

        // Get driver-specific config schema
        try {
            $driver = $this->supplierManager->driver($supplier);
            $credentialFields = $driver->getRequiredCredentialFields();
            $configSchema = $driver->getConfigSchema();
        } catch (\Throwable) {
            $credentialFields = [];
            $configSchema = [];
        }

        $decryptedCredentials = $supplier->getDecryptedCredentials();

        return view('admin-views.supplier.edit', compact(
            'supplier',
            'drivers',
            'credentialFields',
            'configSchema',
            'decryptedCredentials',
        ));
    }

    /**
     * Update a supplier.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'driver' => 'required|string|in:'.implode(',', $this->supplierManager->getAvailableDrivers()),
            'base_url' => 'required|url|max:500',
            'auth_type' => 'required|in:api_key,bearer_token,oauth2,basic,hmac,login_via',
            'rate_limit_per_minute' => 'required|integer|min:1|max:1000',
            'priority' => 'required|integer|min:0',
            'is_sandbox' => 'nullable|boolean',
            'supports_direct_top_up' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());

            return redirect()->back()->withInput();
        }

        $supplier = SupplierApi::findOrFail($id);
        $supplier->name = $request->input('name');
        $supplier->driver = $request->input('driver');
        $supplier->base_url = rtrim($request->input('base_url'), '/');
        $supplier->auth_type = $request->input('auth_type');
        $supplier->rate_limit_per_minute = (int) $request->input('rate_limit_per_minute', 60);
        $supplier->priority = (int) $request->input('priority', 0);
        $supplier->is_sandbox = (bool) $request->input('is_sandbox', false);
        $supplier->supports_direct_top_up = (bool) $request->input('supports_direct_top_up', false);

        // Update credentials only if provided
        $credentials = $request->input('credentials', []);
        if (is_array($credentials) && count(array_filter($credentials)) > 0) {
            $supplier->setEncryptedCredentials($credentials);
        }

        // Update settings
        $settings = $request->input('settings', []);
        if (is_array($settings)) {
            $supplier->settings = $settings;
        }

        $supplier->save();

        Toastr::success(translate('supplier_updated_successfully'));

        return redirect()->route('admin.supplier.list');
    }

    /**
     * Toggle supplier active status (AJAX).
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $supplier = SupplierApi::findOrFail($request->input('id'));
        $supplier->update(['is_active' => $request->input('status', 0)]);

        return response()->json([
            'success' => 1,
            'message' => translate('status_updated_successfully'),
        ]);
    }

    /**
     * Delete a supplier.
     */
    public function delete(Request $request): RedirectResponse
    {
        SupplierApi::findOrFail($request->input('id'))->delete();

        Toastr::success(translate('supplier_deleted_successfully'));

        return redirect()->back();
    }

    /**
     * Browse the product catalog of a supplier (AJAX).
     * Used by the mapping-add form to search and select a supplier product.
     *
     * The full catalog is cached per supplier for 15 minutes so that search
     * and pagination are instant after the first (slow) fetch from Bamboo.
     *
     * Query params:
     *   search   (string)  — filter by product name (PHP-side, case-insensitive)
     *   page     (int)     — 0-based page index
     *   size     (int)     — page size (default 50, max 100)
     */
    public function browseCatalog(int $id, Request $request): JsonResponse
    {
        $supplier = SupplierApi::findOrFail($id);

        $size = min((int) $request->get('size', 50), 100);
        $page = max((int) $request->get('page', 0), 0);
        $search = trim((string) $request->get('search', ''));

        $catalogKey = \App\Jobs\SyncSupplierCatalogJob::catalogCacheKey($supplier->id);
        $allItems = \Cache::get($catalogKey);

        if ($allItems === null) {
            return response()->json([
                'success' => false,
                'message' => 'no_cache',
            ]);
        }

        $filtered = collect($allItems);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $filtered = $filtered->filter(
                fn ($p) => str_contains(mb_strtolower((string) ($p['name'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($p['id'] ?? '')), $needle)
            );
        }

        $total = $filtered->count();
        $items = $filtered->slice($page * $size, $size)->values()->all();

        return response()->json([
            'success' => true,
            'products' => $items,
            'page' => $page,
            'size' => $size,
            'count' => count($items),
            'total' => $total,
        ]);
    }

    /**
     * Dispatch a background job to sync the supplier catalog.
     */
    public function dispatchCatalogSync(int $id, Request $request): JsonResponse
    {
        $supplier = SupplierApi::findOrFail($id);

        $syncService = app(\App\Services\Supplier\SupplierCatalogSyncService::class);
        $statusKey = \App\Jobs\SyncSupplierCatalogJob::statusCacheKey($supplier->id);
        $current = \Cache::get($statusKey);

        $freshStart = $request->boolean('fresh', false);
        $resume = $request->boolean('resume', false);

        if ($current && ($current['state'] ?? '') === 'running') {
            $startedAt = $current['started_at'] ?? null;
            $isStale = $startedAt && now()->diffInMinutes(\Carbon\Carbon::parse($startedAt)) > 20;

            if (! $isStale) {
                return response()->json([
                    'success' => true,
                    'message' => 'already_running',
                    'status' => $current,
                ]);
            }
        }

        if ($freshStart) {
            \Cache::forget(\App\Jobs\SyncSupplierCatalogJob::catalogCacheKey($supplier->id));
            \Cache::forget(\App\Services\Supplier\SupplierCatalogSyncService::checkpointCacheKey($supplier->id));
        } elseif (! $resume && ($current['state'] ?? '') === 'failed' && $syncService->hasResumableCheckpoint($supplier->id)) {
            $resume = true;
        }

        if (! $resume && ! $freshStart) {
            \Cache::forget(\App\Jobs\SyncSupplierCatalogJob::catalogCacheKey($supplier->id));
        }

        \Cache::put($statusKey, [
            'state' => 'running',
            'progress' => 0,
            'total_brands' => null,
            'pages_fetched' => 0,
            'total_pages' => 0,
            'resumed' => $resume,
            'started_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));

        \App\Jobs\SyncSupplierCatalogJob::dispatch($supplier->id, freshStart: $freshStart);

        return response()->json([
            'success' => true,
            'message' => $resume ? 'resumed' : 'dispatched',
            'resumed' => $resume,
        ]);
    }

    /**
     * Poll the status of a running catalog sync job.
     */
    public function catalogSyncStatus(int $id): JsonResponse
    {
        $supplier = SupplierApi::findOrFail($id);

        $statusKey = \App\Jobs\SyncSupplierCatalogJob::statusCacheKey($supplier->id);
        $status = \Cache::get($statusKey);

        if (! $status) {
            // Check if we already have a cached catalog (from a previous sync)
            $catalogKey = \App\Jobs\SyncSupplierCatalogJob::catalogCacheKey($supplier->id);
            $hasCatalog = \Cache::has($catalogKey);

            return response()->json([
                'success' => true,
                'status' => [
                    'state' => $hasCatalog ? 'done' : 'idle',
                    'has_catalog' => $hasCatalog,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $status,
        ]);
    }

    /**
     * Test connection to a supplier (AJAX).
     */
    public function testConnection(int $id): JsonResponse
    {
        $supplier = SupplierApi::findOrFail($id);

        try {
            $result = $this->healthMonitor->check($supplier);

            return response()->json([
                'success' => $result->isHealthy() || $result->status === 'degraded',
                'status' => $result->status,
                'latency_ms' => $result->latencyMs,
                'message' => $result->message,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'down',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
