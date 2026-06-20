@extends('theme-views.layouts.app')

@section('title', translate('order_Complete').' | '.$web_config['company_name'].' '.translate('ecommerce'))

@section('content')
    @php
        $hasDigitalCodes = !empty($digitalCodes) && count($digitalCodes) > 0;
        $hasPendingSupplierCodes = $hasPendingSupplierCodes ?? false;
        $showDigitalCodesSection = $hasDigitalCodes || $hasPendingSupplierCodes;
        $orderIdsStr     = isset($order_ids) && count($order_ids) > 0
            ? '#' . implode(', #', $order_ids)
            : '';
        $shopName  = $web_config['company_name'] ?? 'Buyselles';
        $shopPhone = getWebConfig(name: 'company_phone') ?? '';
    @endphp

    <main class="main-content d-flex flex-column gap-3 py-3 mb-5">

        {{-- Success banner --}}
        <div class="container">
            <div class="card">
                <div class="card-body p-md-5">
                    <div class="row justify-content-center">
                        <div class="col-xl-6 col-md-10">
                            <div class="text-center d-flex flex-column align-items-center gap-3">
                                <img width="46" src="{{ theme_asset('assets/img/icons/check.png') }}" class="dark-support" alt="">
                                <h3 class="text-capitalize">
                                    @if (isset($isNewCustomerInSession) && $isNewCustomerInSession)
                                        {{ translate('Order_Placed_&_Account_Created_Successfully') }}!
                                    @else
                                        {{ translate('Order_Placed_Successfully') }}!
                                    @endif
                                </h3>
                                @if ($orderIdsStr)
                                    <p class="text-muted mb-0">
                                        {{ translate('Order_ID') }}: <strong class="text-primary">{{ $orderIdsStr }}</strong>
                                    </p>
                                @endif
                                <p class="text-muted">
                                    {{ translate('thank_you_for_your_order') }}!
                                    {{ translate('your_order_has_been_processed') }}.
                                    {{ translate('check_your_email_to_get_the_order_id_and_details') }}.
                                </p>
                                <div class="d-flex flex-wrap justify-content-center gap-3">
                                    @if ($showDigitalCodesSection)
                                        <button type="button" class="btn btn-success text-capitalize"
                                            data-bs-toggle="modal" data-bs-target="#orderSuccessModal">
                                            <i class="fa fa-key me-1"></i>
                                            {{ translate('View_Your_Codes_&_Receipt') }}
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-primary text-capitalize"
                                            data-bs-toggle="modal" data-bs-target="#orderSuccessModal">
                                            <i class="fa fa-receipt me-1"></i>
                                            {{ translate('View_Receipt') }}
                                        </button>
                                    @endif
                                    <a href="{{ route('home') }}"
                                        class="btn btn-outline-primary bg-primary-light border-transparent text-capitalize">
                                        {{ translate('continue_shopping') }}
                                    </a>
                                    <a href="{{ route('track-order.index') }}" class="btn btn-primary text-capitalize">
                                        {{ translate('track_order') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Digital Codes Card --}}
        @if ($showDigitalCodesSection)
            <div class="container">
                <div class="card border-success">
                    <div class="card-header" style="background:#0f9d58; color:#fff;">
                        <h6 class="mb-0">
                            <i class="fa fa-key me-2"></i>{{ translate('Your_Digital_Codes') }}
                        </h6>
                        <small style="opacity:.85;">{{ translate('Codes_have_been_emailed_to_you_too._Keep_them_safe.') }}</small>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        @include('web-views.partials._digital-code-delivery-actions', [
                            'orderIds' => $order_ids ?? [],
                            'viewTarget' => 'codes-card-container',
                            'codesContainer' => 'codes-card-container',
                            'layout' => 'cards',
                        ])
                        <hr class="my-1">
                        <div id="codes-card-loading" class="text-center py-4" style="{{ $hasDigitalCodes ? 'display:none;' : '' }}">
                            <div class="spinner-border text-success" role="status"></div>
                            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">
                                {{ translate('Retrieving_your_digital_codes') }}…
                            </p>
                        </div>
                        <div id="codes-card-timeout" class="text-center py-3" style="display:none;">
                            <i class="fa fa-envelope text-primary" style="font-size:28px;"></i>
                            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">
                                {{ translate('Your_codes_are_taking_longer_than_expected._They_will_be_sent_to_your_email_shortly.') }}
                            </p>
                        </div>
                        <div id="codes-card-container">
                            @foreach ($digitalCodes as $item)
                                <div class="border rounded p-3">
                                    <p class="fw-semibold text-muted mb-1" style="font-size:.82rem;">
                                        <i class="fa fa-box me-1"></i>{{ $item['productName'] }}
                                        @if ($item['orderId'])
                                            &mdash; <span class="text-secondary">{{ translate('Order') }} #{{ $item['orderId'] }}</span>
                                        @endif
                                    </p>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <code class="fs-4 fw-bold bg-light px-3 py-2 rounded border flex-grow-1 text-center"
                                            id="aster-code-{{ $loop->index }}"
                                            style="letter-spacing:4px;font-family:'Courier New',monospace;word-break:break-all;">
                                            {{ $item['code'] }}
                                        </code>
                                        <button type="button" class="btn btn-sm btn-outline-primary card-copy-btn"
                                            data-target="aster-code-{{ $loop->index }}">
                                            <i class="fa fa-copy"></i> {{ translate('Copy') }}
                                        </button>
                                    </div>
                                    @if (!empty($item['pin']) || !empty($item['serial']) || !empty($item['expiry']))
                                        <p class="text-muted mb-0 mt-1" style="font-size:.76rem;">
                                            @if (!empty($item['pin'])) <strong>{{ translate('PIN') }}:</strong> <code class="text-dark fw-semibold">{{ $item['pin'] }}</code> @endif
                                            @if (!empty($item['serial'])) &nbsp;<strong>S/N:</strong> {{ $item['serial'] }} @endif
                                            @if (!empty($item['expiry'])) &nbsp;<strong>Exp:</strong> {{ $item['expiry'] }} @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <p id="codes-card-warning" class="text-danger mb-0" style="font-size:.8rem;{{ !$hasDigitalCodes ? 'display:none;' : '' }}">
                            <i class="fa fa-exclamation-triangle me-1"></i>
                            {{ translate('Warning:_Do_not_share_your_codes_with_anyone.') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

    </main>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- Purchase Success Modal                                         --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="orderSuccessModal" tabindex="-1"
        aria-labelledby="asterOrderSuccessModalLabel"
        data-bs-backdrop="{{ $showDigitalCodesSection ? 'static' : 'true' }}"
        data-bs-keyboard="{{ $showDigitalCodesSection ? 'false' : 'true' }}"
        aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $showDigitalCodesSection ? 'modal-lg' : 'modal-md' }}">
            <div class="modal-content border-0 shadow">

                {{-- Header --}}
                <div class="modal-header border-0 p-0">
                    <div class="w-100 text-center py-4 px-3"
                        style="background:linear-gradient(135deg,#063c93 0%,#0f9d58 100%);">
                        <div style="font-size:46px;line-height:1;" class="mb-2">🎉</div>
                        <h5 class="text-white fw-bold mb-1" id="asterOrderSuccessModalLabel">
                            {{ translate('Thank_You_For_Your_Purchase') }}!
                        </h5>
                        @if ($orderIdsStr)
                            <p class="text-white mb-0" style="font-size:.82rem;opacity:.9;">
                                {{ translate('Order_ID') }}: <strong>{{ $orderIdsStr }}</strong>
                                &bull; {{ now()->format('d M Y, H:i') }}
                            </p>
                        @endif
                        <p class="mb-0 mt-1" style="font-size:.78rem;opacity:.75;color:#fff;">
                            {{ translate('We_have_received_your_order_and_will_process_it_shortly.') }}
                            @if ($orderIdsStr)
                                {{ translate('Keep_your_Order_ID_handy_for_tracking.') }}
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Body --}}
                <div class="modal-body pt-3 pb-2">
                    @if ($showDigitalCodesSection)
                        <div id="codes-modal-alert" class="alert alert-warning py-2 px-3 mb-3" style="font-size:.84rem;{{ !$hasDigitalCodes ? 'display:none;' : '' }}">
                            <i class="fa fa-exclamation-triangle me-1"></i>
                            <strong>{{ translate('Important') }}:</strong>
                            {{ translate('Copy_or_print_your_codes_below._They_are_also_sent_to_your_email.') }}
                        </div>

                        @include('web-views.partials._digital-code-delivery-actions', [
                            'orderIds' => $order_ids ?? [],
                            'viewTarget' => 'codes-modal-container',
                            'codesContainer' => 'codes-modal-container',
                            'layout' => 'cards',
                        ])

                        <hr class="my-3">

                        <div id="codes-modal-loading" class="text-center py-4" style="{{ $hasDigitalCodes ? 'display:none;' : '' }}">
                            <div class="spinner-border text-success" role="status"></div>
                            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">
                                {{ translate('Retrieving_your_digital_codes') }}…
                            </p>
                        </div>
                        <div id="codes-modal-timeout" class="text-center py-3" style="display:none;">
                            <i class="fa fa-envelope text-primary" style="font-size:28px;"></i>
                            <p class="text-muted mt-2 mb-0" style="font-size:.85rem;">
                                {{ translate('Your_codes_are_taking_longer_than_expected._They_will_be_sent_to_your_email_shortly.') }}
                            </p>
                        </div>

                        <div id="codes-modal-container">
                            @foreach ($digitalCodes as $idx => $item)
                                <div class="border rounded p-3 mb-3 bg-light">
                                    <p class="text-muted mb-1 fw-semibold" style="font-size:.8rem;">
                                        <i class="fa fa-box me-1"></i>{{ $item['productName'] }}
                                        @if ($item['orderId'])
                                            &mdash; <span class="text-secondary">{{ translate('Order') }} #{{ $item['orderId'] }}</span>
                                        @endif
                                    </p>
                                    <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                        <code class="fs-4 fw-bold bg-white px-3 py-2 rounded border flex-grow-1 text-center"
                                            id="aster-modal-code-{{ $idx }}"
                                            style="letter-spacing:4px;font-family:'Courier New',monospace;word-break:break-all;">
                                            {{ $item['code'] }}
                                        </code>
                                        <button type="button" class="btn btn-sm btn-outline-primary modal-copy-btn"
                                            data-target="aster-modal-code-{{ $idx }}">
                                            <i class="fa fa-copy"></i> {{ translate('Copy') }}
                                        </button>
                                    </div>
                                    @if (!empty($item['pin']) || !empty($item['serial']) || !empty($item['expiry']))
                                        <p class="text-muted mb-0 mt-1" style="font-size:.76rem;">
                                            @if (!empty($item['pin'])) <strong>{{ translate('PIN') }}:</strong> <code class="text-dark fw-semibold">{{ $item['pin'] }}</code> @endif
                                            @if (!empty($item['serial'])) &nbsp;<strong>S/N:</strong> {{ $item['serial'] }} @endif
                                            @if (!empty($item['expiry'])) &nbsp;<strong>Exp:</strong> {{ $item['expiry'] }} @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div id="codes-modal-confirm-wrap" class="form-check p-3 border rounded mt-2" style="background:#fffde7;{{ !$hasDigitalCodes ? 'display:none;' : '' }}">
                            <input class="form-check-input" type="checkbox" id="asterConfirmCodes">
                            <label class="form-check-label fw-semibold" for="asterConfirmCodes" style="cursor:pointer;">
                                <i class="fa fa-shield-alt me-1 text-success"></i>
                                {{ translate('I_confirm_I_have_successfully_copied_/_saved_my_code(s)') }}
                            </label>
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="fa fa-check-circle text-success" style="font-size:42px;"></i>
                            <p class="mt-3 text-muted" style="font-size:.9rem;">
                                {{ translate('Your_order_is_confirmed._You_will_receive_an_email_with_tracking_details.') }}
                            </p>
                            @if ($orderIdsStr)
                                <div class="alert alert-light border mt-3" style="font-size:.87rem;">
                                    <strong>{{ translate('Order_ID') }}:</strong>
                                    <span class="text-primary fw-bold">{{ $orderIdsStr }}</span>
                                    <br><small class="text-muted">{{ now()->format('d M Y, H:i') }}</small>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="modal-footer border-0 pt-1 flex-wrap gap-2 justify-content-end">
                    @if (!$showDigitalCodesSection)
                        <a href="{{ route('track-order.index') }}" class="btn btn-outline-primary">
                            <i class="fa fa-map-marker me-1"></i>{{ translate('Track_Order') }}
                        </a>
                    @endif
                    <button type="button" id="asterCloseBtn"
                        class="btn btn-primary {{ $showDigitalCodesSection ? 'disabled' : '' }}"
                        data-bs-dismiss="modal"
                        @if($showDigitalCodesSection) disabled @endif>
                        {{ $showDigitalCodesSection ? translate('Close_(save_codes_first)') : translate('Close') }}
                    </button>
                </div>

            </div>
        </div>
    </div>

