<?php

namespace App\Http\Controllers\Kyc;

use App\Http\Controllers\Controller;
use App\Services\Kyc\KycService;
use App\Services\Kyc\SumsubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives applicant status notifications from Sumsub.
 *
 * This endpoint is the single place where verification outcomes land, which
 * is what keeps the web storefront and both mobile apps in sync.
 */
class SumsubWebhookController extends Controller
{
    public function __construct(
        private readonly SumsubService $sumsub,
        private readonly KycService $kycService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        if (! $this->sumsub->verifyWebhookSignature(
            $rawBody,
            $request->header('X-Payload-Digest'),
            $request->header('X-Payload-Digest-Alg'),
        )) {
            Log::warning('Rejected a Sumsub webhook with an invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'invalid payload'], 400);
        }

        $type = $payload['type'] ?? 'unknown';

        $this->kycService->handleWebhookPayload($payload);

        Log::info('Processed a Sumsub webhook', [
            'type' => $type,
            'external_user_id' => $payload['externalUserId'] ?? null,
            'review_answer' => $payload['reviewResult']['reviewAnswer'] ?? null,
        ]);

        return response()->json(['message' => 'ok']);
    }
}
