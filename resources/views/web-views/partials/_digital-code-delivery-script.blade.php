@php
    use App\Services\QzTraySigningService;

    $qzSigningService = app(QzTraySigningService::class);
    $qzTrayConfigured = $qzSigningService->isConfigured();
    $qzTrayMode = config('qz-tray.mode', 'preview');
    $qzTrayActive = config('qz-tray.enabled') && $qzTrayConfigured && in_array($qzTrayMode, ['qz', 'auto'], true);
@endphp

@if ($qzTrayActive)
    @include('web-views.partials._qz-tray-printer-modal')

    <script src="https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.js"></script>
    <script>
        window.BuysellesQzTrayConfig = {
            enabled: true,
            mode: @json($qzTrayMode),
            certificateUrl: @json(route('qz-tray.certificate')),
            signUrl: @json(route('qz-tray.sign')),
            escPosUrl: @json(route('order.digital-codes.thermal-escpos')),
            defaultPrinter: @json(config('qz-tray.default_printer')),
            connectTimeoutMs: @json((int) config('qz-tray.connect_timeout_seconds', 4) * 1000),
            messages: {
                printSuccess: @json(translate('sent_to_thermal_printer') ?: 'Sent to thermal printer.'),
                printNow: @json(translate('print_now') ?: 'Print Now'),
                printing: @json(translate('Printing...') ?: 'Printing...'),
                noPrinters: @json(translate('no_printers_found') ?: 'No printers found on this computer.'),
                previewFallback: @json(translate('opened_thermal_print_preview') ?: 'Opened thermal print preview.'),
            },
        };
    </script>
    <script src="{{ dynamicAsset(path: 'public/assets/front-end/js/qz-tray-digital-print.js') }}"></script>
@endif

<script>
    window.BuysellesThermalConfig = {
        mode: @json($qzTrayActive ? $qzTrayMode : 'preview'),
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
        var mode = (window.BuysellesThermalConfig && window.BuysellesThermalConfig.mode) || 'preview';
        var previewFallback = function () {
            openThermalPreview(receiptUrl, orderIds);
        };

        if (mode === 'preview' || !window.BuysellesQzTray) {
            previewFallback();
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