@endsection

@push('script')
<script>
(function () {
    'use strict';

    var hasDigitalCodes        = {{ $hasDigitalCodes ? 'true' : 'false' }};
    var hasPendingCodes        = {{ $hasPendingSupplierCodes ? 'true' : 'false' }};
    var showDigitalCodesSection = {{ $showDigitalCodesSection ? 'true' : 'false' }};
    var orderIds               = @json($order_ids ?? []);
    var pollUrl                = '{{ route("check-digital-codes-status") }}';
    var pollInterval           = null;
    var pollStartTime          = null;
    var POLL_TIMEOUT_MS        = 180000; // 3 minutes
    var POLL_INTERVAL_MS       = 5000;   // 5 seconds
    var dynamicCodeIndex       = 0;

    // Auto-open modal on page load
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('orderSuccessModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(modalEl, {
                backdrop: showDigitalCodesSection ? 'static' : true,
                keyboard: !showDigitalCodesSection
            }).show();
        }

        if (hasPendingCodes && !hasDigitalCodes) {
            pollStartTime = Date.now();
            pollInterval = setInterval(pollForCodes, POLL_INTERVAL_MS);
        }
    });

    function pollForCodes() {
        if (!orderIds.length) return;

        var params = orderIds.map(function(id) { return 'orderIds[]=' + encodeURIComponent(id); }).join('&');

        fetch(pollUrl + '?' + params, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.codes && data.codes.length > 0) {
                renderCodesDynamic(data.codes);
            }
            if (!data.pending) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            if (data.pending && (Date.now() - pollStartTime) >= POLL_TIMEOUT_MS) {
                clearInterval(pollInterval);
                pollInterval = null;
                showTimeoutMessage();
            }
        })
        .catch(function() {
            if ((Date.now() - pollStartTime) >= POLL_TIMEOUT_MS) {
                clearInterval(pollInterval);
                pollInterval = null;
                showTimeoutMessage();
            }
        });
    }

    function renderCodesDynamic(codes) {
        hideElement('codes-card-loading');
        hideElement('codes-modal-loading');
        showElement('codes-modal-alert');
        showElement('codes-card-warning');
        showElement('codes-modal-confirm-wrap');

        var cardContainer    = document.getElementById('codes-card-container');
        var modalContainer   = document.getElementById('codes-modal-container');

        if (cardContainer)    cardContainer.innerHTML = '';
        if (modalContainer)   modalContainer.innerHTML = '';

        codes.forEach(function(item) {
            var codeId      = 'aster-code-dyn-' + dynamicCodeIndex;
            var modalCodeId = 'aster-modal-code-dyn-' + dynamicCodeIndex;
            dynamicCodeIndex++;

            var pinHtml = '';
            if (item.pin)    pinHtml += '<strong>PIN:</strong> <code class="text-dark fw-semibold">' + esc(item.pin) + '</code> ';
            if (item.serial) pinHtml += '&nbsp;<strong>S/N:</strong> ' + esc(item.serial) + ' ';
            if (item.expiry) pinHtml += '&nbsp;<strong>Exp:</strong> ' + esc(item.expiry) + ' ';
            var pinBlock = pinHtml ? '<p class="text-muted mb-0 mt-1" style="font-size:.76rem;">' + pinHtml + '</p>' : '';

            // Card
            if (cardContainer) {
                cardContainer.insertAdjacentHTML('beforeend',
                    '<div class="border rounded p-3">' +
                        '<p class="fw-semibold text-muted mb-1" style="font-size:.82rem;">' +
                            '<i class="fa fa-box me-1"></i>' + esc(item.productName) +
                            (item.orderId ? ' &mdash; <span class="text-secondary">Order #' + esc(String(item.orderId)) + '</span>' : '') +
                        '</p>' +
                        '<div class="d-flex align-items-center gap-2 flex-wrap">' +
                            '<code class="fs-4 fw-bold bg-light px-3 py-2 rounded border flex-grow-1 text-center" id="' + codeId + '" style="letter-spacing:4px;font-family:\'Courier New\',monospace;word-break:break-all;">' + esc(item.code) + '</code>' +
                            '<button type="button" class="btn btn-sm btn-outline-primary card-copy-btn" data-target="' + codeId + '"><i class="fa fa-copy"></i> Copy</button>' +
                        '</div>' +
                        pinBlock +
                    '</div>'
                );
            }

            // Modal
            if (modalContainer) {
                modalContainer.insertAdjacentHTML('beforeend',
                    '<div class="border rounded p-3 mb-3 bg-light">' +
                        '<p class="text-muted mb-1 fw-semibold" style="font-size:.8rem;">' +
                            '<i class="fa fa-box me-1"></i>' + esc(item.productName) +
                            (item.orderId ? ' &mdash; <span class="text-secondary">Order #' + esc(String(item.orderId)) + '</span>' : '') +
                        '</p>' +
                        '<div class="d-flex align-items-center gap-2 flex-wrap mt-1">' +
                            '<code class="fs-4 fw-bold bg-white px-3 py-2 rounded border flex-grow-1 text-center" id="' + modalCodeId + '" style="letter-spacing:4px;font-family:\'Courier New\',monospace;word-break:break-all;">' + esc(item.code) + '</code>' +
                            '<button type="button" class="btn btn-sm btn-outline-primary modal-copy-btn" data-target="' + modalCodeId + '"><i class="fa fa-copy"></i> Copy</button>' +
                        '</div>' +
                        pinBlock +
                    '</div>'
                );
            }

        });

        hasDigitalCodes = true;
    }

    function showTimeoutMessage() {
        hideElement('codes-card-loading');
        hideElement('codes-modal-loading');
        showElement('codes-card-timeout');
        showElement('codes-modal-timeout');
    }

    function hideElement(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function showElement(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = '';
    }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function legacyCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }

    // Copy buttons (modal + card)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.modal-copy-btn, .card-copy-btn');
        if (!btn) return;
        var text = document.getElementById(btn.getAttribute('data-target')).innerText.trim();
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.resolve(legacyCopy(text)))
            .then(function () {
                btn.innerHTML = '<i class="fa fa-check"></i> {{ translate("Copied!") }}';
                setTimeout(function () { btn.innerHTML = original; }, 2000);
            }).catch(function () { btn.innerHTML = original; });
    });

    // Confirmation checkbox enables close button
    var confirmCb  = document.getElementById('asterConfirmCodes');
    var closeBtn   = document.getElementById('asterCloseBtn');
    if (confirmCb && closeBtn) {
        confirmCb.addEventListener('change', function () {
            if (this.checked) {
                closeBtn.classList.remove('disabled');
                closeBtn.removeAttribute('disabled');
                closeBtn.textContent = '{{ translate("Close") }}';
            } else {
                closeBtn.classList.add('disabled');
                closeBtn.setAttribute('disabled', 'disabled');
                closeBtn.textContent = '{{ translate("Close_(save_codes_first)") }}';
            }
        });
    }

}());
</script>
    @include('web-views.partials._digital-code-delivery-script')
@endpush
