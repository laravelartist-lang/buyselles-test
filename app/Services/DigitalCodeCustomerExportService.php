<?php

namespace App\Services;

use App\Models\DigitalProductCode;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalCodeCustomerExportService
{
    /**
     * @return array<int, array{orderId: int, productName: string, code: string, pin: string|null, serial: string|null, expiry: string|null}>
     */
    public function getCodesForOrders(array $orderIds, ?int $customerId = null): array
    {
        $validOrderIds = $this->resolveValidOrderIds($orderIds, $customerId);

        if (empty($validOrderIds)) {
            return [];
        }

        return DigitalProductCode::query()
            ->whereIn('order_id', $validOrderIds)
            ->where('status', 'sold')
            ->with('product')
            ->get()
            ->map(fn (DigitalProductCode $code) => [
                'orderId' => $code->order_id,
                'productName' => $code->product?->name ?? translate('Digital Product'),
                'code' => $code->decryptCode(),
                'pin' => $code->decryptPin(),
                'serial' => $code->serial_number,
                'expiry' => $code->expiry_date?->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{codes: array<int, array<string, mixed>>, orderId: string, orderDate: string, customerName: string}
     */
    public function getReceiptData(array $orderIds, ?int $customerId = null): array
    {
        $validOrderIds = $this->resolveValidOrderIds($orderIds, $customerId);
        $codes = [];
        $orderId = implode(', ', $validOrderIds);
        $orderDate = now()->format('Y-m-d H:i');
        $customerName = '';

        if (! empty($validOrderIds)) {
            $records = DigitalProductCode::query()
                ->whereIn('order_id', $validOrderIds)
                ->where('status', 'sold')
                ->with(['product', 'order.customer'])
                ->get();

            foreach ($records as $record) {
                $codes[] = [
                    'productName' => $record->product?->name ?? translate('Digital Product'),
                    'code' => $record->decryptCode(),
                    'pin' => $record->decryptPin(),
                    'serial' => $record->serial_number,
                    'expiry' => $record->expiry_date?->format('Y-m-d'),
                ];

                if (empty($customerName) && $record->order) {
                    $order = $record->order;
                    if ($order->customer) {
                        $customerName = $order->customer->name ?? trim(($order->customer->f_name ?? '').' '.($order->customer->l_name ?? ''));
                    } else {
                        $billingAddress = is_object($order->billing_address_data)
                            ? $order->billing_address_data
                            : json_decode($order->billing_address_data ?? '{}');
                        $customerName = $billingAddress->contact_person_name ?? '';
                    }
                    $orderDate = $order->created_at?->format('Y-m-d H:i') ?? $orderDate;
                }
            }
        }

        return compact('codes', 'orderId', 'orderDate', 'customerName');
    }

    public function downloadPdf(array $orderIds, ?int $customerId = null): Response
    {
        $data = $this->getReceiptData($orderIds, $customerId);

        if (empty($data['codes'])) {
            abort(404);
        }

        $pdf = Pdf::loadView('web-views.order.digital-code-export-pdf', $data)
            ->setPaper('a4');

        $filename = 'digital-codes-'.str_replace(', ', '-', $data['orderId']).'.pdf';

        return $pdf->download($filename);
    }

    public function downloadExcel(array $orderIds, ?int $customerId = null): StreamedResponse
    {
        $codes = $this->getCodesForOrders($orderIds, $customerId);

        if (empty($codes)) {
            abort(404);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Digital Products');

        $sheet->fromArray([
            [translate('Order'), translate('Product'), translate('Code'), translate('PIN'), translate('Serial'), translate('Expiry')],
        ]);

        $row = 2;
        foreach ($codes as $code) {
            $sheet->fromArray([
                [
                    $code['orderId'],
                    $code['productName'],
                    $code['code'],
                    $code['pin'] ?? '',
                    $code['serial'] ?? '',
                    $code['expiry'] ?? '',
                ],
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'digital-codes-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadWord(array $orderIds, ?int $customerId = null): Response
    {
        $data = $this->getReceiptData($orderIds, $customerId);

        if (empty($data['codes'])) {
            abort(404);
        }

        $html = view('web-views.order.digital-code-export-word', $data)->render();
        $filename = 'digital-codes-'.str_replace(', ', '-', $data['orderId']).'.doc';

        return response($html, 200, [
            'Content-Type' => 'application/msword',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<int, int|string>
     */
    private function resolveValidOrderIds(array $orderIds, ?int $customerId): array
    {
        $orderIds = array_values(array_filter(array_map('intval', $orderIds)));

        if (empty($orderIds)) {
            return [];
        }

        $query = Order::query()->whereIn('id', $orderIds);

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        return $query->pluck('id')->all();
    }
}
