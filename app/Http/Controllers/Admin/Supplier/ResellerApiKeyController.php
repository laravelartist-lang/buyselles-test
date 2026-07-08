<?php

namespace App\Http\Controllers\Admin\Supplier;

use App\Http\Controllers\BaseController;
use App\Models\PartnerApiLog;
use App\Models\ResellerApiKey;
use App\Services\ResellerApiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class ResellerApiKeyController extends BaseController
{
    /**
     * Display all reseller API keys.
     */
    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $search = $request?->get('searchValue');
        $filterStatus = $request?->get('status');

        $keys = ResellerApiKey::query()
            ->with('user', 'seller')
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('seller', fn ($s) => $s->where('f_name', 'like', "%{$search}%")->orWhere('l_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($filterStatus, fn ($q) => $q->where('status', $filterStatus))
            ->orderByDesc('id')
            ->paginate(getWebConfig(name: 'pagination_limit'));

        $pendingCount = ResellerApiKey::pending()->count();

        return view('admin-views.reseller.list', compact('keys', 'search', 'filterStatus', 'pendingCount'));
    }

    /**
     * Generate a new API key pair for a customer.
     */
    public function generate(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'name' => 'required|string|max:100',
        ]);

        $keyRecord = ResellerApiService::generateKeyPair(
            userId: (int) $request->input('user_id'),
            name: $request->input('name'),
        );

        // Admin-generated keys are immediately active
        $keyRecord->update(['status' => 'active', 'is_active' => true]);

        Toastr::success(translate('api_key_generated_successfully'));

        return redirect()->route('admin.reseller-keys.list')->with([
            'new_key_id' => $keyRecord->id,
            'raw_api_key' => $keyRecord->raw_api_key,
            'raw_api_secret' => $keyRecord->raw_api_secret,
        ]);
    }

    /**
     * Approve a pending API key request.
     */
    public function approve(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:reseller_api_keys,id',
        ]);

        $key = ResellerApiKey::findOrFail($request->input('id'));
        $key->update([
            'status' => 'active',
            'is_active' => true,
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
            'admin_note' => $request->input('admin_note'),
        ]);

        Cache::forget("reseller_key:{$key->api_key}");

        Toastr::success(translate('partner_api_key_approved'));

        return redirect()->back();
    }

    /**
     * Reject (deactivate) a pending or active API key.
     */
    public function reject(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:reseller_api_keys,id',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $key = ResellerApiKey::findOrFail($request->input('id'));
        $key->update([
            'status' => 'inactive',
            'is_active' => false,
            'admin_note' => $request->input('admin_note'),
        ]);

        Cache::forget("reseller_key:{$key->api_key}");

        Toastr::success(translate('partner_api_key_rejected'));

        return redirect()->back();
    }

    /**
     * Toggle active status of an API key.
     */
    public function toggleStatus(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:reseller_api_keys,id',
        ]);

        $key = ResellerApiKey::findOrFail($request->input('id'));

        if ($key->status === 'pending') {
            Toastr::warning(translate('approve_or_reject_pending_key_first'));

            return redirect()->back();
        }

        $newStatus = $key->status === 'active' ? 'inactive' : 'active';
        $key->update(['status' => $newStatus, 'is_active' => $newStatus === 'active']);

        Cache::forget("reseller_key:{$key->api_key}");

        $label = $newStatus === 'active' ? translate('activated') : translate('deactivated');
        Toastr::success(translate('API_key').' '.$label);

        return redirect()->back();
    }

    /**
     * Permanently delete an API key.
     */
    public function delete(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:reseller_api_keys,id',
        ]);

        $key = ResellerApiKey::findOrFail($request->input('id'));
        Cache::forget("reseller_key:{$key->api_key}");
        $key->delete();

        Toastr::success(translate('api_key_deleted_successfully'));

        return redirect()->route('admin.reseller-keys.list');
    }

    /**
     * Show the edit form for an API key.
     */
    public function edit(int $id): View
    {
        $key = ResellerApiKey::with('user', 'seller')->findOrFail($id);

        $allPermissions = [
            'products.list' => 'Products — List',
            'orders.create' => 'Orders — Create',
            'orders.view' => 'Orders — View',
            'balance.view' => 'Balance — View',
        ];

        return view('admin-views.reseller.edit', compact('key', 'allPermissions'));
    }

    /**
     * Update settings for an API key.
     */
    public function updateKey(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'rate_limit_per_minute' => 'required|integer|min:1|max:600',
            'allowed_ips' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:products.list,orders.create,orders.view,balance.view',
            'status' => 'required|in:active,inactive',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $key = ResellerApiKey::findOrFail($id);

        // Parse IPs — newline or comma separated, strip blanks, deduplicate
        $rawIps = (string) $request->input('allowed_ips', '');
        $allowedIps = collect(preg_split('/[\r\n,;\s]+/', $rawIps))
            ->map(fn ($ip) => trim($ip))
            ->filter(fn ($ip) => $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP))
            ->unique()
            ->values()
            ->all();

        $key->update([
            'name' => $request->input('name'),
            'rate_limit_per_minute' => (int) $request->input('rate_limit_per_minute'),
            'allowed_ips' => $allowedIps,
            'permissions' => $request->input('permissions', []),
            'status' => $request->input('status'),
            'is_active' => $request->input('status') === 'active',
            'admin_note' => $request->input('admin_note'),
        ]);

        Cache::forget("reseller_key:{$key->api_key}");

        Toastr::success(translate('api_key_updated_successfully'));

        return redirect()->route('admin.reseller-keys.edit', $id);
    }

    /**
     * Show paginated logs for a specific API key.
     */
    public function keyLogs(Request $request, int $id): View
    {
        $key = ResellerApiKey::with('user', 'seller')->findOrFail($id);

        $logs = PartnerApiLog::query()
            ->where('reseller_api_key_id', $id)
            ->when($request->method_filter, fn ($q) => $q->where('method', strtoupper($request->method_filter)))
            ->when($request->status_filter, function ($q) use ($request) {
                $range = $request->status_filter;
                if ($range === '2xx') {
                    $q->whereBetween('http_status', [200, 299]);
                } elseif ($range === '4xx') {
                    $q->whereBetween('http_status', [400, 499]);
                } elseif ($range === '5xx') {
                    $q->whereBetween('http_status', [500, 599]);
                } else {
                    $q->where('http_status', (int) $range);
                }
            })
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('id')
            ->paginate(50);

        return view('admin-views.reseller.logs', compact('key', 'logs'));
    }

    /**
     * Show the API documentation page.
     */
    public function apiDocs(): View
    {
        return view('admin-views.reseller.api-docs');
    }

    public function downloadApiDocsPdf(): Response
    {
        $partnerApiBaseUrl = url('/api/v1/partner');
        $partnerApiHost = parse_url(config('app.url'), PHP_URL_HOST) ?? config('app.url');

        $pdf = Pdf::loadView('admin-views.reseller.api-docs-pdf', [
            'generatedAt' => now()->format('Y-m-d H:i'),
            'partnerApiBaseUrl' => $partnerApiBaseUrl,
            'partnerApiHost' => $partnerApiHost,
        ])->setPaper('a4', 'portrait');

        $filename = 'buyselles-partner-api-documentation-v1.pdf';

        return $pdf->download($filename);
    }

    /**
     * Top up the partner wallet balance for a specific API key.
     */
    public function topUpWallet(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:99999',
            'note' => 'nullable|string|max:255',
        ]);

        $key = ResellerApiKey::findOrFail($id);

        $amount = round((float) $request->input('amount'), 2);
        $key->increment('wallet_balance', $amount);

        Cache::forget("reseller_key:{$key->api_key}");

        Toastr::success(translate('wallet_topped_up_successfully') ?: "Wallet topped up by \${$amount} successfully.");

        return redirect()->route('admin.reseller-keys.edit', $id);
    }
}
