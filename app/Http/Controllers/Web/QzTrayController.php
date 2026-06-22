<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DigitalCodeCustomerExportService;
use App\Services\QzTraySigningService;
use App\Services\ThermalEscPosBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class QzTrayController extends Controller
{
    public function certificate(QzTraySigningService $signingService): Response
    {
        if (! config('qz-tray.enabled')) {
            abort(404);
        }

        try {
            $certificate = $signingService->getCertificate();
        } catch (RuntimeException $exception) {
            abort(503, $exception->getMessage());
        }

        return response($certificate, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function sign(Request $request, QzTraySigningService $signingService): Response
    {
        if (! config('qz-tray.enabled')) {
            abort(404);
        }

        $toSign = (string) ($request->input('request') ?? $request->query('request', ''));

        if ($toSign === '') {
            abort(400, 'Missing request parameter.');
        }

        try {
            $signature = $signingService->sign($toSign);
        } catch (RuntimeException $exception) {
            abort(503, $exception->getMessage());
        }

        return response($signature, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function thermalEscPos(
        Request $request,
        DigitalCodeCustomerExportService $exportService,
        ThermalEscPosBuilder $escPosBuilder
    ): JsonResponse {
        if (! config('qz-tray.enabled')) {
            abort(404);
        }

        $orderIds = (array) $request->input('orderIds', []);
        $customerId = auth('customer')->id();
        $codes = $exportService->getCodesForOrders($orderIds, $customerId);

        if (empty($codes)) {
            abort(404);
        }

        $shopName = getWebConfig(name: 'company_name') ?? 'Buyselles';
        $shopTagline = 'E-Commerce Marketplace';
        $paperWidth = (int) config('qz-tray.paper_width_mm', 80);

        $rawJobs = $escPosBuilder->buildJobs($shopName, $shopTagline, $codes, $paperWidth);

        return response()->json([
            'jobs' => array_map(static fn (string $bytes): string => bin2hex($bytes), $rawJobs),
            'count' => count($rawJobs),
            'default_printer' => config('qz-tray.default_printer'),
        ]);
    }
}
