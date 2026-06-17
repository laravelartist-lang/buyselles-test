<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Enums\DisputeStatus;
use App\Enums\DisputeUserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DisputeMessageRequest;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function __construct(
        private readonly DisputeService $disputeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $seller = $request->seller;
        $vendorId = $seller['id'];
        $status = $request->get('status');

        $query = Dispute::where('vendor_id', $vendorId)
            ->with(['reason', 'order', 'buyer'])
            ->latest();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $disputes = $query->paginate(15);

        $statusCounts = [
            'all' => Dispute::where('vendor_id', $vendorId)->count(),
            'open' => Dispute::where('vendor_id', $vendorId)->where('status', DisputeStatus::OPEN)->count(),
            'vendor_response' => Dispute::where('vendor_id', $vendorId)->where('status', DisputeStatus::VENDOR_RESPONSE)->count(),
            'under_review' => Dispute::where('vendor_id', $vendorId)->where('status', DisputeStatus::UNDER_REVIEW)->count(),
            'resolved' => Dispute::where('vendor_id', $vendorId)->whereIn('status', [DisputeStatus::RESOLVED_REFUND, DisputeStatus::RESOLVED_RELEASE])->count(),
            'closed' => Dispute::where('vendor_id', $vendorId)->whereIn('status', [DisputeStatus::CLOSED, DisputeStatus::AUTO_CLOSED])->count(),
        ];

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $disputes,
            'counts' => $statusCounts,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $seller = $request->seller;
        $vendorId = $seller['id'];

        $with = ['reason', 'order', 'buyer', 'messages.buyerSender', 'messages.vendorSender', 'messages.adminSender', 'evidence', 'statusLogs'];

        $dispute = Dispute::where('id', $id)
            ->where('vendor_id', $vendorId)
            ->with($with)
            ->first();

        if (! $dispute) {
            return response()->json(['status' => 404, 'message' => 'dispute_not_found'], 404);
        }

        return response()->json([
            'status' => 200,
            'message' => 'success',
            'data' => $dispute,
        ]);
    }

    public function respond(DisputeMessageRequest $request, int $id): JsonResponse
    {
        $seller = $request->seller;
        $vendorId = $seller['id'];

        $dispute = Dispute::where('id', $id)->where('vendor_id', $vendorId)->first();

        if (! $dispute) {
            return response()->json(['status' => 404, 'message' => 'dispute_not_found'], 404);
        }

        if (! in_array($dispute->status, [DisputeStatus::OPEN, DisputeStatus::VENDOR_RESPONSE])) {
            return response()->json(['status' => 422, 'message' => 'dispute_cannot_be_responded_to_in_current_status'], 422);
        }

        $this->disputeService->vendorRespond($dispute, (int) $seller['id'], $request->message);

        // Return the newly created message
        $message = DisputeMessage::where('dispute_id', $dispute->id)
            ->latest('id')
            ->first();

        // Reload dispute with fresh relations
        $with = ['reason', 'order', 'buyer', 'messages.buyerSender', 'messages.vendorSender', 'messages.adminSender', 'evidence', 'statusLogs'];
        $dispute->load($with);

        return response()->json([
            'status' => 200,
            'message' => 'response_submitted_successfully',
            'data' => $dispute,
            'chat_message' => $message,
        ]);
    }

    public function uploadEvidence(Request $request, int $id): JsonResponse
    {
        $seller = $request->seller;
        $vendorId = $seller['id'];

        $dispute = Dispute::where('id', $id)->where('vendor_id', $vendorId)->first();

        if (! $dispute) {
            return response()->json(['status' => 404, 'message' => 'dispute_not_found'], 404);
        }

        if (in_array($dispute->status, [DisputeStatus::RESOLVED_REFUND, DisputeStatus::RESOLVED_RELEASE, DisputeStatus::CLOSED, DisputeStatus::AUTO_CLOSED])) {
            return response()->json(['status' => 422, 'message' => 'cannot_upload_evidence_for_a_closed_dispute'], 422);
        }

        $request->validate([
            'files' => 'required|array|max:5',
            'files.*' => 'file|mimes:jpg,jpeg,png,mp4',
        ]);

        try {
            $uploaded = $this->disputeService->uploadEvidenceFiles(
                dispute: $dispute,
                files: $request->file('files', []),
                uploadedBy: (int) $seller['id'],
                userType: DisputeUserType::VENDOR,
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 422,
                'message' => collect($exception->errors())->flatten()->first() ?? 'something_went_wrong',
                'errors' => $exception->errors(),
            ], 422);
        }

        // Reload dispute with evidence
        $with = ['reason', 'order', 'buyer', 'messages.buyerSender', 'messages.vendorSender', 'messages.adminSender', 'evidence', 'statusLogs'];
        $dispute->load($with);

        return response()->json([
            'status' => 201,
            'message' => 'evidence_uploaded_successfully',
            'data' => $dispute,
        ]);
    }

    public function escalate(Request $request, int $id): JsonResponse
    {
        $seller = $request->seller;
        $vendorId = $seller['id'];

        $dispute = Dispute::where('id', $id)->where('vendor_id', $vendorId)->first();

        if (! $dispute) {
            return response()->json(['status' => 404, 'message' => 'dispute_not_found'], 404);
        }

        if (! in_array($dispute->status, [DisputeStatus::OPEN, DisputeStatus::VENDOR_RESPONSE])) {
            return response()->json(['status' => 422, 'message' => 'dispute_cannot_be_escalated_in_current_status'], 422);
        }

        $dispute = $this->disputeService->escalateToAdmin($dispute, (int) $seller['id'], DisputeUserType::VENDOR);

        $with = ['reason', 'order', 'buyer', 'messages.buyerSender', 'messages.vendorSender', 'messages.adminSender', 'evidence', 'statusLogs'];
        $dispute->load($with);

        return response()->json([
            'status' => 200,
            'message' => 'dispute_escalated_to_admin_for_review',
            'data' => $dispute,
        ]);
    }
}
