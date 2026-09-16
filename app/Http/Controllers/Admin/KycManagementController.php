<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Enums\KycStatus;
use App\Enums\KycUserType;
use App\Http\Controllers\BaseController;
use App\Models\KycVerification;
use App\Services\Kyc\KycService;
use App\Services\Kyc\SumsubService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Admin visibility over KYC. Sumsub performs the actual review, so this
 * panel is a monitoring and configuration surface rather than a manual
 * approval queue.
 */
class KycManagementController extends BaseController
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly SumsubService $sumsub,
        private readonly BusinessSettingRepositoryInterface $businessSettingRepo,
    ) {}

    public function index(?Request $request, ?string $type = null): View
    {
        $filters = [
            'user_type' => $request?->input('user_type'),
            'status' => $request?->input('status'),
            'search' => $request?->input('search'),
        ];

        $verifications = KycVerification::query()
            ->when($filters['user_type'], fn ($query, $userType) => $query->where('user_type', $userType))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'], function ($query, $search) {
                return $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('external_user_id', 'like', '%'.$search.'%')
                        ->orWhere('applicant_id', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate(getWebConfig(name: 'pagination_limit') ?: 15);

        return view('admin-views.kyc.index', [
            'verifications' => $verifications,
            'filters' => $filters,
            'statuses' => KycStatus::all(),
            'userTypes' => KycUserType::all(),
            'kycEnabled' => $this->kycService->isEnabled(),
            'sumsubConfigured' => $this->sumsub->isConfigured(),
            'threshold' => $this->kycService->purchaseThreshold(),
            'customerLevel' => config('sumsub.levels.customer'),
            'vendorLevel' => config('sumsub.levels.vendor'),
        ]);
    }

    public function settings(): View
    {
        return view('admin-views.kyc.settings', [
            'kycEnabled' => $this->kycService->isEnabled(),
            'sumsubConfigured' => $this->sumsub->isConfigured(),
            'threshold' => $this->kycService->purchaseThreshold(),
            'customerLevel' => config('sumsub.levels.customer'),
            'vendorLevel' => config('sumsub.levels.vendor'),
            'webhookUrl' => url(config('sumsub.webhook_path', 'webhooks/sumsub')),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'kyc_customer_purchase_threshold' => 'required|numeric|min:0',
        ]);

        $this->businessSettingRepo->updateOrInsert(
            type: 'kyc_verification_status',
            value: $request->get('kyc_verification_status', 0),
        );

        $this->businessSettingRepo->updateOrInsert(
            type: 'kyc_customer_purchase_threshold',
            value: $request->get('kyc_customer_purchase_threshold', 100),
        );

        ToastMagic::success(translate('updated_successfully'));

        return redirect()->back();
    }

    /**
     * Re-read the applicant state from Sumsub.
     */
    public function sync(int $id): RedirectResponse
    {
        $verification = KycVerification::find($id);

        if (! $verification) {
            ToastMagic::error(translate('no_kyc_verification_found'));

            return redirect()->back();
        }

        try {
            $this->kycService->queueSyncFromSumsub($verification);
            ToastMagic::success(translate('kyc_refresh_queued'));
        } catch (Throwable $exception) {
            report($exception);
            ToastMagic::error(translate('something_went_wrong'));
        }

        return redirect()->back();
    }

    /**
     * Reset the applicant so a rejected user can submit documents again.
     */
    public function reset(int $id): RedirectResponse
    {
        $verification = KycVerification::find($id);

        if (! $verification || empty($verification->applicant_id)) {
            ToastMagic::error(translate('no_kyc_verification_found'));

            return redirect()->back();
        }

        try {
            $this->sumsub->resetApplicant($verification->applicant_id);

            $verification->status = KycStatus::RESET;
            $verification->verified_at = null;
            $verification->save();

            ToastMagic::success(translate('updated_successfully'));
        } catch (Throwable $exception) {
            report($exception);
            ToastMagic::error(translate('something_went_wrong'));
        }

        return redirect()->back();
    }
}
