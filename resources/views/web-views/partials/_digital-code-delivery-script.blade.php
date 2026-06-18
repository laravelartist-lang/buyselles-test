<script>
(function () {
    'use strict';

    function buildExportUrl(baseUrl, format, orderIds) {
        var url = baseUrl.replace('__FORMAT__', format);
        var params = orderIds.map(function (id) {
            return 'orderIds[]=' + encodeURIComponent(id);
        }).join('&');
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + params;
    }

    function buildReceiptUrl(receiptUrl, orderIds) {
        var params = orderIds.map(function (id) {
            return 'orderIds[]=' + encodeURIComponent(id);
        }).join('&');
        return receiptUrl + '?' + params;
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

        var orderIds = [];
        try {
            orderIds = JSON.parse(wrapper.getAttribute('data-order-ids') || '[]');
        } catch (e) {
            orderIds = [];
        }

        var receiptUrl = wrapper.getAttribute('data-receipt-url');
        var exportBaseUrl = wrapper.getAttribute('data-export-base-url');
        var viewTarget = wrapper.getAttribute('data-view-target');

        if (actionEl.classList.contains('digital-code-action-print')) {
            event.preventDefault();
            window.open(buildReceiptUrl(receiptUrl, orderIds), '_blank');
            return;
        }

        if (actionEl.classList.contains('digital-code-action-pdf')) {
            event.preventDefault();
            window.location.href = buildExportUrl(exportBaseUrl, 'pdf', orderIds);
            return;
        }

        if (actionEl.classList.contains('digital-code-action-word')) {
            event.preventDefault();
            window.location.href = buildExportUrl(exportBaseUrl, 'word', orderIds);
            return;
        }

        if (actionEl.classList.contains('digital-code-action-excel')) {
            event.preventDefault();
            window.location.href = buildExportUrl(exportBaseUrl, 'excel', orderIds);
            return;
        }

        if (actionEl.classList.contains('digital-code-action-view')) {
            event.preventDefault();
            var target = document.getElementById(viewTarget);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
}());
</script>
