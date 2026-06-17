<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\DisputeStatus;
use App\Enums\DisputeUserType;
use App\Enums\ViewPaths\Vendor\Dispute as DisputeViewPath;
use App\Http\Controllers\BaseController;
use App\Http\Requests\DisputeMessageRequest;
use App\Models\Dispute;
use App\Services\DisputeService;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class DisputeController extends BaseController
{
    public function __construct(
        private readonly DisputeService $disputeService,
    ) {}

    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse|JsonResponse
    {
        $vendorId = auth('seller')->id();
        $status = $request->get('status', $type);
        $search = $request->get('search');

        $query = Dispute::where('vendor_id', $vendorId)
            ->with(['reason', 'order', 'buyer'])
            ->latest();

        if ($status && $status !== 'all') {
            match ($status) {
                'resolved' => $query->whereIn('status', [DisputeStatus::RESOLVED_REFUND, DisputeStatus::RESOLVED_RELEASE]),
                'closed' => $query->whereIn('status', [DisputeStatus::CLOSED, DisputeStatus::AUTO_CLOSED]),
                default => $query->where('status', $status),
            };
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('order', fn ($o) => $o->where('id', $search));
            });
        }

        $disputes = $query->paginate(15)->appends($request->query());

        $statusCounts = [
            'all' => Dispute::where('vendor_id', $vendorId)->count(),
            'open' => Dispute::where('vendor_id', $vendorId)->where('status', DisputeStatus::OPEN)->count(),
            'vendor_response' => Dispute::where('vendor_id', $vendorId)->where('status', DisputeStatus::VENDOR_RESPONSE)->count(),
            'under_review' => Dispute::where('vendor_id', $vendorId)->where('status', DisputeStatus::UNDER_REVIEW)->count(),
            'resolved' => Dispute::where('vendor_id', $vendorId)->whereIn('status', [DisputeStatus::RESOLVED_REFUND, DisputeStatus::RESOLVED_RELEASE])->count(),
            'closed' => Dispute::where('vendor_id', $vendorId)->whereIn('status', [DisputeStatus::CLOSED, DisputeStatus::AUTO_CLOSED])->count(),
        ];

        return view(DisputeViewPath::INDEX[VIEW], compact('disputes', 'statusCounts', 'status', 'search'));
    }

    public function show(int $id): View
    {
        $vendorId = auth('seller')->id();

        $dispute = Dispute::where('id', $id)
            ->where('vendor_id', $vendorId)
            ->with(['reason', 'order', 'buyer', 'messages', 'evidence', 'statusLogs'])
            ->firstOrFail();

        return view(DisputeViewPath::DETAIL[VIEW], compact('dispute'));
    }

    public function respond(DisputeMessageRequest $request, int $id): RedirectResponse
    {
        $vendorId = auth('seller')->id();
        $seller = auth('seller')->user();

        $dispute = Dispute::where('id', $id)->where('vendor_id', $vendorId)->firstOrFail();

        if (! in_array($dispute->status, [DisputeStatus::OPEN, DisputeStatus::VENDOR_RESPONSE])) {
            ToastMagic::error(translate('dispute_cannot_be_responded_to_in_current_status'));

            return back();
        }

        $this->disputeService->vendorRespond($dispute, (int) $seller->id, $request->message);

        ToastMagic::success(translate('response_submitted_successfully'));

        return back();
    }

    public function uploadEvidence(Request $request, int $id): RedirectResponse
    {
        $vendorId = auth('seller')->id();
        $seller = auth('seller')->user();

        $dispute = Dispute::where('id', $id)->where('vendor_id', $vendorId)->firstOrFail();

        if (in_array($dispute->status, [DisputeStatus::RESOLVED_REFUND, DisputeStatus::RESOLVED_RELEASE, DisputeStatus::CLOSED, DisputeStatus::AUTO_CLOSED])) {
            ToastMagic::error(translate('cannot_upload_evidence_for_a_closed_dispute'));

            return back();
        }

        $request->validate([
            'files' => 'required|array|max:5',
            'files.*' => 'file|mimes:jpg,jpeg,png,mp4',
        ]);

        try {
            $this->disputeService->uploadEvidenceFiles(
                dispute: $dispute,
                files: $request->file('files', []),
                uploadedBy: (int) $seller->id,
                userType: DisputeUserType::VENDOR,
            );
        } catch (ValidationException $exception) {
            ToastMagic::error(collect($exception->errors())->flatten()->first() ?? translate('something_went_wrong'));

            return back();
        }

        ToastMagic::success(translate('evidence_uploaded_successfully'));

        return back();
    }

    public function escalate(int $id): RedirectResponse
    {
        $vendorId = auth('seller')->id();
        $seller = auth('seller')->user();

        $dispute = Dispute::where('id', $id)->where('vendor_id', $vendorId)->firstOrFail();

        if (! in_array($dispute->status, [DisputeStatus::OPEN, DisputeStatus::VENDOR_RESPONSE])) {
            ToastMagic::error(translate('dispute_cannot_be_escalated_in_current_status'));

            return back();
        }

        $this->disputeService->escalateToAdmin($dispute, (int) $seller->id, DisputeUserType::VENDOR);

        ToastMagic::success(translate('dispute_escalated_to_admin_for_review'));

        return back();
    }
}
