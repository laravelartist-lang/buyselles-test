<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\BaseController;
use App\Models\PartnerApiLog;
use App\Models\ResellerApiKey;
use App\Services\ResellerApiService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PartnerApiController extends BaseController
{
    /**
     * Developer dashboard — shows current API key status, masked credentials, and IP whitelist.
     */
    public function index(?Request $request = null, ?string $type = null): View
    {
        $sellerId = auth('seller')->id();
        $seller = auth('seller')->user();

        $apiKey = ResellerApiKey::query()
            ->where('seller_id', $sellerId)
            ->latest()
            ->first();

        $recentLogs = $apiKey
            ? PartnerApiLog::where('reseller_api_key_id', $apiKey->id)
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        return view('vendor-views.partner-api.index', compact('seller', 'apiKey', 'recentLogs'));
    }

    /**
     * Submit a new API key request — creates a key with status=pending awaiting admin approval.
     */
    public function requestKey(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'request_note' => 'nullable|string|max:500',
        ]);

        $sellerId = auth('seller')->id();
        $seller = auth('seller')->user();

        // Each vendor can only have one active/pending key at a time
        $existingKey = ResellerApiKey::where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if ($existingKey) {
            ToastMagic::warning(message: translate('you_already_have_an_api_key'));

            return redirect()->back();
        }

        $generated = ResellerApiService::generateKeyPair(
            userId: null,
            name: $request->input('name'),
            sellerId: $sellerId,
            requestNote: $request->input('request_note'),
        );

        // Flash raw values once so the view can show them — they are never retrievable again
        session()->flash('new_api_key', $generated['raw_api_key']);
        session()->flash('new_api_secret', $generated['raw_api_secret']);

        ToastMagic::success(message: translate('api_key_request_submitted_awaiting_approval'));

        return redirect()->route('vendor.developer.index');
    }

    /**
     * Regenerate the vendor's API key+secret, preserving permissions/rate-limit/IPs.
     */
    public function regenerateKey(Request $request): RedirectResponse
    {
        $sellerId = auth('seller')->id();

        $apiKey = ResellerApiKey::where('seller_id', $sellerId)
            ->whereIn('status', ['active', 'inactive'])
            ->latest()
            ->first();

        if (! $apiKey) {
            ToastMagic::error(message: translate('no_active_key_to_regenerate'));

            return redirect()->route('vendor.developer.index');
        }

        $generated = ResellerApiService::regenerateCredentials($apiKey);

        session()->flash('new_api_key', $generated['raw_api_key']);
        session()->flash('new_api_secret', $generated['raw_api_secret']);

        ToastMagic::success(message: translate('api_key_regenerated_successfully'));

        return redirect()->route('vendor.developer.index');
    }

    /**
     * Update the IP whitelist for the vendor's API key.
     */
    public function updateIps(Request $request): RedirectResponse
    {
        $request->validate([
            'allowed_ips' => 'nullable|string',
        ]);

        $sellerId = auth('seller')->id();
        $apiKey = ResellerApiKey::where('seller_id', $sellerId)->latest()->firstOrFail();

        // Parse newline/comma-separated IPs, strip empties
        $rawIps = $request->input('allowed_ips', '');
        $ips = collect(preg_split('/[\r\n,]+/', $rawIps))
            ->map(fn ($ip) => trim($ip))
            ->filter(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false)
            ->values()
            ->all();

        $apiKey->update(['allowed_ips' => empty($ips) ? null : $ips]);

        // Clear cached key so new IP list takes effect immediately
        Cache::forget("reseller_key:{$apiKey->api_key}");

        ToastMagic::success(message: translate('ip_whitelist_updated'));

        return redirect()->back();
    }

    /**
     * Revoke / delete the vendor's API key.
     */
    public function revokeKey(Request $request): RedirectResponse
    {
        $sellerId = auth('seller')->id();

        $apiKey = ResellerApiKey::where('seller_id', $sellerId)->latest()->first();

        if ($apiKey) {
            Cache::forget("reseller_key:{$apiKey->api_key}");
            $apiKey->delete();
        }

        ToastMagic::success(message: translate('api_key_revoked_successfully'));

        return redirect()->route('vendor.developer.index');
    }

    /**
     * Paginated API access log for the vendor's key.
     */
    public function logs(Request $request): View
    {
        $sellerId = auth('seller')->id();

        $apiKey = ResellerApiKey::where('seller_id', $sellerId)->latest()->first();

        $logs = $apiKey
            ? PartnerApiLog::where('reseller_api_key_id', $apiKey->id)
                ->when($request->get('status'), fn ($q, $s) => $q->where('http_status', $s))
                ->latest()
                ->paginate(getWebConfig('pagination_limit'))
            : collect()->paginate(getWebConfig('pagination_limit'));

        return view('vendor-views.partner-api.logs', compact('apiKey', 'logs'));
    }
}
