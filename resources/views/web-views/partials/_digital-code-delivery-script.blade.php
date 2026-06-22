@php
    use App\Services\QzTraySigningService;

    $qzSigningService = app(QzTraySigningService::class);
    $qzTrayConfigured = $qzSigningService->isConfigured();
    $qzTrayEnabled = (bool) config('qz-tray.enabled', false);
    $qzTrayMode = config('qz-tray.mode', 'preview');
    $qzTrayAvailable = $qzTrayConfigured && $qzTrayEnabled;
    $qzTrayActive = $qzTrayAvailable && in_array($qzTrayMode, ['qz', 'auto'], true);

    $qzTrayMessages = [
        'printSuccess' => translate('sent_to_thermal_printer') ?: 'Sent to thermal printer.',
        'printNow' => translate('print_now') ?: 'Print Now',
        'printing' => translate('Printing...') ?: 'Printing...',
        'printFailed' => translate('printing_failed') ?: 'Printing failed.',
        'noPrinters' => translate('no_printers_found') ?: 'No printers found on this computer.',
        'previewFallback' => translate('opened_thermal_print_preview') ?: 'Opened thermal print preview.',
        'setupLink' => translate('setup_thermal_printer') ?: 'Setup thermal printer (QZ Tray)',
        'statusChecking' => translate('qz_tray_status_checking') ?: 'Checking connection...',
        'installRequired' => translate('qz_tray_install_required') ?: 'Install and start QZ Tray to continue.',
        'clickConnect' => translate('qz_tray_click_connect') ?: 'QZ Tray is installed? Click "Connect to QZ Tray" below. If a security popup appears, choose Allow and check Remember.',
        'notConnected' => translate('qz_tray_not_connected') ?: 'Could not connect to QZ Tray.',
        'trustTimeout' => translate('qz_tray_trust_timeout') ?: 'Connection timed out. Click Connect again, approve the QZ Tray popup with Allow and Remember (check behind this window).',
        'signFailed' => translate('qz_tray_sign_failed') ?: 'QZ Tray signing failed on the server. Check /qz-tray/sign returns 200, then try again.',
        'resetSiteManager' => translate('qz_tray_reset_site_manager') ?: 'If connection still fails, right-click the QZ Tray icon -> Advanced -> Site Manager, remove this site, then connect again.',
        'trustDenied' => translate('qz_tray_trust_denied') ?: 'QZ Tray blocked this website. Reset allowed sites in QZ Tray if needed, then click Connect and choose Allow / Remember.',
        'unreachable' => translate('qz_tray_unreachable') ?: 'Browser cannot reach QZ Tray. Confirm the QZ Tray icon is in your system tray, then click Connect again.',
        'hostnameMismatch' => translate('qz_tray_hostname_mismatch') ?: 'Open this site at :host so QZ Tray can trust the connection.',
        'connectedContinue' => translate('qz_tray_connected_continue') ?: 'QZ Tray connected. Continue to select your printer.',
        'connectedSaved' => translate('qz_tray_connected_saved') ?: 'Connected — saved printer ready.',
        'connectedReady' => translate('qz_tray_connected_ready') ?: 'Connected — choose your thermal printer.',
        'connecting' => translate('connecting_qz_tray') ?: 'Connecting to QZ Tray...',
        'connectFailed' => translate('qz_tray_connect_failed') ?: 'Could not connect to QZ Tray.',
        'loadingPrinters' => translate('loading_printers') ?: 'Loading printers...',
        'savePrinterHint' => translate('qz_tray_save_printer_hint') ?: 'Your printer choice is saved in this browser for next time.',
        'savedPrinterHint' => translate('qz_tray_saved_printer_hint') ?: 'Saved printer on this browser:',
        'connectionTimeout' => translate('qz_tray_connection_timeout') ?: 'Connection timed out.',
        'alreadyConnected' => translate('qz_tray_already_connected') ?: 'Already connected to QZ Tray.',
        'testPrintSuccess' => translate('qz_tray_test_print_success') ?: 'Test print saved :count receipt(s) to :file',
        'testValidateSuccess' => translate('qz_tray_test_validate_success') ?: 'Test OK: :count receipt(s) validated (cut command present). ESC/POS was not sent to a printer.',
        'testPrinterHint' => translate('qz_tray_test_printer_hint') ?: 'Test mode validates ESC/POS output (one cut per code). Optional sandbox file: ~/.qz/sandbox/:file',
        'setupComplete' => translate('qz_tray_setup_complete') ?: 'Thermal printer saved: :printer. You can print receipts directly next time.',
        'selectPrinterFirst' => translate('qz_tray_select_printer_first') ?: 'Select a thermal printer before clicking Done.',
        'notConnectedSetup' => translate('qz_tray_not_connected_setup') ?: 'QZ Tray is not connected. Click Connect and allow access in the QZ popup first.',
        'trustBlockedRetry' => translate('qz_tray_trust_blocked_retry') ?: 'If you blocked access, click Connect again. When the QZ Tray popup appears, choose Allow and Remember. Blocked sites are not listed in Site Manager until allowed once.',
    ];
