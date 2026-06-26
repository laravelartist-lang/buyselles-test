@php
    extract(app(\App\Services\QzTraySigningService::class)->viewVariables());

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

    $thermalMessages = array_merge($qzTrayMessages, [
        'loadPrintDataFailed' => translate('thermal_load_print_data_failed') ?: 'Unable to load print data.',
        'bluetoothUnsupported' => translate('bluetooth_unsupported') ?: 'Bluetooth printing is not supported in this browser. Use Chrome or Edge on HTTPS.',
        'bluetoothNotConfigured' => translate('bluetooth_not_configured') ?: 'No Bluetooth printer saved on this browser.',
        'bluetoothPermissionRequired' => translate('bluetooth_permission_required') ?: 'Select your Bluetooth printer again to grant access.',
        'bluetoothNoWritableChar' => translate('bluetooth_no_writable_char') ?: 'No writable Bluetooth characteristic found on this printer.',
        'bluetoothUnknownDevice' => translate('bluetooth_unknown_device') ?: 'Bluetooth printer',
        'bluetoothPrintSuccess' => translate('bluetooth_print_success') ?: 'Sent to Bluetooth thermal printer (:device).',
        'bluetoothConnecting' => translate('bluetooth_connecting') ?: 'Connecting to Bluetooth printer...',
        'bluetoothConnected' => translate('bluetooth_connected') ?: 'Connected: :device',
        'bluetoothConnectFailed' => translate('bluetooth_connect_failed') ?: 'Could not connect to Bluetooth printer.',
        'bluetoothSetupComplete' => translate('bluetooth_setup_complete') ?: 'Bluetooth thermal printer saved for this browser.',
        'bluetoothSavedReady' => translate('bluetooth_saved_ready') ?: 'Bluetooth printer ready on this browser.',
        'bluetoothSetupHint' => translate('bluetooth_setup_hint') ?: 'Turn on your printer, enable Bluetooth pairing mode, then click Connect.',
        'bluetoothNoSavedDevice' => translate('bluetooth_no_saved_device') ?: 'No Bluetooth printer paired yet.',
        'bluetoothCleared' => translate('bluetooth_cleared') ?: 'Saved Bluetooth printer removed.',
        'bluetoothFallbackQz' => translate('bluetooth_fallback_qz') ?: 'Bluetooth print failed. Trying QZ Tray...',
        'bluetoothTestModeBanner' => translate('bluetooth_test_mode_banner') ?: 'Test mode is on — use the demo printer button below to simulate Bluetooth without hardware.',
        'bluetoothTestConnect' => translate('bluetooth_test_connect') ?: 'Use demo Bluetooth printer',
        'bluetoothTestPrintSuccess' => translate('bluetooth_test_print_success') ?: 'Test passed: :count receipt(s), :bytes bytes — preview opened. .raw file downloaded.',
        'bluetoothTestPassedTitle' => translate('bluetooth_test_passed_title') ?: 'Bluetooth print test passed',
        'bluetoothTestPassedSummary' => translate('bluetooth_test_passed_summary') ?: 'Validated :count receipt(s), :bytes bytes total — same data a real Bluetooth printer would receive.',
        'bluetoothTestRawFileHelp' => translate('bluetooth_test_raw_file_help') ?: 'The downloaded .raw file contains the exact bytes that would be sent over Bluetooth.',
        'bluetoothTestReceiptPreview' => translate('bluetooth_test_receipt_preview') ?: 'Receipt preview (text extracted from ESC/POS):',
        'bluetoothTestNoJobs' => translate('bluetooth_test_no_jobs') ?: 'No print jobs were returned from the server.',
        'bluetoothTestEmptyReceipt' => translate('bluetooth_test_empty_receipt') ?: 'Receipt :n is empty.',
        'bluetoothTestMissingCut' => translate('bluetooth_test_missing_cut') ?: 'Receipt :n is missing the paper cut command.',
        'bluetoothTestCheckInit' => translate('bluetooth_test_check_init') ?: 'Receipt :n starts with printer init (ESC @)',
        'bluetoothTestCheckCut' => translate('bluetooth_test_check_cut') ?: 'Receipt :n ends with paper cut command',
        'bluetoothTestCheckSize' => translate('bluetooth_test_check_size') ?: 'Receipt :n has :bytes bytes of print data',
        'bluetoothTestPaired' => translate('bluetooth_test_paired') ?: 'Demo Bluetooth printer paired for testing.',
        'bluetoothStatusReady' => translate('bluetooth_status_ready') ?: 'Bluetooth ready',
        'bluetoothQuickPrint' => translate('bluetooth_quick_print') ?: 'Quick Print',
        'bluetoothQuickPrintTitle' => translate('bluetooth_quick_print_title') ?: 'Print receipt to saved Bluetooth printer',
        'bluetoothQuickPrintFailed' => translate('bluetooth_quick_print_failed') ?: 'Bluetooth quick print failed. Try Thermal Print or Configure.',
        'bluetoothStatusDemo' => translate('bluetooth_status_demo') ?: 'Bluetooth demo',
        'bluetoothStatusSetup' => translate('bluetooth_status_setup') ?: 'Not configured',
        'bluetoothStatusUnavailable' => translate('bluetooth_status_unavailable') ?: 'Bluetooth — use Chrome/HTTPS',
    ]);

    $bluetoothThermalEnabled = (bool) config('bluetooth-thermal.enabled', true);
    $bluetoothThermalTestMode = (bool) config('bluetooth-thermal.test_mode', false);
