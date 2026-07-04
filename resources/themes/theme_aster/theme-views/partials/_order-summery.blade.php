@php
    use App\Utils\CartManager;
    use App\Utils\Helpers;
@endphp
<div class="col-lg-4">
    <div class="card text-dark sticky-top-80">
        <div class="card-body px-sm-4 d-flex flex-column gap-3">
            @php($systemTaxConfig = getTaxModuleSystemTypesConfig())
            @php($current_url = request()->segment(count(request()->segments())))
            @php($shippingMethod = getWebConfig(name: 'shipping_method'))
            @php($product_price_total = 0)
            @php($totalTax = \App\Utils\CartManager::getCartListTaxAmount())
            @php($total_shipping_cost = 0)
            @php($total_discount_on_product = 0)
            @php($cart = CartManager::getCartListQuery(type: 'checked'))
            @php($cartAll = CartManager::getCartListQuery())
            @php($cart_group_ids = CartManager::get_cart_group_ids())
            @php($shipping_cost = CartManager::get_shipping_cost(type: 'checked'))
            @php($get_shipping_cost_saved_for_free_delivery = CartManager::getShippingCostSavedForFreeDelivery(type: 'checked'))
            @if ($cart->count() > 0)
                @foreach ($cart as $key => $cartItem)
                    @php($product_price_total += $cartItem['price'] * $cartItem['quantity'])
                    @php($total_discount_on_product += $cartItem['discount'] * $cartItem['quantity'])
                @endforeach

                @if (session()->missing('coupon_type') || session('coupon_type') != 'free_delivery')
                    @php($total_shipping_cost = $shipping_cost - $get_shipping_cost_saved_for_free_delivery)
                @else
                    @php($total_shipping_cost = $shipping_cost)
                @endif
            @endif

            @if ($cartAll->count() > 0 && $cart->count() == 0)
                <span>{{ translate('Please_checked_items_before_proceeding_to_checkout') }}</span>
            @elseif($cartAll->count() == 0)
                <span>{{ translate('empty_cart') }}</span>
            @endif

            <h4 class="text-capitalize mb-0">{{ translate('order_summary') }}</h4>
            @php($coupon_discount = 0)
            @php($coupon_dis = 0)
            @if (auth('customer')->check() && !session()->has('coupon_discount'))
                <form class="needs-validation" action="{{ route('coupon.apply') }}" method="post"
                      id="submit-coupon-code">
                    @csrf
                    <div class="form-group mb-0">
                        <div class="form-control focus-border pe-1 rounded d-flex align-items-center">
                            <input type="text" name="code" id="promo-code"
                                   class="w-100 text-dark bg-transparent border-0 focus-input"
                                   placeholder="{{ translate('write_coupon_code_here') }}" required>
                            <button class="btn btn-primary text-nowrap"
                                    id="coupon-code-apply">{{ translate('apply') }}</button>
                        </div>
                    </div>
                </form>
            @endif
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('item_price') }}</div>
                <div class="fw-semibold">{{ webCurrencyConverter($product_price_total) }}</div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('product_discount') }}</div>
                <div class="fw-semibold">{{ webCurrencyConverter($total_discount_on_product) }}</div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('sub_total') }}</div>
                <div class="fw-semibold">{{ webCurrencyConverter($product_price_total - $total_discount_on_product) }}</div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <div class="opacity-75 text-capitalize">{{ translate('shipping') }}</div>
                <div class="fw-semibold">{{ webCurrencyConverter($total_shipping_cost) }}</div>
            </div>

            @php($coupon_discount = session()->has('coupon_discount') ? session('coupon_discount') : 0)
            @php($coupon_dis = session()->has('coupon_discount') ? session('coupon_discount') : 0)
            @if (auth('customer')->check() && session()->has('coupon_discount'))
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                    <div class="opacity-75 text-capitalize">{{ translate('coupon_discount') }}</div>
                    <div class="fw-semibold">
                        {{ '-' . webCurrencyConverter($coupon_discount) }}
                    </div>
                </div>
            @endif

            <?php
            $totalAmount = $product_price_total + ($totalTax['item_tax'] + $totalTax['shipping_cost_tax']) + $total_shipping_cost - $coupon_dis - $total_discount_on_product;
            $referralAmount = \App\Utils\CustomerManager::getReferralDiscountAmount(
                user: (auth('customer')->check() ? auth('customer')->user() : null),
                couponDiscount: $coupon_dis
            );
            ?>

            @if ($referralAmount > 0)
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                    <div class="opacity-75 text-capitalize">{{ translate('referral_discount') }}</div>
                    <div class="fw-semibold">{{ '-' . webCurrencyConverter($referralAmount) }}</div>
                </div>
            @endif

            @if($systemTaxConfig['SystemTaxVat']['is_active'] && !$systemTaxConfig['is_included'])
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                    <div class="opacity-75 text-capitalize">{{ translate('tax') }}</div>
                    <div class="fw-semibold">{{ webCurrencyConverter(amount: ($totalTax['item_tax'] + $totalTax['shipping_cost_tax'])) }}</div>
                </div>
            @endif
            
            <hr class="m-0" />

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                <h5>
                    {{ translate('total') }}
                    @if($systemTaxConfig['SystemTaxVat']['is_active'] && $systemTaxConfig['is_included'])
                        <span class="fs-12 fw-semibold">({{ translate('Tax_:_Inc.') }})</span>
                    @endif
                </h5>
                <h4 class="text-primary">{{ webCurrencyConverter($totalAmount - $referralAmount) }}</h4>
            </div>

            {{-- Wallet balance breakdown — shown when wallet payment is selected --}}
            @if (auth('customer')->check())
                @php
                    $walletBalance = auth('customer')->user()->wallet_balance ?? 0;
                    $orderTotal = $totalAmount - $referralAmount;
                    $walletRemaining = $walletBalance - $orderTotal;
                @endphp
                <div id="wallet-balance-info" class="d-none">
                    <hr class="m-0">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img width="16" src="{{ theme_asset('assets/img/icons/wallet.png') }}" alt="">
                        <span class="fw-semibold fs-14">{{ translate('wallet_summary') }}</span>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                        <div class="opacity-75 text-capitalize">{{ translate('wallet_balance') }}</div>
                        <div class="fw-semibold">{{ webCurrencyConverter(amount: $walletBalance) }}</div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                        <div class="opacity-75 text-capitalize">{{ translate('order_amount') }}</div>
                        <div class="fw-semibold">- {{ webCurrencyConverter(amount: $orderTotal) }}</div>
                    </div>
                    <hr class="m-0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 fs-16">
                        <div class="fw-semibold {{ $walletRemaining >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ translate('remaining_balance') }}
                        </div>
                        <div class="fw-semibold {{ $walletRemaining >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ webCurrencyConverter(amount: $walletRemaining) }}
                        </div>
                    </div>
                    @if ($walletRemaining < 0)
                        <div class="mt-2 p-2 rounded bg-soft-danger border border-danger">
                            <small class="text-danger d-flex align-items-center gap-1">
                                <i class="bi bi-exclamation-triangle"></i>
                                {{ translate('you_do_not_have_sufficient_balance_for_pay_this_order') }}
                            </small>
                        </div>
                    @endif
                </div>
            @endif

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                @if (str_contains(request()->url(), 'checkout-payment') || str_contains(request()->url(), 'checkout-details'))
                    <label class="custom-control custom-checkbox mb-3 d-flex user-select-none cursor-pointer">
                        <input type="checkbox" class="custom-control-input payment-input-checkbox">
                        <span class="custom-control-label">
                    <span>{{ translate('i_agree_to_Your') }}</span>
                    <a class="font-size-sm text--primary fw-bold d-inline" target="_blank"
                       href="{{ route('business-page.view', ['slug' => 'terms-and-conditions']) }}">
                        {{ translate('terms_and_condition') }}
                    </a>
                    @foreach($web_config['business_pages']->where('default_status', 1) as $businessPage)
                                @if($businessPage['slug'] == 'privacy-policy' || $businessPage['slug'] == 'refund-policy')
                                    <a class="font-size-sm text--primary fw-bold d-inline" target="_blank"
                                       href="{{ route('business-page.view', ['slug' => $businessPage['slug']]) }}">
                            , {{ translate(str_replace('-', '_', $businessPage['slug'])) }}
                            </a>
                                @endif
                            @endforeach
                    </span>
                    </label>
                @endif

                <a href="{{ route('home') }}" class="btn-link text-primary text-capitalize user-select-none fw-semibold">
                    <i class="fi fi-rr-angle-double-left fs-10"></i> {{ translate('continue_shopping') }}
                </a>

                @if (str_contains(request()->url(), 'checkout-payment') || str_contains(request()->url(), 'checkout-details'))
                    <button class="btn btn-primary text-capitalize {{ $cart->count() <= 0 ? 'custom-disabled' : '' }}"
                            id="proceed-to-next-action"
                            data-goto-checkout="{{ route('customer.choose-shipping-address-other') }}"
                            data-checkout-payment="{{ route('checkout-payment') }}"
                            {{ isset($isProductNullStatus) && $isProductNullStatus == 1 ? 'disabled' : '' }}
                            type="button">
                        {{ translate('place_order') }}
                    </button>
                @else
                    <button class="btn btn-primary text-capitalize {{ $cart->count() <= 0 ? 'custom-disabled' : '' }}"
                            id="proceed-to-next-action"
                            data-goto-checkout="{{ route('customer.choose-shipping-address-other') }}"
                            data-checkout-payment="{{ route('checkout-payment') }}"
                            {{ isset($isProductNullStatus) && $isProductNullStatus == 1 ? 'disabled' : '' }}
                            type="button">
                        {{ translate('proceed_to_next') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@push('script')
    <script>
        "use strict";
        $(document).ready(function() {
            // Toggle wallet balance info in sidebar when wallet radio is selected/deselected
            $(document).on('change', 'input[type="radio"]', function() {
                var walletInfo = $('#wallet-balance-info');
                if (walletInfo.length) {
                    if ($('input[name="payment_method"][value="wallet_payment"]').is(':checked')) {
                        walletInfo.removeClass('d-none');
                    } else {
                        walletInfo.addClass('d-none');
                    }
                }
            });
        });
    </script>
@endpush