@endphp

@if ($qzTrayAvailable)
    @include('web-views.partials._qz-tray-printer-modal')

    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.6/qz-tray.js"></script>
    <script>
        window.BuysellesQzTrayConfig = {
            enabled: true,
            mode: @json($qzTrayMode),
            debug: @json((bool) config('app.debug')),
            certificateUrl: @json(route('qz-tray.certificate', [], false)),
            signUrl: @json(route('qz-tray.sign', [], false)),
            certificateHost: @json(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            escPosUrl: @json(route('order.digital-codes.thermal-escpos', [], false)),
            defaultPrinter: @json(config('qz-tray.default_printer')),
            connectTimeoutMs: @json((int) config('qz-tray.connect_timeout_seconds', 120) * 1000),
            testMode: @json((bool) config('qz-tray.test_mode', false)),
            testPrinterName: @json(config('qz-tray.test_printer_name')),
            testOutputFile: @json(config('qz-tray.test_output_file')),
            messages: @json($qzTrayMessages),
        };
    </script>
    <script src="{{ dynamicAsset(path: 'public/assets/front-end/js/qz-tray-digital-print.js') }}"></script>
@endif

<script>
    window.BuysellesThermalConfig = {
        mode: @json($qzTrayAvailable ? $qzTrayMode : 'preview'),
        qzAvailable: @json($qzTrayAvailable),
        receiptUrlBase: @json(route('order.digital-codes.receipt')),
    };
</script>

<script>
(function () {
    'use strict';

    function buildExportUrl(baseUrl, format, orderIds, extraParams) {
        var url = baseUrl.replace('__FORMAT__', format);
        var params = (orderIds || []).map(function (id) {
            return 'orderIds[]=' + encodeURIComponent(id);
        });

        if (extraParams) {
            Object.keys(extraParams).forEach(function (key) {
                params.push(encodeURIComponent(key) + '=' + encodeURIComponent(extraParams[key]));
            });
        }

        return url + (url.indexOf('?') >= 0 ? '&' : '?') + params.join('&');
    }

    function buildReceiptUrl(receiptUrl, orderIds, extraParams) {
        var params = (orderIds || []).map(function (id) {
            return 'orderIds[]=' + encodeURIComponent(id);
        });
        params.push('preview=1');

        if (extraParams) {
            Object.keys(extraParams).forEach(function (key) {
                params.push(encodeURIComponent(key) + '=' + encodeURIComponent(extraParams[key]));
            });
        }

        return receiptUrl + '?' + params.join('&');
    }

    function openThermalPreview(receiptUrl, orderIds) {
        window.open(buildReceiptUrl(receiptUrl, orderIds), '_blank', 'noopener,noreferrer');
    }

    function showToast(message) {
        if (typeof toastr !== 'undefined') {
            toastr.info(message);
            return;
        }

        alert(message);
    }

    function handleThermalPrint(receiptUrl, orderIds) {
        var thermalConfig = window.BuysellesThermalConfig || {};
        var mode = thermalConfig.mode || 'preview';
        var previewFallback = function () {
            openThermalPreview(receiptUrl, orderIds);
        };

        if (!thermalConfig.qzAvailable || !window.BuysellesQzTray) {
            previewFallback();
            return;
        }

        if (mode === 'preview') {
            window.BuysellesQzTray.openThermalChoice(orderIds, previewFallback);
            return;
        }

        if (mode === 'auto') {
            window.BuysellesQzTray.tryAutoPrint(orderIds, previewFallback);
            return;
        }

        window.BuysellesQzTray.printDigitalCodes(orderIds, previewFallback);
    }

    function collectCodesFromContainer(containerId) {
        var container = document.getElementById(containerId);
        if (!container) {
            return [];
        }

        var items = [];
        container.querySelectorAll('code[id^="modal-code-"], code[id^="detail-code-"], code[id^="code-"], code[id^="dyn-code-"], code[id^="aster-modal-code-"], code[id^="aster-code-"], code[id^="dyn-modal-code-"], code[id^="aster-code-dyn-"], code[id^="aster-modal-code-dyn-"]').forEach(function (codeEl) {
            var block = codeEl.closest('.border, .digital-code-item, .bg-light');
            var productName = '';
            if (block) {
                var nameEl = block.querySelector('.fw-bold, .fw-semibold, .text-muted');
                productName = nameEl ? nameEl.innerText.trim().split('—')[0].trim() : '';
            }

            var metaEl = block ? block.querySelector('.text-muted, .fs-12, .fs-13') : null;

            items.push({
                productName: productName || '{{ translate('Digital Product') }}',
                code: codeEl.innerText.trim(),
                meta: metaEl ? metaEl.innerText.trim() : '',
            });
        });

        return items;
    }

    function buildShareText(containerId) {
        var codes = collectCodesFromContainer(containerId);
        if (!codes.length) {
            return '{{ translate('Your_Digital_Codes') }}';
        }

        var lines = ['{{ translate('Your_Digital_Codes') }}:', ''];
        codes.forEach(function (item) {
            lines.push('{{ translate('Product') }}: ' + item.productName);
            lines.push('{{ translate('Code') }}: ' + item.code);
            if (item.meta) {
                lines.push(item.meta);
            }
            lines.push('--------------------');
        });

        return lines.join('\n');
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        return Promise.resolve();
    }

    function scrollToCodes(viewTarget) {
        var target = document.getElementById(viewTarget);
        if (!target) {
            return;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        target.classList.add('digital-code-view-highlight');
        setTimeout(function () {
            target.classList.remove('digital-code-view-highlight');
        }, 1800);
    }

    document.addEventListener('click', function (event) {
        var actionEl = event.target.closest('.digital-code-action');
        if (!actionEl) {
            return;
        }

        var wrapper = actionEl.closest('.digital-code-delivery-actions');
        if (!wrapper) {
            return;
        }

        event.preventDefault();

        var orderIds = [];
        try {
            orderIds = JSON.parse(wrapper.getAttribute('data-order-ids') || '[]');
        } catch (e) {
            orderIds = [];
        }

        var receiptUrl = wrapper.getAttribute('data-receipt-url');
        var exportBaseUrl = wrapper.getAttribute('data-export-base-url');
        var viewTarget = wrapper.getAttribute('data-view-target');
        var codesContainer = wrapper.getAttribute('data-codes-container');

        if (actionEl.classList.contains('digital-code-action-thermal')) {
            handleThermalPrint(receiptUrl, orderIds);
            return;
        }

        if (actionEl.classList.contains('digital-code-action-a4')) {
            window.open(buildExportUrl(exportBaseUrl, 'pdf', orderIds, { inline: 1 }), '_blank', 'noopener,noreferrer');
            return;
        }

        if (actionEl.classList.contains('digital-code-action-excel')) {
            window.location.href = buildExportUrl(exportBaseUrl, 'excel', orderIds);
            return;
        }

        if (actionEl.classList.contains('digital-code-action-word')) {
            window.location.href = buildExportUrl(exportBaseUrl, 'word', orderIds);
            return;
        }

        if (actionEl.classList.contains('digital-code-action-view')) {
            scrollToCodes(viewTarget);
            return;
        }

        if (actionEl.classList.contains('digital-code-action-share')) {
            var shareText = buildShareText(codesContainer);

            if (navigator.share) {
                navigator.share({
                    title: '{{ translate('Your_Digital_Codes') }}',
                    text: shareText,
                }).catch(function () {
                    copyText(shareText).then(function () {
                        showToast('{{ translate('Copied_to_clipboard') ?: 'Copied to clipboard' }}');
                    });
                });
                return;
            }

            copyText(shareText).then(function () {
                showToast('{{ translate('Copied_to_clipboard') ?: 'Codes copied — paste into WhatsApp, Telegram, etc.' }}');
            });
        }
    });
}());
</script>

<style>
    .digital-code-view-highlight {
        outline: 2px solid rgba(6, 60, 147, .35);
        outline-offset: 4px;
        border-radius: 8px;
        transition: outline-color .3s ease;
    }
</style>