@endphp

@include('web-views.partials._thermal-printer-setup-modal')

@if ($qzTrayAvailable)
    {{-- buyselles-qz-tray:enabled mode={{ $qzTrayMode }} --}}
    @include('web-views.partials._qz-tray-printer-modal')

    <script src="{{ dynamicAsset(path: 'public/assets/front-end/js/qz-tray-2.2.6.js') }}"></script>
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
@else
    {{-- buyselles-qz-tray:disabled keys={{ $qzTrayConfigured ? 'ok' : 'missing' }} enabled={{ $qzTrayEnabled ? 'yes' : 'no' }} --}}
@endif

<script src="{{ dynamicAsset(path: 'public/assets/front-end/js/bluetooth-thermal-print.js') }}"></script>

@include('web-views.partials._digital-code-delivery-styles')

<script>
    window.BuysellesThermalConfig = {
        mode: @json($qzTrayAvailable ? $qzTrayMode : 'preview'),
        qzAvailable: @json($qzTrayAvailable),
        bluetoothEnabled: @json($bluetoothThermalEnabled),
        bluetoothTestMode: @json($bluetoothThermalTestMode),
        testPrinterName: @json(config('bluetooth-thermal.test_printer_name')),
        debug: @json((bool) config('app.debug')),
        escPosUrl: @json(route('order.digital-codes.thermal-escpos', [], false)),
        receiptUrlBase: @json(route('order.digital-codes.receipt')),
        messages: @json($thermalMessages),
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

    function openThermalPreviewFallback(receiptUrl, orderIds) {
        openThermalPreview(receiptUrl, orderIds);
        showToast(@json(translate('opened_thermal_print_preview') ?: 'Opened thermal print preview.'));
    }

    function runQzThermalPrint(orderIds, receiptUrl) {
        var previewFallback = function () {
            openThermalPreviewFallback(receiptUrl, orderIds);
        };

        if (window.BuysellesQzTray && typeof window.BuysellesQzTray.printDigitalCodes === 'function') {
            return window.BuysellesQzTray.printDigitalCodes(orderIds, previewFallback);
        }

        previewFallback();
        return Promise.resolve();
    }

    function runBluetoothThermalPrint(orderIds, receiptUrl) {
        if (!window.BuysellesBluetoothThermal
            || typeof window.BuysellesBluetoothThermal.isAvailable !== 'function'
            || !window.BuysellesBluetoothThermal.isAvailable()) {
            return Promise.reject(new Error('Bluetooth unavailable'));
        }

        if (window.BuysellesBluetoothThermal.getSavedPrinter && window.BuysellesBluetoothThermal.getSavedPrinter()) {
            return window.BuysellesBluetoothThermal.printOrderIds(orderIds);
        }

        var pairFn = window.BuysellesBluetoothThermal.pairPrinter;

        if (window.BuysellesBluetoothThermal.isTestMode
            && window.BuysellesBluetoothThermal.isTestMode()
            && window.BuysellesBluetoothThermal.pairTestPrinter) {
            pairFn = window.BuysellesBluetoothThermal.pairTestPrinter;
        }

        return pairFn()
            .then(function () {
                return window.BuysellesBluetoothThermal.printOrderIds(orderIds);
            });
    }

    function handleThermalPrint(receiptUrl, orderIds) {
        runBluetoothThermalPrint(orderIds, receiptUrl)
            .catch(function () {
                return runQzThermalPrint(orderIds, receiptUrl);
            })
            .catch(function () {
                openThermalPreviewFallback(receiptUrl, orderIds);
            });
    }

    function openThermalSetup(orderIds) {
        if (window.BuysellesBluetoothThermal
            && typeof window.BuysellesBluetoothThermal.openSetupModal === 'function') {
            window.BuysellesBluetoothThermal.openSetupModal();
            return;
        }

        if (window.BuysellesQzTray && typeof window.BuysellesQzTray.openSetupWizard === 'function') {
            window.BuysellesQzTray.openSetupWizard(orderIds || [], null);
            return;
        }

        showToast(@json(translate('thermal_setup_unavailable') ?: 'Thermal printer setup is not available in this browser.'));
    }

    function collectCodesFromContainer(containerId) {
        var container = document.getElementById(containerId);
        if (!container) {
            return [];
        }

        var items = [];
        container.querySelectorAll('code[id^="modal-code-"], code[id^="detail-code-"], code[id^="code-"], code[id^="dyn-code-"], code[id^="aster-modal-code-"], code[id^="aster-code-"], code[id^="dyn-modal-code-"], code[id^="aster-code-dyn-"], code[id^="aster-modal-code-dyn-"], code[id^="success-code-"], code[id^="aster-success-code-"], code[id^="purchase-modal-code-"]').forEach(function (codeEl) {
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

    function setBluetoothQuickPrintBusy(btn, busy) {
        if (!btn) {
            return;
        }

        btn.disabled = !!busy;
        btn.classList.toggle('is-busy', !!busy);
    }

    document.addEventListener('click', function (event) {
        var quickPrintBtn = event.target.closest('.digital-code-action-bluetooth-quick-print');
        if (quickPrintBtn) {
            event.preventDefault();
            event.stopPropagation();

            var quickWrapper = quickPrintBtn.closest('.digital-code-delivery-actions');
            var quickOrderIds = [];

            if (quickWrapper) {
                try {
                    quickOrderIds = JSON.parse(quickWrapper.getAttribute('data-order-ids') || '[]');
                } catch (e) {
                    quickOrderIds = [];
                }
            }

            if (!quickOrderIds.length) {
                showToast(@json(translate('thermal_load_print_data_failed') ?: 'Unable to load print data.'), 'error');
                return;
            }

            var quickReceiptUrl = quickWrapper ? quickWrapper.getAttribute('data-receipt-url') : '';

            setBluetoothQuickPrintBusy(quickPrintBtn, true);

            runBluetoothThermalPrint(quickOrderIds, quickReceiptUrl)
                .catch(function (error) {
                    var message = (error && error.message)
                        ? error.message
                        : (@json(translate('bluetooth_quick_print_failed') ?: 'Bluetooth quick print failed. Try Thermal Print or Configure.'));

                    showToast(message, 'error');
                })
                .finally(function () {
                    setBluetoothQuickPrintBusy(quickPrintBtn, false);
                });

            return;
        }

        var setupBtn = event.target.closest('.digital-code-action-qz-setup');
        if (setupBtn) {
            event.preventDefault();
            event.stopPropagation();

            var wrapper = setupBtn.closest('.digital-code-delivery-actions');
            var orderIds = [];

            if (wrapper) {
                try {
                    orderIds = JSON.parse(wrapper.getAttribute('data-order-ids') || '[]');
                } catch (e) {
                    orderIds = [];
                }
            }

            openThermalSetup(orderIds);
            return;
        }

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
