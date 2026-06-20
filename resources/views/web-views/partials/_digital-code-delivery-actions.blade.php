@php
    $orderIds = $orderIds ?? [];
    $viewTarget = $viewTarget ?? 'digitalCodesSection';
    $codesContainer = $codesContainer ?? 'digitalCodesPrintArea';
    $layout = $layout ?? ((($compact ?? false) ? 'compact' : 'cards'));
    $showHeading = $showHeading ?? ($layout === 'cards');
    $exportBaseUrl = route('order.digital-codes.export', ['format' => '__FORMAT__']);
    $receiptUrl = route('order.digital-codes.receipt');
@endphp

<div class="digital-code-delivery-actions digital-code-delivery-actions--{{ $layout }}"
     data-order-ids='@json(array_values($orderIds))'
     data-receipt-url="{{ $receiptUrl }}"
     data-export-base-url="{{ $exportBaseUrl }}"
     data-view-target="{{ $viewTarget }}"
     data-codes-container="{{ $codesContainer }}">

    @if ($showHeading)
        <div class="digital-code-delivery-actions__heading mb-3">
            <h6 class="mb-1 fw-bold">{{ translate('digital_delivery_options') ?: 'Delivery Options' }}</h6>
            <p class="mb-0 text-muted fs-13">
                {{ translate('select_delivery_method') ?: 'How would you like to receive your digital products?' }}
            </p>
        </div>
    @endif

    @if ($layout === 'cards')
        <div class="digital-code-delivery-actions__grid">
            <button type="button"
                    class="digital-code-delivery-option digital-code-action digital-code-action-thermal">
                <span class="digital-code-delivery-option__icon"><i class="fa fa-print"></i></span>
                <span class="digital-code-delivery-option__content">
                    <span class="digital-code-delivery-option__title">{{ translate('thermal_print') ?: 'Thermal Print' }}</span>
                    <span class="digital-code-delivery-option__subtitle">{{ translate('thermal_print_subtitle') ?: 'Print via QZ Tray to your thermal printer' }}</span>
                </span>
                <span class="digital-code-delivery-option__arrow"><i class="fa fa-chevron-right"></i></span>
            </button>

            <button type="button"
                    class="digital-code-delivery-option digital-code-action digital-code-action-a4">
                <span class="digital-code-delivery-option__icon"><i class="fa fa-file-pdf-o"></i></span>
                <span class="digital-code-delivery-option__content">
                    <span class="digital-code-delivery-option__title">{{ translate('print_receipt_a4') ?: 'Print (A4 PDF)' }}</span>
                    <span class="digital-code-delivery-option__subtitle">{{ translate('generate_a4_pdf_receipt') ?: 'Generate A4 PDF receipt' }}</span>
                </span>
                <span class="digital-code-delivery-option__arrow"><i class="fa fa-chevron-right"></i></span>
            </button>

            <button type="button"
                    class="digital-code-delivery-option digital-code-action digital-code-action-view">
                <span class="digital-code-delivery-option__icon"><i class="fa fa-eye"></i></span>
                <span class="digital-code-delivery-option__content">
                    <span class="digital-code-delivery-option__title">{{ translate('view_code_on_screen') ?: 'View Code on Screen' }}</span>
                    <span class="digital-code-delivery-option__subtitle">{{ translate('view_code_immediately') ?: 'Immediate display' }}</span>
                </span>
                <span class="digital-code-delivery-option__arrow"><i class="fa fa-chevron-right"></i></span>
            </button>

            <button type="button"
                    class="digital-code-delivery-option digital-code-action digital-code-action-share">
                <span class="digital-code-delivery-option__icon"><i class="fa fa-share-alt"></i></span>
                <span class="digital-code-delivery-option__content">
                    <span class="digital-code-delivery-option__title">{{ translate('send_via_social_media') ?: 'Send via Social Media' }}</span>
                    <span class="digital-code-delivery-option__subtitle">{{ translate('whatsapp_telegram_etc') ?: 'WhatsApp, Telegram, etc.' }}</span>
                </span>
                <span class="digital-code-delivery-option__arrow"><i class="fa fa-chevron-right"></i></span>
            </button>

            <button type="button"
                    class="digital-code-delivery-option digital-code-action digital-code-action-excel">
                <span class="digital-code-delivery-option__icon"><i class="fa fa-file-excel-o"></i></span>
                <span class="digital-code-delivery-option__content">
                    <span class="digital-code-delivery-option__title">{{ translate('download_as_excel') ?: 'Download as Excel' }}</span>
                    <span class="digital-code-delivery-option__subtitle">.xlsx {{ translate('file_format') ?: 'file format' }}</span>
                </span>
                <span class="digital-code-delivery-option__arrow"><i class="fa fa-chevron-right"></i></span>
            </button>

            <button type="button"
                    class="digital-code-delivery-option digital-code-action digital-code-action-word">
                <span class="digital-code-delivery-option__icon"><i class="fa fa-file-word-o"></i></span>
                <span class="digital-code-delivery-option__content">
                    <span class="digital-code-delivery-option__title">{{ translate('download_as_word') ?: 'Download as Word' }}</span>
                    <span class="digital-code-delivery-option__subtitle">.doc {{ translate('file_format') ?: 'file format' }}</span>
                </span>
                <span class="digital-code-delivery-option__arrow"><i class="fa fa-chevron-right"></i></span>
            </button>
        </div>
    @else
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-sm btn-outline-dark digital-code-action digital-code-action-thermal">
                <i class="fa fa-print"></i> {{ translate('thermal_print') ?: 'Thermal' }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger digital-code-action digital-code-action-a4">
                <i class="fa fa-file-pdf-o"></i> {{ translate('print_receipt_a4') ?: 'A4 PDF' }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary digital-code-action digital-code-action-view">
                <i class="fa fa-eye"></i> {{ translate('View') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-success digital-code-action digital-code-action-share">
                <i class="fa fa-share-alt"></i> {{ translate('Share') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-success digital-code-action digital-code-action-excel">
                <i class="fa fa-file-excel-o"></i> {{ translate('Excel') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary digital-code-action digital-code-action-word">
                <i class="fa fa-file-word-o"></i> {{ translate('Word') }}
            </button>
        </div>
    @endif
</div>

<style>
    .digital-code-delivery-actions__grid {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .digital-code-delivery-option {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #fff;
        text-align: start;
        cursor: pointer;
        transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
    }

    .digital-code-delivery-option:hover {
        border-color: rgba(6, 60, 147, .25);
        box-shadow: 0 4px 14px rgba(0, 0, 0, .08);
        transform: translateY(-1px);
    }

    .digital-code-delivery-option__icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(6, 60, 147, .08);
        color: #063c93;
        font-size: 18px;
        flex-shrink: 0;
    }

    .digital-code-delivery-option__content {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .digital-code-delivery-option__title {
        font-size: 15px;
        font-weight: 700;
        color: #212529;
    }

    .digital-code-delivery-option__subtitle {
        font-size: 12px;
        color: #6c757d;
    }

    .digital-code-delivery-option__arrow {
        color: #adb5bd;
        font-size: 12px;
        flex-shrink: 0;
    }

    .digital-code-delivery-actions--compact .btn i {
        margin-right: 5px;
    }
</style>
