@php
    $orderIds = $orderIds ?? [];
    $viewTarget = $viewTarget ?? 'digitalCodesSection';
    $codesContainer = $codesContainer ?? 'digitalCodesPrintArea';
    $compact = $compact ?? false;
    $exportBaseUrl = route('order.digital-codes.export', ['format' => '__FORMAT__']);
    $receiptUrl = route('order.digital-codes.receipt');
@endphp

<div class="digital-code-delivery-actions {{ $compact ? 'digital-code-delivery-actions--compact' : '' }}"
     data-order-ids='@json(array_values($orderIds))'
     data-receipt-url="{{ $receiptUrl }}"
     data-export-base-url="{{ $exportBaseUrl }}"
     data-view-target="{{ $viewTarget }}"
     data-codes-container="{{ $codesContainer }}">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button"
                class="btn btn-sm btn-primary digital-code-action digital-code-action-print"
                title="{{ translate('Print') }}">
            <i class="fa fa-print"></i>{{ translate('Print') }}
        </button>
        <a href="#"
           class="btn btn-sm btn-outline-danger digital-code-action digital-code-action-pdf"
           title="{{ translate('Download_PDF') }}">
            <i class="fa fa-file-pdf-o"></i>{{ translate('PDF') }}
        </a>
        <a href="#"
           class="btn btn-sm btn-outline-primary digital-code-action digital-code-action-word"
           title="{{ translate('Download_Word') }}">
            <i class="fa fa-file-word-o"></i>{{ translate('Word') }}
        </a>
        <a href="#"
           class="btn btn-sm btn-outline-success digital-code-action digital-code-action-excel"
           title="{{ translate('Download_Excel') }}">
            <i class="fa fa-file-excel-o"></i>{{ translate('Excel') }}
        </a>
        <button type="button"
                class="btn btn-sm btn-outline-dark digital-code-action digital-code-action-view"
                title="{{ translate('View') }}">
            <i class="fa fa-eye"></i>{{ translate('View') }}
        </button>
    </div>
</div>

<style>
    .digital-code-delivery-actions .btn {
        font-size: 12px;
        font-weight: 600;
        border-radius: 6px;
    }

    .digital-code-delivery-actions .btn i {
        margin-right: 5px;
    }

    .digital-code-delivery-actions--compact .btn {
        padding: 4px 8px;
    }
</style>
