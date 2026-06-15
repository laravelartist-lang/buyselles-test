<?php

namespace App\Http\Controllers\RestAPI\v3\seller;

use App\Exports\DigitalProductCodeTemplateExport;
use App\Exports\ProductCodeTemplateExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessDigitalCodeImportJob;
use App\Models\DigitalProductCode;
use App\Models\Product;
use App\Services\DigitalProductCodeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DigitalCodeController extends Controller
{
    /**
     * Download the bulk import Excel template.
     */
    public function downloadBulkTemplate(Request $request): BinaryFileResponse|JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        $export = new DigitalProductCodeTemplateExport(sellerId: $sellerId);

        return Excel::download($export, 'digital-code-bulk-template-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Upload a bulk Excel file and dispatch a background job to process it.
     */
    public function uploadBulkImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $this->formatValidationErrors($validator)], 422);
        }

        $storedPath = $request->file('excel_file')->store('digital-code-imports', 'local');
        $absolutePath = storage_path('app/'.$storedPath);

        $seller = $request->seller;
        $importedBy = $seller->name ?? 'Vendor';
        $sellerId = (int) $seller->id;

        ProcessDigitalCodeImportJob::dispatch($absolutePath, $importedBy, 0, $sellerId)
            ->onQueue('default');

        return response()->json([
            'message' => translate('Your_file_has_been_queued_for_processing._You_will_be_notified_by_email_once_it_is_complete.'),
        ], 200);
    }

    /**
     * Get all digital codes for a specific product, with stats.
     */
    public function getProductCodes(Request $request, int $productId): JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        $product = $this->validateProductOwnership($productId, $sellerId);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $codes = DigitalProductCode::query()
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 50), ['*'], 'page', $request->input('offset', 1));

        $stats = [
            'available' => DigitalProductCode::where('product_id', $productId)->where('status', 'available')->where('is_active', true)->count(),
            'inactive' => DigitalProductCode::where('product_id', $productId)->where('is_active', false)->count(),
            'reserved' => DigitalProductCode::where('product_id', $productId)->where('status', 'reserved')->count(),
            'sold' => DigitalProductCode::where('product_id', $productId)->where('status', 'sold')->count(),
            'expired' => DigitalProductCode::where('product_id', $productId)->where('status', 'expired')->count(),
            'total' => DigitalProductCode::where('product_id', $productId)->count(),
        ];

        $expiringCount = DigitalProductCode::where('product_id', $productId)
            ->where('status', 'available')
            ->where('expiry_date', '<=', now()->addDays(7))
            ->where('expiry_date', '>', now())
            ->count();

        $formattedCodes = $codes->map(function (DigitalProductCode $code) {
            return [
                'id' => $code->id,
                'serial_number' => $code->serial_number,
                'expiry_date' => $code->expiry_date?->format('Y-m-d'),
                'status' => $code->status,
                'is_active' => $code->is_active,
                'source' => $code->source,
                'created_at' => $code->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'total_size' => $codes->total(),
            'limit' => (int) $request->input('limit', 50),
            'offset' => (int) $request->input('offset', 1),
            'codes' => $formattedCodes,
            'stats' => $stats,
            'expiring_count' => $expiringCount,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
        ], 200);
    }

    /**
     * Download the per-product code import template.
     */
    public function downloadProductTemplate(Request $request, int $productId): BinaryFileResponse|JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        $product = $this->validateProductOwnership($productId, $sellerId);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $export = new ProductCodeTemplateExport(productName: $product->name);

        return Excel::download($export, 'codes-'.str($product->name)->slug().'-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Upload an Excel file for a specific product and process it immediately.
     */
    public function uploadProductImport(Request $request, int $productId, DigitalProductCodeService $service): JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        $product = $this->validateProductOwnership($productId, $sellerId);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $validator = Validator::make($request->all(), [
            'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $this->formatValidationErrors($validator)], 422);
        }

        $spreadsheet = IOFactory::load($request->file('excel_file')->getPathname());
        $rows = $spreadsheet->getActiveSheet()->toArray();

        array_shift($rows); // remove header

        $processed = 0;
        $duplicates = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $pin = trim((string) ($row[0] ?? ''));
            $serial = trim((string) ($row[1] ?? ''));
            $expiryRaw = trim((string) ($row[2] ?? ''));

            if ($pin === '' || strtolower($pin) === 'pin') {
                $skipped++;

                continue;
            }

            $expiry = null;
            if ($expiryRaw !== '') {
                try {
                    $expiry = Carbon::parse($expiryRaw)->startOfDay();
                } catch (\Throwable $e) {
                    $expiry = null;
                }
            }

            $result = $service->addToPool(
                productId: $product->id,
                plainCode: $pin,
                serialNumber: $serial ?: null,
                expiryDate: $expiry?->toDateString(),
            );

            if ($result) {
                $processed++;
            } else {
                $duplicates++;
            }
        }

        return response()->json([
            'message' => translate('Import completed'),
            'summary' => [
                'processed' => $processed,
                'duplicates' => $duplicates,
                'skipped' => $skipped,
            ],
        ], 200);
    }

    /**
     * Add a single code manually for a product.
     */
    public function addSingleCode(Request $request, int $productId, DigitalProductCodeService $service): JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        $product = $this->validateProductOwnership($productId, $sellerId);
        if ($product instanceof JsonResponse) {
            return $product;
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $this->formatValidationErrors($validator)], 422);
        }

        $result = $service->addToPool(
            productId: $product->id,
            plainCode: $request->input('code'),
            serialNumber: $request->input('serial_number') ?: null,
            expiryDate: $request->input('expiry_date') ?: null,
            source: 'manual',
        );

        if ($result === null) {
            return response()->json([
                'message' => translate('This_code_or_serial_number_already_exists'),
            ], 409);
        }

        return response()->json([
            'message' => translate('Code_added_successfully'),
            'code' => [
                'id' => $result->id,
                'status' => $result->status,
            ],
        ], 200);
    }

    /**
     * Toggle the active/inactive status of a digital code.
     */
    public function toggleCodeStatus(Request $request, int $id, DigitalProductCodeService $service): JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        try {
            $code = $service->toggleActive($id, $sellerId);

            return response()->json([
                'success' => true,
                'is_active' => $code->is_active,
                'message' => $code->is_active
                    ? translate('Code_activated_successfully')
                    : translate('Code_deactivated_successfully'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Decrypt and return the plain-text PIN for a code.
     */
    public function decryptCode(Request $request, int $id): JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        $code = DigitalProductCode::where('seller_id', $sellerId)->findOrFail($id);

        try {
            $plain = $code->decryptCode();
        } catch (\Exception) {
            return response()->json(['error' => translate('decryption_failed_code_may_be_corrupted')], 500);
        }

        return response()->json([
            'id' => $code->id,
            'pin' => $plain,
            'serial' => $code->serial_number,
        ]);
    }

    /**
     * Delete a digital code.
     */
    public function deleteCode(Request $request, int $id, DigitalProductCodeService $service): JsonResponse
    {
        $seller = $request->seller;
        $sellerId = (int) $seller->id;

        try {
            $service->deleteCode($id, $sellerId);

            return response()->json([
                'success' => true,
                'message' => translate('Code_deleted_successfully'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Validate that the product exists, is digital, and belongs to the seller.
     */
    private function validateProductOwnership(int $productId, int $sellerId): Product|JsonResponse
    {
        $product = Product::query()
            ->where('product_type', 'digital')
            ->where('added_by', 'seller')
            ->where('user_id', $sellerId)
            ->find($productId);

        if (! $product) {
            return response()->json(['message' => translate('Product not found or access denied')], 404);
        }

        return $product;
    }

    /**
     * Format validation errors into a consistent structure.
     */
    private function formatValidationErrors(\Illuminate\Support\MessageBag|Validator $validator): array
    {
        $errors = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            $errors[$field] = $messages[0];
        }

        return $errors;
    }
}
