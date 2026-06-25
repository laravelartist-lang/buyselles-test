@extends('layouts.front-end.app')

@section('title', translate('my_Order_List'))
@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/payment.css') }}">
@endpush
@section('content')

    <div class="container py-2 py-md-4 p-0 p-md-2 user-profile-container px-5px">
        <div class="row">
            @include('web-views.partials._profile-aside')

            <section class="col-lg-9 __customer-profile customer-profile-wishlist px-0">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-10px px-2 px-xl-0">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <h5 class="mb-0 fs-16">{{ translate('my_Order') }}</h5>
                    </div>
                    <div class="d-flex align-items-center gap-2 border rounded py-1 px-3">
                        <i class="fi fi-rr-bars-filter"></i>
                        @php
                            $currentOrder = request('order_by');
                        @endphp
                        <select name="filter" id="orderFilter" class="bg-transparent outline-0 fs-14 w-auto border-0 p-0">
                            <option value="{{ route('account-oder', ['order_by' => 'desc']) }}"
                                {{ $currentOrder === 'desc' ? 'selected' : '' }}>
                                {{ translate('sort_by_latest') }}
                            </option>
                            <option value="{{ route('account-oder', ['order_by' => 'asc']) }}"
                                {{ $currentOrder === 'asc' ? 'selected' : '' }}>
                                {{ translate('sort_by_oldest') }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="card __card d-flex web-direction customer-profile-orders h-100-44">
                    <div class="card-body">
                        @if ($orders->count() > 0)
                            <div class="row g-3">
                                @foreach ($orders as $order)
                                    <div class="col-md-6">
                                        <div class="cus-shadow rounded-8 p-xl-3 p-2">
                                            <div class="media-order">
                                                <a href="{{ route('account-order-details', ['id' => $order->id]) }}"
                                                    class="d-block position-relative w-70px border rounded h-70px min-w-60px">
                                                    @if ($order->seller_is == 'seller')
                                                        <img alt="{{ translate('shop') }}"
                                                            src="{{ getStorageImages(path: $order?->seller?->shop->image_full_url, type: 'shop') }}"
                                                            class="w-100 h-100">
                                                    @elseif($order->seller_is == 'admin')
                                                        <img alt="{{ translate('shop') }}"
                                                            src="{{ getStorageImages(path: getInHouseShopConfig(key: 'image_full_url'), type: 'shop') }}"
                                                            class="w-100 h-100">
                                                    @endif
                                                </a>
                                                <div class="cont w-auto text-start flex-grow-1">
                                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                                        <div class="d-flex align-items-center gap-1 flex-wrap">
                                                            <h6 class="mb-0">
                                                                <a href="{{ route('account-order-details', ['id' => $order->id]) }}"
                                                                    class="fs-14 font-semibold min-w-110 line--limit-1">
                                                                    {{ translate('order') }} #{{ $order['id'] }}
                                                                </a>
                                                            </h6>
                                                            <div>
                                                                @if ($order->edited_status == 1)
                                                                    <span class="edit-text title-semidark fs-14">
                                                                        {{ translate('Edited') }}
                                                                        @if (
                                                                            $order?->latestEditHistory?->order_due_payment_status == 'unpaid' &&
                                                                                $order?->latestEditHistory?->order_due_payment_method != 'offline_payment' &&
                                                                                $order?->latestEditHistory?->order_due_payment_method != 'cash_on_delivery' &&
                                                                                $order?->latestEditHistory?->order_due_amount > 0)
                                                                            <span data-toggle="tooltip"
                                                                                data-title="{{ translate('You will pay due the amount ') }}">
                                                                                <i
                                                                                    class="fi fi-sr-usd-circle text-danger"></i>
                                                                            </span>
                                                                        @endif

                                                                        @if (
                                                                            $order?->latestEditHistory?->order_return_payment_status == 'pending' &&
                                                                                $order?->latestEditHistory?->order_return_amount > 0)
                                                                            <span data-toggle="tooltip"
                                                                                data-title="{{ translate('Admin return the excess amount to you') }}">
                                                                                <i
                                                                                    class="fi fi-sr-usd-circle text-danger"></i>
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                @endif

                                                                @if ($order['order_status'] == 'failed' || $order['order_status'] == 'canceled')
                                                                    <span
                                                                        class="status-badge rounded-pill __badge badge-soft-danger fs-12 font-semibold text-capitalize">
                                                                        {{ translate($order['order_status'] == 'failed' ? 'failed_to_deliver' : $order['order_status']) }}
                                                                    </span>
                                                                @elseif(
                                                                    $order['order_status'] == 'confirmed' ||
                                                                        $order['order_status'] == 'processing' ||
                                                                        $order['order_status'] == 'delivered')
                                                                    <span
                                                                        class="status-badge rounded-pill __badge badge-soft-success fs-12 font-semibold text-capitalize">
                                                                        {{ translate($order['order_status'] == 'processing' ? 'packaging' : $order['order_status']) }}
                                                                    </span>
                                                                @else
                                                                    <span
                                                                        class="status-badge rounded-pill __badge badge-soft-primary fs-12 font-semibold text-capitalize">
                                                                        {{ translate($order['order_status']) }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="btn-group myorder-dropdown">
                                                            <button class="btn p-0 bg-transparent m-0 outline-0"
                                                                type="button" data-toggle="dropdown" aria-expanded="false">
                                                                <i class="fi fi-rr-menu-dots-vertical fs-14"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-right p-2">
                                                                <li>
                                                                    <div class="dropdown-item w-100">
                                                                        <a class="d-flex align-items-center justify-content-between w-100 fs-14 gap-2"
                                                                            href="{{ route('generate-invoice', [$order->id]) }}"
                                                                            title="{{ translate('download_invoice') }}">
                                                                            {{ translate('Download Invoice') }} <i
                                                                                class="fi fi-rr-download web-text-primary"></i>
                                                                        </a>
                                                                    </div>
                                                                </li>
                                                                <li>
                                                                    <div class="dropdown-item w-100">
                                                                        <a class="d-flex align-items-center justify-content-between w-100 fs-14 gap-2"
                                                                            href="{{ route('account-order-details', ['id' => $order->id]) }}"
                                                                            title="{{ translate('view_order_details') }}">
                                                                            {{ translate('View Order Details') }} <i
                                                                                class="fa fa-eye web-text-primary"></i>
                                                                        </a>
                                                                    </div>
                                                                </li>
                                                                @if (
                                                                    $order->edited_status == 1 &&
                                                                        $order?->latestEditHistory?->order_due_payment_status == 'unpaid' &&
                                                                        $order?->latestEditHistory?->order_due_payment_method != 'offline_payment' &&
                                                                        $order?->latestEditHistory?->order_due_payment_method != 'cash_on_delivery' &&
                                                                        $order?->latestEditHistory?->order_due_amount > 0)
                                                                    <li>
                                                                        <button
                                                                            class="dropdown-item d-flex align-items-center justify-content-between fs-14 gap-2"
                                                                            type="button"
                                                                            title="{{ translate('view_order_details') }}"
                                                                            data-toggle="modal"
                                                                            data-target="#choosePaymentMethodModal-{{ $order->id }}">
                                                                            {{ translate('Pay Due') }}
                                                                            <i class="bi bi-eye-fill"></i>
                                                                        </button>
                                                                    </li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <span class="fs-12 font-weight-medium">
                                                        <span class="text-dark fw-semibold">
                                                            {{ $order->order_details_sum_qty }}
                                                        </span>
                                                        {{ translate('Products') }}
                                                    </span>
                                                    <div
                                                        class="d-flex align-items-center justify-content-between gap-1 flex-wrap mt-1">
                                                        <div class="text-secondary-50 fs-12 font-weight-normal">
                                                            {{ date('d M, Y h:i A', strtotime($order['created_at'])) }}
                                                        </div>
                                                        <div class="web-text-primary fs-16 font-bold">
                                                            @php($orderTotalPriceSummary = \App\Utils\OrderManager::getOrderTotalPriceSummary(order: $order))
                                                            {{ webCurrencyConverter(amount: $orderTotalPriceSummary['totalAmount']) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="d-flex justify-content-center align-items-center h-100">
                                <div class="d-flex flex-column justify-content-center align-items-center gap-3">
                                    <img src="{{ theme_asset(path: 'public/assets/front-end/img/empty-icons/empty-orders.svg') }}"
                                        alt="" width="100">
                                    <h5 class="text-muted fs-14 font-semi-bold text-center">
                                        {{ translate('You_have_not_any_order_yet') }}
                                        !</h5>
                                </div>
                            </div>
                        @endif
                        <div class="card-footer border-0">
                            {{ $orders->links() }}
                        </div>
                    </div>
                </div>

            </section>
        </div>

    </div>
    <?php
    $orderSuccessIds = session('order_success_ids') ?? [];
    if (!is_array($orderSuccessIds)) {
        $orderSuccessIds = [];
    }
    $isPlural = count($orderSuccessIds) > 1;
    session()->forget('order_success_ids');
    $successDigitalCodes = [];
    if (!empty($orderSuccessIds)) {
        $successDigitalCodes = \App\Models\DigitalProductCode::whereIn('order_id', $orderSuccessIds)
            ->where('status', 'sold')
            ->with('product')
            ->get()
            ->map(
                fn($c) => [
                    'orderId' => $c->order_id,
                    'productName' => $c->product?->name ?? translate('Digital_Product'),
                    'code' => $c->decryptCode(),
                    'pin' => $c->decryptPin(),
                    'serial' => $c->serial_number,
                    'expiry' => $c->expiry_date?->format('Y-m-d'),
                ],
            )
            ->all();
    }
    $hasDigitalCodes = !empty($successDigitalCodes);
    ?>
    @if ($orderSuccessIds && auth('customer')->check())
        <div class="modal fade" id="order_successfully" aria-labelledby="order_successfully" tabindex="-1"
            aria-hidden="true" data-backdrop="{{ $hasDigitalCodes ? 'static' : 'false' }}"
            data-keyboard="{{ $hasDigitalCodes ? 'false' : 'true' }}">
            <div class="modal-dialog modal-dialog-centered {{ $hasDigitalCodes ? 'modal-lg' : 'modal--md' }}">
                <div class="modal-content">
                    <button type="button" class="close position-absolute" data-dismiss="modal" aria-label="Close"
                        style="top: 30px; right: 25px; z-index: 1; font-size: 1.5rem; opacity: 0.7;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <div class="modal-body rtl">
                        {{-- Header --}}
                        <div class="px-sm-3 pb-2 pt-4 mt-xl-1">
                            <div class="text-center mb-3">
                                <img width="56" height="56"
                                    src="{{ theme_asset(path: 'public/assets/front-end/img/icons/checked-circle.png') }}"
                                    alt="">
                            </div>
                            <h6 class="mb-2 fs-18 fw-semibold text-center">{{ translate('Thank You For Your Purchase!') }}
                            </h6>
                            <p class="fs-14 title-semidark mb-2 text-center">
                                {{ translate('We have received your order and will ship it shortly.') }}
                                {{ translate('Your Order ID' . ($isPlural ? 's' : '')) }}
                                <strong>#{{ implode(', #', $orderSuccessIds) }}</strong> —
                                {{ translate('keep it handy for tracking.') }}
                            </p>
                            {{-- Digital Codes Section --}}
                            @if ($hasDigitalCodes)
                                <p class="fs-13 text-muted text-center mb-3">
                                    <i class="fi fi-rr-info me-1"></i>
                                    {{ translate('You can also retrieve your code(s) anytime from Order Summary section.') }}
                                </p>

                                @include('web-views.partials._digital-code-purchase-modal-section', [
                                    'codes' => $successDigitalCodes,
                                    'orderIds' => $orderSuccessIds,
                                    'codesContainerId' => 'success-codes-container',
                                    'codeIdPrefix' => 'success-code',
                                    'copyBtnClass' => 'success-copy-btn',
                                ])
                            @endif
                            {{-- Footer buttons --}}
                            <div class="d-flex flex-wrap gap-2 justify-content-center mt-3 pb-2">
                                <a href="{{ route('home') }}"
                                    class="btn btn--primary font-bold px-4 font-weight-normal rounded-10">
                                    {{ translate('Explore More Items') }}
                                </a>
                                <button type="button" class="btn btn-outline-secondary px-4 rounded-10"
                                    data-dismiss="modal">
                                    {{ translate('Close') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (auth('customer')->check())
        <template id="order-success-delivery-actions-template">
            @include('web-views.partials._digital-code-delivery-actions', [
                'orderIds' => [],
                'viewTarget' => 'success-codes-container',
                'codesContainer' => 'success-codes-container',
                'layout' => 'cards',
                'context' => 'modal',
            ])
        </template>
    @endif

    @if ($orders->count() > 0)
        @foreach ($orders as $order)
            @include('web-views.partials._choose-payment-method-order-details', [
                'order' => $order,
                'orderDueAmount' => $order?->latestEditHistory?->order_due_amount ?? 0,
                'paymentGatewayList' => $paymentGatewayList,
            ])
        @endforeach
    @endif

@endsection

@push('script')
    <script src="{{ theme_asset(path: 'public/assets/front-end/js/payment.js') }}"></script>
    <?php
    $jsOrderData = $orderSuccessIds
        ? [
            'orderIds' => $orderSuccessIds,
            'isPlural' => $isPlural,
            'hasDigitalCodes' => $hasDigitalCodes,
            'codes' => $successDigitalCodes,
        ]
        : null;
    ?>
    <script>
        (function() {
            var LS_KEY = 'bk_order_success';

            // PHP injects fresh data only on first load after checkout
            var phpData = @json($jsOrderData);

            function esc(str) {
                return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g,
                    '&quot;');
            }

            function buildDeliverySectionHtml(orderIds) {
                var tpl = document.getElementById('order-success-delivery-actions-template');
                if (!tpl || !tpl.content) {
                    return '';
                }

                var node = tpl.content.querySelector('.digital-code-delivery-actions');
                if (!node) {
                    return '';
                }

                var clone = node.cloneNode(true);
                clone.setAttribute('data-order-ids', JSON.stringify(orderIds || []));
                clone.setAttribute('data-view-target', 'success-codes-container');
                clone.setAttribute('data-codes-container', 'success-codes-container');

                return clone.outerHTML + '<hr class="my-3">';
            }

            function buildCodesHtml(data) {
                if (!data.hasDigitalCodes || !data.codes || !data.codes.length) return '';
                var h = '';
                h += '<p class="fs-13 text-muted text-center mb-3"><i class="fi fi-rr-info me-1"></i>{{ addslashes(translate('You can also retrieve your code(s) anytime from Order Summary section.')) }}</p>';
                h += '<div class="alert alert-warning py-2 px-3 fs-13 mb-3">' +
                    '<i class="fa fa-exclamation-triangle me-1"></i>' +
                    '<strong>{{ addslashes(translate('Important')) }}:</strong> ' +
                    '{{ addslashes(translate('Copy or print your codes below. They are also sent to your email.')) }}' +
                    '</div>';
                h += buildDeliverySectionHtml(data.orderIds);
                h += '<div id="success-codes-container">';
                data.codes.forEach(function(item, idx) {
                    h += '<div class="border rounded p-3 mb-3 bg-light digital-code-item">';
                    h += '<p class="text-muted mb-1 fw-semibold digital-code-item__label" style="font-size:.8rem;"><i class="fa fa-box me-1"></i>' +
                        esc(item.productName);
                    if (item.orderId) h +=
                        ' &mdash; <span class="text-secondary">{{ addslashes(translate('Order')) }} #' + item
                        .orderId + '</span>';
                    h += '</p>';
                    h += '<div class="d-flex align-items-center gap-2 flex-wrap mt-1">';
                    h += '<code class="fs-5 fw-bold text-dark bg-white px-3 py-2 rounded border flex-grow-1 text-center" id="success-code-' +
                        idx +
                        '" style="letter-spacing:4px;font-family:\'Courier New\',monospace;word-break:break-all;">' +
                        esc(item.code) + '</code>';
                    h += '<button type="button" class="btn btn-sm btn-outline-primary success-copy-btn" data-target="success-code-' +
                        idx + '"><i class="fa fa-copy"></i> {{ addslashes(translate('Copy')) }}</button>';
                    h += '</div>';
                    if (item.pin || item.serial || item.expiry) {
                        h += '<p class="text-muted mb-0 mt-1 digital-code-item__meta" style="font-size:.76rem;">';
                        if (item.pin) h += '<strong>{{ addslashes(translate('PIN')) }}:</strong> <code class="text-dark fw-semibold">' + esc(item.pin) + '</code> ';
                        if (item.serial) h += '&nbsp;&nbsp;<strong>S/N:</strong> ' + esc(item.serial) + ' ';
                        if (item.expiry) h += '&nbsp;&nbsp;<strong>Exp:</strong> ' + esc(item.expiry);
                        h += '</p>';
                    }
                    h += '</div>';
                });
                h += '</div>';
                return h;
            }

            function buildModalHtml(data) {
                var orderIdsStr = data.orderIds.map(function(id) {
                    return '#' + id;
                }).join(', #');
                var codesHtml = buildCodesHtml(data);
                var footerHtml =
                    '<a href="{{ route('home') }}" class="btn btn--primary font-bold px-4 font-weight-normal rounded-10">{{ addslashes(translate('Explore More Items')) }}</a>';
                footerHtml +=
                    '<button type="button" class="btn btn-outline-secondary px-4 rounded-10" data-dismiss="modal">{{ addslashes(translate('Close')) }}</button>';
                return '<div class="modal fade" id="order_successfully" tabindex="-1" aria-hidden="true" data-backdrop="static" data-keyboard="false">' +
                    '<div class="modal-dialog modal-dialog-centered ' + (data.hasDigitalCodes ? 'modal-lg' :
                        'modal--md') + '">' +
                    '<div class="modal-content"><div class="modal-body rtl"><div class="px-sm-3 pb-2 pt-4 mt-xl-1">' +
                    '<div class="text-center mb-3"><img width="56" height="56" src="{{ theme_asset(path: 'public/assets/front-end/img/icons/checked-circle.png') }}" alt=""></div>' +
                    '<h6 class="mb-2 fs-18 fw-semibold text-center">{{ addslashes(translate('Thank You For Your Purchase!')) }}</h6>' +
                    '<p class="fs-14 title-semidark mb-2 text-center">{{ addslashes(translate('We have received your order and will ship it shortly.')) }} {{ addslashes(translate('Your Order ID')) }}' +
                    (data.isPlural ? 's' : '') + ' <strong>' + orderIdsStr +
                    '</strong> &mdash; {{ addslashes(translate('keep it handy for tracking.')) }}</p>' +
                    codesHtml +
                    '<div class="d-flex flex-wrap gap-2 justify-content-center mt-3 pb-2">' + footerHtml + '</div>' +
                    '</div></div></div></div></div>';
            }

            function attachModalEvents(data) {
                var $modal = $('#order_successfully');

                // Copy buttons
                $(document).on('click', '.success-copy-btn', function() {
                    var $btn = $(this);
                    var text = $('#' + $btn.data('target')).text().trim();
                    var orig = $btn.html();
                    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.resolve())
                    .then(function() {
                        $btn.html(
                            '<i class="fi fi-rr-check"></i> {{ addslashes(translate('Copied!')) }}'
                        );
                        setTimeout(function() {
                            $btn.html(orig);
                        }, 2000);
                    });
                });

                // Close modal and clear localStorage
                $modal.on('hide.bs.modal', function() {
                    localStorage.removeItem(LS_KEY);
                });
            }

            $(document).ready(function() {
                var data = null;

                if (phpData) {
                    // Fresh data from checkout — save to localStorage for refresh resilience
                    data = phpData;
                    try {
                        localStorage.setItem(LS_KEY, JSON.stringify(phpData));
                    } catch (e) {}
                } else {
                    // No PHP data — check if user hasn't confirmed yet
                    try {
                        data = JSON.parse(localStorage.getItem(LS_KEY));
                    } catch (e) {
                        data = null;
                    }
                    if (data) {
                        // PHP-rendered modal is absent; build it dynamically
                        if (!document.getElementById('order_successfully')) {
                            $('body').append(buildModalHtml(data));
                        }
                    }
                }

                if (!data) return;

                var backdrop = data.hasDigitalCodes ? 'static' : false;
                $('#order_successfully').modal({
                    backdrop: backdrop,
                    keyboard: !data.hasDigitalCodes,
                    show: true
                });
                attachModalEvents(data);
            });
        }());
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelect = document.getElementById('orderFilter');
            filterSelect.addEventListener('change', function() {
                const url = this.value;
                if (url) {
                    window.location.href = url;
                }
            });
        });
    </script>
    @include('web-views.partials._digital-code-delivery-script')
@endpush
