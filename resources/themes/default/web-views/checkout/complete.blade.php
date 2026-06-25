@extends('layouts.front-end.app')

@section('title', translate('order_Complete'))

@section('content')
    @php
        $hasDigitalCodes = !empty($digitalCodes) && count($digitalCodes) > 0;
        $hasPendingSupplierCodes = $hasPendingSupplierCodes ?? false;
        $showDigitalCodesSection = $hasDigitalCodes || $hasPendingSupplierCodes;
        $orderIdsStr     = isset($order_ids) && count($order_ids) > 0
            ? '#' . implode(', #', $order_ids)
            : '';
        $shopName  = getWebConfig(name: 'company_name') ?? 'Buyselles';
        $shopPhone = getWebConfig(name: 'company_phone') ?? '';
    @endphp

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- Success banner card — stays below the modal for reference      --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="container mt-5 mb-4 rtl __inline-53 text-align-direction">
        <div class="row d-flex justify-content-center">
            <div class="col-md-10 col-lg-10">
                <div class="card">
                    @if (auth('customer')->check() || session('guest_id'))
                        <div class="card-body">
                            <div class="mb-3 text-center">
                                <i class="fa fa-check-circle __text-60px __color-0f9d58"></i>
                            </div>

                            <h6 class="font-black fw-bold text-center">
                                @if (isset($isNewCustomerInSession) && $isNewCustomerInSession)
                                    {{ translate('Order_Placed_&_Account_Created_Successfully') }}!
                                @else
                                    {{ translate('Order_Placed_Successfully') }}!
                                @endif
                            </h6>

                            @if ($orderIdsStr)
                                <p class="text-center fs-12">
                                    {{ translate('your_payment_has_been_successfully_processed_and_your_order') }}
                                    <span class="fw-bold text-primary">{{ $orderIdsStr }}</span>
                                    {{ translate('has_been_placed.') }}
                                </p>
                            @else
                                <p class="text-center fs-12">
                                    {{ translate('your_order_is_being_processed_and_will_be_completed.') }}
                                    {{ translate('You_will_receive_an_email_confirmation_when_your_order_is_placed.') }}
                                </p>
                            @endif

                            <div class="row mt-4">
                                @if ($showDigitalCodesSection)
                                    <div class="col-12 text-center mb-2">
                                        <button type="button" class="btn btn--primary"
                                            data-bs-toggle="modal" data-bs-target="#orderSuccessModal">
                                            <i class="fa fa-key me-1"></i>
                                            {{ translate('View_Your_Codes_&_Receipt') }}
                                        </button>
                                    </div>
                                @else
                                    <div class="col-12 text-center mb-2">
                                        <button type="button" class="btn btn--primary"
                                            data-bs-toggle="modal" data-bs-target="#orderSuccessModal">
                                            <i class="fa fa-receipt me-1"></i>
                                            {{ translate('View_Order_Receipt') }}
                                        </button>
                                    </div>
                                @endif
                                <div class="col-12 text-center mb-2">
                                    <a href="{{ route('track-order.index') }}" class="btn btn-outline-primary">
                                        {{ translate('track_Order') }}
                                    </a>
                                </div>
                                <div class="col-12 text-center">
                                    <a href="{{ route('home') }}" class="text-center">
                                        {{ translate('Continue_Shopping') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- Digital Codes Card (always visible below for reference)        --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    @if ($showDigitalCodesSection)
        <div class="container mb-5 rtl __inline-53 text-align-direction">
            <div class="row d-flex justify-content-center">
                <div class="col-md-10 col-lg-10">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <div>
                                <h6 class="mb-0">
                                    <i class="fa fa-key me-2"></i>
                                    {{ translate('Your_Digital_Codes') }}
                                </h6>
                                <small>{{ translate('Codes_have_been_emailed_to_you_too._Keep_them_safe.') }}</small>
                            </div>
                        </div>
                        <div class="card-body">
                            @include('web-views.partials._digital-code-delivery-actions', [
                                'orderIds' => $order_ids ?? [],
                                'viewTarget' => 'codes-card-container',
                                'codesContainer' => 'codes-card-container',
                                'layout' => 'cards',
                            ])
                            <hr class="my-4">
                            <div id="codes-card-container">
                                @foreach ($digitalCodes as $item)
                                    <div class="border rounded p-3 mb-3">
                                        <p class="fw-bold mb-1 text-muted" style="font-size:0.85rem;">
                                            {{ $item['productName'] }}
                                            @if ($item['orderId'])
                                                &nbsp;&mdash;&nbsp;
                                                <span class="text-secondary">{{ translate('Order') }} #{{ $item['orderId'] }}</span>
                                            @endif
                                        </p>
                                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                            <code class="fs-5 fw-bold text-dark bg-light px-3 py-2 rounded border"
                                                id="code-{{ $loop->index }}"
                                                style="letter-spacing:3px; font-family:'Courier New',monospace; word-break:break-all;">
                                                {{ $item['code'] }}
                                            </code>
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="copyCodeById('code-{{ $loop->index }}', this)"
                                                title="{{ translate('Copy_Code') }}">
                                                <i class="fa fa-copy"></i> {{ translate('Copy') }}
                                            </button>
                                        </div>
                                        @if (!empty($item['pin']) || !empty($item['serial']) || !empty($item['expiry']))
                                            <p class="text-muted mb-0" style="font-size:0.78rem;">
                                                @if (!empty($item['pin']))
                                                    <strong>{{ translate('PIN') }}:</strong> <code class="text-dark fw-semibold">{{ $item['pin'] }}</code>
                                                @endif
                                                @if (!empty($item['serial']))
                                                    &nbsp;&nbsp;<strong>{{ translate('S/N') }}:</strong> {{ $item['serial'] }}
                                                @endif
                                                @if (!empty($item['expiry']))
                                                    &nbsp;&nbsp;<strong>{{ translate('Exp') }}:</strong> {{ $item['expiry'] }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Loading spinner for pending supplier codes --}}
                            @if ($hasPendingSupplierCodes)
                                <div id="codes-card-loading" class="text-center py-4" @if($hasDigitalCodes) style="display:none;" @endif>
                                    <div class="spinner-border text-success" role="status">
                                        <span class="visually-hidden">{{ translate('Loading...') }}</span>
                                    </div>
                                    <p class="mt-2 text-muted mb-0" style="font-size:0.9rem;">
                                        <i class="fa fa-clock me-1"></i>
                                        {{ translate('Fetching_your_digital_codes_from_supplier...') }}
                                    </p>
                                    <p class="text-muted mb-0" style="font-size:0.78rem;">
                                        {{ translate('This_usually_takes_30-60_seconds._Please_wait.') }}
                                    </p>
                                </div>
                            @endif

                            {{-- Timeout message (shown by JS after 3 min) --}}
                            <div id="codes-card-timeout" class="text-center py-3" style="display:none;">
                                <i class="fa fa-envelope text-primary" style="font-size:32px;"></i>
                                <p class="mt-2 text-muted mb-0">
                                    {{ translate('Your_codes_are_taking_longer_than_expected.') }}
                                    {{ translate('They_will_be_sent_to_your_email_shortly.') }}
                                </p>
                                <p class="text-muted mb-0" style="font-size:0.8rem;">
                                    {{ translate('You_can_also_check_your_order_history_later.') }}
                                </p>
                            </div>

                            <p class="text-danger mt-1 mb-0" style="font-size:0.8rem;" id="codes-card-warning" @if(!$hasDigitalCodes) style="display:none;" @endif>
                                <i class="fa fa-exclamation-triangle"></i>
                                {{ translate('Warning:_Do_not_share_your_codes_with_anyone.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- Purchase Success Modal (auto-opens on page load)               --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="modal fade" id="orderSuccessModal" tabindex="-1"
        aria-labelledby="orderSuccessModalLabel"
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
                        <h5 class="text-white fw-bold mb-1" id="orderSuccessModalLabel">
                            {{ translate('Thank_You_For_Your_Purchase') }}!
                        </h5>
                        @if ($orderIdsStr)
                            <p class="text-white mb-0" style="font-size:0.82rem; opacity:0.9;">
                                {{ translate('Order_ID') }}: <strong>{{ $orderIdsStr }}</strong>
                                &nbsp;&bull;&nbsp; {{ now()->format('d M Y, H:i') }}
                            </p>
                        @endif
                        <p class="mb-0 mt-1" style="font-size:0.78rem; opacity:0.75; color:#fff;">
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
                        <div class="alert alert-warning py-2 px-3 fs-13 mb-3" id="codes-modal-alert" @if(!$hasDigitalCodes) style="display:none;" @endif>
                            <i class="fa fa-exclamation-triangle me-1"></i>
                            <strong>{{ translate('Important') }}:</strong>
                            {{ translate('Copy_or_print_your_codes_below._They_are_also_sent_to_your_email.') ?: translate('Copy or print your codes below. They are also sent to your email.') }}
                        </div>

                        @include('web-views.partials._digital-code-purchase-modal-section', [
                            'codes' => $digitalCodes,
                            'orderIds' => $order_ids ?? [],
                            'codesContainerId' => 'codes-modal-container',
                            'codeIdPrefix' => 'modal-code',
                            'copyBtnClass' => 'modal-copy-btn',
                            'showAlert' => false,
                        ])

                        {{-- Loading spinner for pending supplier codes --}}
                        @if ($hasPendingSupplierCodes)
                            <div id="codes-modal-loading" class="text-center py-4" @if($hasDigitalCodes) style="display:none;" @endif>
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">{{ translate('Loading...') }}</span>
                                </div>
                                <p class="mt-2 text-muted mb-0">
                                    <i class="fa fa-clock me-1"></i>
                                    {{ translate('Fetching_your_digital_codes_from_supplier...') }}
                                </p>
                                <p class="text-muted mb-0" style="font-size:0.78rem;">
                                    {{ translate('This_usually_takes_30-60_seconds._Please_wait.') }}
                                </p>
                            </div>
                        @endif

                        {{-- Timeout message (shown by JS) --}}
                        <div id="codes-modal-timeout" class="text-center py-3" style="display:none;">
                            <i class="fa fa-envelope text-primary" style="font-size:32px;"></i>
                            <p class="mt-2 text-muted mb-0">
                                {{ translate('Your_codes_are_taking_longer_than_expected.') }}
                                {{ translate('They_will_be_sent_to_your_email_shortly.') }}
                            </p>
                        </div>

                        {{-- Confirmation checkbox — required before close --}}
                        <div class="form-check p-3 border rounded mt-2" id="codes-modal-confirm-wrap" style="background:#fffde7;@if(!$hasDigitalCodes) display:none; @endif">
                            <input class="form-check-input" type="checkbox" id="confirmCodesDownloaded" value="1">
                            <label class="form-check-label fw-semibold" for="confirmCodesDownloaded" style="cursor:pointer;">
                                <i class="fa fa-shield-alt me-1 text-success"></i>
                                {{ translate('I_confirm_I_have_successfully_copied_/_saved_my_code(s)') }}
                            </label>
                        </div>
                    @else
                        {{-- Physical / no-codes order --}}
                        <div class="text-center py-3">
                            <i class="fa fa-check-circle text-success" style="font-size:42px;"></i>
                            <p class="mt-3 text-muted" style="font-size:0.9rem;">
                                {{ translate('Your_order_is_confirmed._You_will_receive_an_email_with_tracking_details.') }}
                            </p>
                            @if ($orderIdsStr)
                                <div class="alert alert-light border mt-3" style="font-size:0.87rem;">
                                    <strong>{{ translate('Order_ID') }}:</strong>
                                    <span class="text-primary fw-bold">{{ $orderIdsStr }}</span>
                                    <br>
                                    <small class="text-muted">{{ now()->format('d M Y, H:i') }}</small>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="modal-footer border-0 pt-1 flex-wrap gap-2 justify-content-end">
                    @if (!$showDigitalCodesSection)
                        <a href="{{ route('track-order.index') }}" class="btn btn-outline-primary">
                            <i class="fa fa-map-marker me-1"></i> {{ translate('Track_Order') }}
                        </a>
                    @endif
                    <button type="button" id="modalCloseBtn"
                        class="btn btn--primary {{ $showDigitalCodesSection ? 'disabled' : '' }}"
                        data-bs-dismiss="modal"
                        @if($showDigitalCodesSection) disabled @endif>
                        @if($showDigitalCodesSection)
                            {{ translate('Close_(save_codes_first)') }}
                        @else
                            {{ translate('Close') }}
                        @endif
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

    var hasDigitalCodes = {{ $hasDigitalCodes ? 'true' : 'false' }};
    var hasPendingCodes = {{ ($hasPendingSupplierCodes ?? false) ? 'true' : 'false' }};
    var showDigitalCodesSection = {{ $showDigitalCodesSection ? 'true' : 'false' }};
    var orderIds = @json($order_ids ?? []);
    var pollUrl = '{{ route("check-digital-codes-status") }}';
    var pollInterval = null;
    var pollStartTime = Date.now();
    var POLL_TIMEOUT_MS = 180000; // 3 minutes
    var POLL_INTERVAL_MS = 5000;  // 5 seconds
    var dynamicCodeIndex = {{ count($digitalCodes ?? []) }};

    // ── Auto-open modal on page load ──────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('orderSuccessModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');

            var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl, {
                backdrop: showDigitalCodesSection ? 'static' : true,
                keyboard: !showDigitalCodesSection
            });
            bsModal.show();
        }

        // Start polling if there are pending supplier codes
        if (hasPendingCodes) {
            pollInterval = setInterval(pollForCodes, POLL_INTERVAL_MS);
        }
    });

    // ── AJAX polling for supplier codes ───────────────────────────
    function pollForCodes() {
        if (Date.now() - pollStartTime > POLL_TIMEOUT_MS) {
            clearInterval(pollInterval);
            showTimeoutMessage();
            return;
        }

        var params = new URLSearchParams();
        orderIds.forEach(function(id) { params.append('orderIds[]', id); });

        fetch(pollUrl + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.codes && data.codes.length > 0) {
                renderCodesDynamic(data.codes);
            }
            if (!data.pending) {
                clearInterval(pollInterval);
                hideElement('codes-card-loading');
                hideElement('codes-modal-loading');
            }
        })
        .catch(function(err) {
            console.error('Digital codes poll error:', err);
        });
    }

    function renderCodesDynamic(codes) {
        // Hide loading spinners
        hideElement('codes-card-loading');
        hideElement('codes-modal-loading');

        // Show alerts / warnings / confirm
        showElement('codes-modal-alert');
        showElement('codes-card-warning');
        showElement('codes-modal-confirm-wrap');

        var cardContainer = document.getElementById('codes-card-container');
        var modalContainer = document.getElementById('codes-modal-container');

        // Clear existing content (replace with fresh from server)
        if (cardContainer) cardContainer.innerHTML = '';
        if (modalContainer) modalContainer.innerHTML = '';

        codes.forEach(function(item, idx) {
            var cardId = 'dyn-code-' + idx;
            var modalId = 'dyn-modal-code-' + idx;

            var metaParts = [];
            if (item.pin) metaParts.push('<strong>PIN:</strong> <code class="text-dark fw-semibold">' + esc(item.pin) + '</code>');
            if (item.serial) metaParts.push('<strong>S/N:</strong> ' + esc(item.serial));
            if (item.expiry) metaParts.push('<strong>Exp:</strong> ' + esc(item.expiry));
            var metaHtml = metaParts.length
                ? '<p class="text-muted mb-0" style="font-size:0.78rem;">' + metaParts.join(' &nbsp;&nbsp;') + '</p>'
                : '';
            var modalMetaHtml = metaParts.length
                ? '<p class="text-muted mb-0 mt-1" style="font-size:0.76rem;">' + metaParts.join(' &nbsp;&nbsp;') + '</p>'
                : '';

            var orderLabel = item.orderId ? ' &mdash; <span class="text-secondary">{{ translate("Order") }} #' + esc(item.orderId) + '</span>' : '';

            // Card HTML
            if (cardContainer) {
                cardContainer.insertAdjacentHTML('beforeend',
                    '<div class="border rounded p-3 mb-3">'
                    + '<p class="fw-bold mb-1 text-muted" style="font-size:0.85rem;">' + esc(item.productName) + orderLabel + '</p>'
                    + '<div class="d-flex align-items-center gap-2 flex-wrap mb-1">'
                    + '<code class="fs-5 fw-bold text-dark bg-light px-3 py-2 rounded border" id="' + cardId + '" style="letter-spacing:3px;font-family:\'Courier New\',monospace;word-break:break-all;">' + esc(item.code) + '</code>'
                    + '<button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyCodeById(\'' + cardId + '\', this)"><i class="fa fa-copy"></i> {{ translate("Copy") }}</button>'
                    + '</div>'
                    + metaHtml
                    + '</div>'
                );
            }

            // Modal HTML
            if (modalContainer) {
                modalContainer.insertAdjacentHTML('beforeend',
                    '<div class="border rounded p-3 mb-3 bg-light">'
                    + '<p class="text-muted mb-1 fw-semibold" style="font-size:0.8rem;"><i class="fa fa-box me-1"></i>' + esc(item.productName) + orderLabel + '</p>'
                    + '<div class="d-flex align-items-center gap-2 flex-wrap mt-1">'
                    + '<code class="fs-4 fw-bold text-dark bg-white px-3 py-2 rounded border flex-grow-1 text-center" id="' + modalId + '" style="letter-spacing:4px;font-family:\'Courier New\',monospace;word-break:break-all;">' + esc(item.code) + '</code>'
                    + '<button type="button" class="btn btn-sm btn-outline-primary modal-copy-btn" data-target="' + modalId + '"><i class="fa fa-copy"></i> {{ translate("Copy") }}</button>'
                    + '</div>'
                    + modalMetaHtml
                    + '</div>'
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
        if (!str && str !== 0) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    // ── Auto-open modal on page load ──────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('orderSuccessModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            var bsModal = new bootstrap.Modal(modalEl, {
                backdrop: hasDigitalCodes ? 'static' : true,
                keyboard: !hasDigitalCodes
            });
            bsModal.show();
        }
    });

    // ── Copy buttons inside modal ────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.modal-copy-btn');
        if (!btn) return;
        var targetId = btn.getAttribute('data-target');
        var text = document.getElementById(targetId).innerText.trim();
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        (navigator.clipboard
            ? navigator.clipboard.writeText(text)
            : Promise.resolve(legacyCopy(text))
        ).then(function () {
            btn.innerHTML = '<i class="fa fa-check"></i> {{ translate("Copied!") }}';
            setTimeout(function () { btn.innerHTML = original; }, 2000);
        }).catch(function () {
            btn.innerHTML = original;
        });
    });

    function legacyCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }

    // ── Confirmation checkbox enables close button ───────────────
    document.addEventListener('change', function (e) {
        if (e.target.id !== 'confirmCodesDownloaded') return;
        var closeBtn = document.getElementById('modalCloseBtn');
        if (!closeBtn) return;
        if (e.target.checked) {
            closeBtn.classList.remove('disabled');
            closeBtn.removeAttribute('disabled');
            closeBtn.textContent = '{{ translate("Close") }}';
        } else {
            closeBtn.classList.add('disabled');
            closeBtn.setAttribute('disabled', 'disabled');
            closeBtn.textContent = '{{ translate("Close_(save_codes_first)") }}';
        }
    });

    // ── Inline card copy function (backward compat) ───────────────
    window.copyCodeById = function (elementId, btn) {
        var text = document.getElementById(elementId).innerText.trim();
        var original = btn.innerHTML;
        (navigator.clipboard
            ? navigator.clipboard.writeText(text)
            : Promise.resolve(legacyCopy(text))
        ).then(function () {
            btn.innerHTML = '<i class="fa fa-check"></i> {{ translate("Copied!") }}';
            setTimeout(function () { btn.innerHTML = original; }, 2000);
        });
    };
}());
</script>
    @include('web-views.partials._digital-code-delivery-script')
@endpush
