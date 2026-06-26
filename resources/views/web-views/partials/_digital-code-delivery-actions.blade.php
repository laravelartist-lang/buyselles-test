@php
    extract(app(\App\Services\QzTraySigningService::class)->viewVariables());

    $orderIds = $orderIds ?? [];
    $viewTarget = $viewTarget ?? 'digitalCodesSection';
    $codesContainer = $codesContainer ?? 'digitalCodesPrintArea';
    $layout = $layout ?? ((($compact ?? false) ? 'compact' : 'cards'));
    $context = $context ?? 'default';
    $showHeading = $showHeading ?? ($layout === 'cards');
    $exportBaseUrl = route('order.digital-codes.export', ['format' => '__FORMAT__']);
    $receiptUrl = route('order.digital-codes.receipt');
@endphp

<div class="digital-code-delivery-actions digital-code-delivery-actions--{{ $layout }} digital-code-delivery-actions--{{ $context }}"
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
            <div class="digital-code-delivery-option digital-code-delivery-option--thermal">
                <div class="digital-code-thermal-card">
                    <button type="button"
                            class="digital-code-delivery-option__main digital-code-action digital-code-action-thermal">
                        <span class="digital-code-delivery-option__icon"><i class="fa fa-print"></i></span>
                        <span class="digital-code-delivery-option__content">
                            <span class="digital-code-delivery-option__title">{{ translate('thermal_print') ?: 'Thermal Print' }}</span>
                            <span class="digital-code-delivery-option__subtitle">{{ translate('thermal_print_subtitle') ?: 'Bluetooth first, then QZ Tray, then browser preview' }}</span>
                        </span>
                        <span class="digital-code-delivery-option__arrow"><i class="fa fa-chevron-right"></i></span>
                    </button>
                    <div class="digital-code-thermal-toolbar">
                        <div class="digital-code-thermal-toolbar__status-group">
                            <span class="digital-code-bluetooth-status digital-code-bluetooth-status-pill" title="{{ translate('bluetooth_thermal_print') ?: 'Bluetooth thermal printer' }}">
                                <i class="fa fa-bluetooth-b"></i>
                                <span class="digital-code-bluetooth-status__text">{{ translate('bluetooth_status_setup') ?: 'Not configured' }}</span>
                            </span>
                            <button type="button"
                                    class="digital-code-action-bluetooth-quick-print d-none"
                                    title="{{ translate('bluetooth_quick_print_title') ?: 'Print receipt to saved Bluetooth printer' }}">
                                <i class="fa fa-print"></i>
                                <span>{{ translate('bluetooth_quick_print') ?: 'Quick Print' }}</span>
                            </button>
                        </div>
                        <button type="button" class="digital-code-action-qz-setup digital-code-thermal-toolbar__configure" title="{{ translate('configure_thermal_printer') ?: 'Configure printer' }}">
                            <i class="fa fa-cog"></i>
                            <span>{{ translate('configure_thermal_printer') ?: 'Configure printer' }}</span>
                        </button>
                    </div>
                </div>
            </div>

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
            <div class="digital-code-thermal-compact">
                <div class="digital-code-thermal-compact__actions btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-dark digital-code-action digital-code-action-thermal">
                        <i class="fa fa-print"></i> {{ translate('thermal_print') ?: 'Thermal' }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary digital-code-action-qz-setup" title="{{ translate('configure_thermal_printer') ?: 'Configure printer' }}">
                        <i class="fa fa-cog"></i>
                    </button>
                </div>
                <div class="digital-code-thermal-compact__status-row">
                    <span class="digital-code-bluetooth-status digital-code-bluetooth-status-pill digital-code-bluetooth-status-pill--compact">
                        <i class="fa fa-bluetooth-b"></i>
                        <span class="digital-code-bluetooth-status__text">{{ translate('bluetooth_status_setup') ?: 'Not configured' }}</span>
                    </span>
                    <button type="button"
                            class="digital-code-action-bluetooth-quick-print digital-code-action-bluetooth-quick-print--compact d-none"
                            title="{{ translate('bluetooth_quick_print_title') ?: 'Print receipt to saved Bluetooth printer' }}">
                        <i class="fa fa-print"></i>
                        <span>{{ translate('bluetooth_quick_print') ?: 'Quick Print' }}</span>
                    </button>
                </div>
            </div>
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
