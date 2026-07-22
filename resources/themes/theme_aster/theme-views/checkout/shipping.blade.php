@extends('theme-views.layouts.app')

@section('title', translate('shopping_Details') . ' | ' . $web_config['company_name'] . ' ' . translate('ecommerce'))

@section('content')
    <main class="main-content d-flex flex-column gap-3 py-3 mb-5">
        <div class="container">
            <h4 class="text-center mb-3 text-capitalize">{{ translate('shipping_details') }}</h4>
            <div class="row">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <div class="card h-100">
                        <div class="card-body  px-sm-4">
                            <div class="d-flex justify-content-center mb-30">
                                <ul class="cart-step-list">
                                    <li class="done cursor-pointer get-view-by-onclick" data-link="{{ route('shop-cart') }}">
                                        <span><i class="bi bi-check2"></i></span> {{ translate('cart') }}</li>
                                    <li class="current cursor-pointer get-view-by-onclick text-capitalize"
                                        data-link="{{ route('checkout-details') }}"><span><i
                                                class="bi bi-check2"></i></span> {{ translate('checkout') }}
                                    </li>
                                </ul>
                            </div>
                            <input type="hidden" id="physical-product" name="physical_product"
                                value="{{ $physical_product_view ? 'yes' : 'no' }}">
                            <input type="hidden" id="billing-input-enable" name="billing_input_enable"
                                value="{{ $billing_input_by_customer }}">
                            {{-- =========================================================
                                 PAYMENT METHOD SELECTION (Consolidated Checkout)
                                 ========================================================= --}}
                            <div class="mb-4 payment-method-list-page" id="payment-methods-section">
                                <h5 class="mb-4 text-capitalize">{{ translate('payment_method') }}</h5>

                                @if (!$activeMinimumMethods)
                                    <div class="d-flex justify-content-center py-5 align-items-center">
                                        <div class="text-center">
                                            <img src="{{ theme_asset(path: 'assets/img/not_found.png') }}" alt=""
                                                class="mb-4" width="70">
                                            <h5 class="fs-14 text-muted">
                                                {{ translate('payment_methods_are_not_available_at_this_time.') }}</h5>
                                        </div>
                                    </div>
                                @else
                                    <div class="mb-30">
                                        <ul class="option-select-btn d-grid flex-wrap gap-3">
                                            @if ($cashOnDeliveryBtnShow && $cash_on_delivery['status'])
                                                <li>
                                                    <form action="{{ route('checkout-complete') }}" method="get"
                                                        class="checkout-payment-form payment-method-form checkout-cash-on-payment">
                                                        <label class="w-100">
                                                            <input type="radio" hidden name="payment_method" checked
                                                                value="cash_on_delivery" data-form=".checkout-cash-on-payment">
                                                            <button type="submit"
                                                                class="payment-method payment-method_parent next-btn-enable d-flex align-items-center overflow-hidden flex-column p-0 w-100 border-selected">
                                                                <div class="d-flex align-items-center gap-3 pt-1">
                                                                    <img width="30" class="dark-support" alt=""
                                                                        src="{{ theme_asset('assets/img/icons/cash-on.png') }}">
                                                                    <span class="text-capitalize fs-16">{{ translate('cash_on_delivery') }}</span>
                                                                </div>
                                                                <div class="w-100">
                                                                    <div class="collapse show" id="bring_change_amount"
                                                                        data-more="{{ translate('See_More') }}"
                                                                        data-less="{{ translate('See_Less') }}">
                                                                        <div class="bg-primary-op-05 border border-white rounded text-start p-3 mx-3 my-2">
                                                                            <h6 class="fs-12 fw-semibold mb-1">
                                                                                {{ translate('Change_Amount') }}
                                                                                ({{ getCurrencySymbol(type: 'web') }})
                                                                            </h6>
                                                                            <p class="mb-0 fs-12 opacity-75 fw-normal text-transform-none">
                                                                                {{ translate('Insert_amount_if_you_need_deliveryman_to_bring') }}
                                                                            </p>
                                                                            <input type="text"
                                                                                class="form-control mt-2 only-integer-input-field"
                                                                                placeholder="{{ translate('Amount') }}"
                                                                                name="bring_change_amount">
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-center">
                                                                        <a id="bring_change_amount_btn"
                                                                            class="btn primary-color border-0 fs-12 text-center text-capitalize shadow-none border-0 base-color p-0"
                                                                            data-bs-toggle="collapse"
                                                                            href="#bring_change_amount" role="button"
                                                                            aria-expanded="false" aria-controls="change_amount">
                                                                            {{ translate('See_Less') }}
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </button>
                                                        </label>
                                                    </form>
                                                </li>
                                            @endif

                                            @if (auth('customer')->check() && $wallet_status == 1)
                                                <li>
                                                    <div class="payment-method-form">
                                                        <input type="radio" hidden name="payment_method" value="wallet_payment">
                                                        <label class="w-100">
                                                            <button
                                                                class="payment-method payment-method_parent next-btn-enable d-flex align-items-center gap-3 overflow-hidden w-100"
                                                                type="button">
                                                                <img width="30"
                                                                    src="{{ theme_asset('assets/img/icons/wallet.png') }}"
                                                                    class="dark-support" alt="">
                                                                <span class="fs-16">{{ translate('wallet') }}</span>
                                                            </button>
                                                        </label>
                                                    </div>
                                                </li>
                                            @endif

                                            @if (isset($offline_payment) && $offline_payment['status'] && count($offline_payment_methods) > 0)
                                                <li>
                                                    <div class="payment-method-form">
                                                        <input type="radio" hidden name="payment_method" value="offline_payment">
                                                        <label class="w-100">
                                                            <span
                                                                class="payment-method payment-method_parent next-btn-enable d-flex align-items-center gap-3 overflow-hidden">
                                                                <img width="30"
                                                                    src="{{ theme_asset('assets/img/icons/cash-payment.png') }}"
                                                                    class="dark-support" alt="">
                                                                <span class="fs-16">{{ translate('offline_payment') }}</span>
                                                            </span>
                                                        </label>
                                                    </div>
                                                </li>
                                            @endif

                                            @if ($digital_payment['status'] == 1)
                                                @if (count($payment_gateways_list) > 0 ||
                                                    (isset($offline_payment) && $offline_payment['status'] && count($offline_payment_methods) > 0))
                                                    <li>
                                                        <label id="digital-payment-btn" class="w-100">
                                                            <span class="payment-method payment-method_parent d-flex align-items-center gap-3">
                                                                <img width="30"
                                                                    src="{{ theme_asset('assets/img/icons/degital-payment.png') }}"
                                                                    class="dark-support" alt="">
                                                                <span class="fs-16">{{ translate('Digital_Payment') }}</span>
                                                            </span>
                                                        </label>
                                                    </li>

                                                    @foreach ($payment_gateways_list as $payment_gateway)
                                                        @php $additionalData = $payment_gateway['additional_data'] != null ? json_decode($payment_gateway['additional_data']) : []; @endphp
                                                        <?php
                                                        $gatewayImgPath = dynamicAsset(path: 'public/assets/back-end/img/modal/payment-methods/' . $payment_gateway->key_name . '.png');
                                                        if ($additionalData != null && $additionalData?->gateway_image && file_exists(base_path('storage/app/public/payment_modules/gateway_image/' . $additionalData->gateway_image))) {
                                                            $gatewayImgPath = $additionalData->gateway_image ? dynamicStorage(path: 'storage/app/public/payment_modules/gateway_image/' . $additionalData->gateway_image) : $gatewayImgPath;
                                                        }
                                                        ?>

                                                        <li>
                                                            <form method="post"
                                                                class="digital-payment d--none payment-method-form checkout-payment-{{ $payment_gateway->key_name }}"
                                                                action="{{ route('customer.web-payment-request') }}">
                                                                @csrf
                                                                <input type="text" hidden name="user_id"
                                                                    value="{{ auth('customer')->check() ? auth('customer')->id() : session('guest_id') }}">
                                                                <input type="text" hidden name="customer_id"
                                                                    value="{{ auth('customer')->check() ? auth('customer')->id() : session('guest_id') }}">
                                                                <input type="radio" hidden name="payment_method"
                                                                    value="{{ $payment_gateway->key_name }}"
                                                                    data-form=".checkout-payment-{{ $payment_gateway->key_name }}">
                                                                <input type="text" hidden name="payment_platform"
                                                                    value="web">
                                                                @if ($payment_gateway->mode == 'live' && isset($payment_gateway->live_values['callback_url']))
                                                                    <input type="text" hidden name="callback"
                                                                        value="{{ $payment_gateway->live_values['callback_url'] }}">
                                                                @elseif ($payment_gateway->mode == 'test' && isset($payment_gateway->test_values['callback_url']))
                                                                    <input type="text" hidden name="callback"
                                                                        value="{{ $payment_gateway->test_values['callback_url'] }}">
                                                                @else
                                                                    <input type="text" hidden name="callback"
                                                                        value="">
                                                                @endif
                                                                <input type="text" hidden name="external_redirect_link"
                                                                    value="{{ route('web-payment-success') }}">

                                                                <label class="w-100">
                                                                    @php $additional_data = $payment_gateway['additional_data'] != null ? json_decode($payment_gateway['additional_data']) : []; @endphp
                                                                    <button
                                                                        class="payment-method next-btn-enable d-flex align-items-center gap-3 digital-payment-card overflow-hidden w-100"
                                                                        type="submit">
                                                                        @if (!empty($gatewayImgPath))
                                                                            <img width="100" class="dark-support"
                                                                                alt=""
                                                                                src="{{ $gatewayImgPath }}">
                                                                        @else
                                                                            <h4>{{ ucwords(str_replace('_', ' ', $payment_gateway->key_name ?? '')) }}
                                                                            </h4>
                                                                        @endif
                                                                    </button>
                                                                </label>
                                                            </form>
                                                        </li>
                                                    @endforeach
                                                @endif
                                            @endif
                                        </ul>

                                        {{-- Hidden wallet payment form (no modal) --}}
                                        @if (auth('customer')->check() && $wallet_status == 1)
                                            <form id="wallet_payment_form" action="{{ route('checkout-complete-wallet') }}" method="post" class="d-none">
                                                @csrf
                                                <input type="hidden" name="idempotency_key" value="{{ session('customer_wallet_checkout_idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                                            </form>
                                        @endif

                                        @if (isset($offline_payment) && $offline_payment['status'])
                                            <div class="modal fade" id="offline_payment_submit_button">
                                                <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ translate('offline_Payment') }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Close"></button>
                                                        </div>
                                                        <form action="{{ route('offline-payment-checkout-complete') }}"
                                                            method="post" class="needs-validation form-loading-button-form">
                                                            @csrf
                                                            <div class="modal-body p-3 p-md-5">
                                                                <div class="text-center px-5">
                                                                    <img src="{{ theme_asset('assets/img/offline-payments.png') }}"
                                                                        alt="">
                                                                    <p class="py-2">
                                                                        {{ translate('pay_your_bill_using_any_of_the_payment_method_below_and_input_the_required_information_in_the_form') }}
                                                                    </p>
                                                                </div>
                                                                <div>
                                                                    <select class="form-select" id="pay-offline-method"
                                                                        name="payment_by" required>
                                                                        <option value="">{{ translate('select_Payment_Method') }}</option>
                                                                        @foreach ($offline_payment_methods as $method)
                                                                            <option value="{{ $method->id }}">
                                                                                {{ translate('payment_Method') . ' : ' . $method->method_name }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                                <div id="method-filed-div">
                                                                    <div class="text-center py-5">
                                                                        <img class="pt-5"
                                                                            src="{{ theme_asset('assets/img/offline-payments-vectors.png') }}"
                                                                            alt="">
                                                                        <p class="py-2 pb-5 text-muted">
                                                                            {{ translate('select_a_payment_method first') }}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if ($physical_product_view)
                                <form method="post" id="address-form">
                                    <h5 class="mb-3 text-capitalize">{{ translate('delivery_information_details') }}</h5>

                                    <div class="card">
                                        <div class="card-body" id="collapseThree">
                                            <div
                                                class="bg-light p-3 rounded d-flex flex-wrap justify-content-between gap-3 mb-3">
                                                <h6 class="text-capitalize">{{ translate('Shipping_Address') }}</h6>
                                                @if (auth('customer')->check())
                                                    <a href="javascript:" type="button" data-bs-toggle="modal"
                                                        data-bs-target="#shippingSavedAddressModal"
                                                        class="btn-link text-primary text-capitalize">{{ translate('Saved_Address') }}</a>
                                                @endif
                                            </div>

                                            @if (auth('customer')->check())
                                                <div class="modal fade" id="shippingSavedAddressModal"
                                                    data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
                                                    aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered justify-content-center">
                                                        <div class="modal-content border-0">
                                                            <div class="modal-header">
                                                                <h5 class="text-capitalize" id="contact_sellerModalLabel">
                                                                    {{ translate('saved_addresses') }}</h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>

                                                            <div class="modal-body custom-scrollbar">
                                                                <div class="product-quickview">
                                                                    <div
                                                                        class="shipping-saved-addresses {{ $shipping_addresses->count() < 1 ? 'd--none' : '' }}">
                                                                        <div class="row gy-3 text-dark py-4">
                                                                            @foreach ($shipping_addresses as $key => $address)
                                                                                <div class="col-md-12">
                                                                                    <div class="card border-0">
                                                                                        <div
                                                                                            class="card-header bg-transparent gap-2 align-items-center d-flex flex-wrap justify-content-between">
                                                                                            <label
                                                                                                class="d-flex align-items-center gap-3 cursor-pointer mb-0">
                                                                                                <input type="radio"
                                                                                                    name="shipping_method_id"
                                                                                                    value="{{ $address['id'] }}"
                                                                                                    {{ $key == 0 ? 'checked' : '' }}>
                                                                                                <h6>{{ $address['address_type'] }}
                                                                                                </h6>
                                                                                            </label>
                                                                                            <div
                                                                                                class="d-flex align-items-center gap-3">
                                                                                                <button type="button"
                                                                                                    onclick="location.href='{{ route('address-edit', ['id' => $address->id]) }}'"
                                                                                                    class="p-0 bg-transparent border-0">
                                                                                                    <img src="{{ theme_asset('assets/img/svg/location-edit.svg') }}"
                                                                                                        alt=""
                                                                                                        class="svg">
                                                                                                </button>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="card-body">
                                                                                            <address>
                                                                                                <dl
                                                                                                    class="mb-0 flexible-grid sm-down-1 width--5rem">
                                                                                                    <dt>{{ translate('name') }}
                                                                                                    </dt>
                                                                                                    <dd
                                                                                                        class="shipping-contact-person">
                                                                                                        {{ $address['contact_person_name'] }}
                                                                                                    </dd>

                                                                                                    <dt>{{ translate('phone') }}
                                                                                                    </dt>
                                                                                                    <dd class="">
                                                                                                        <a href="tel:{{ $address['phone'] }}"
                                                                                                            class="text-dark shipping-contact-phone">{{ $address['phone'] }}</a>
                                                                                                    </dd>

                                                                                                    <dt>{{ translate('address') }}
                                                                                                    </dt>
                                                                                                    <dd>{{ $address['address'] }}
                                                                                                        ,
                                                                                                        {{ $address['city'] }}
                                                                                                        ,
                                                                                                        {{ $address['zip'] }}
                                                                                                    </dd>
                                                                                                    <span
                                                                                                        class="shipping-contact-address d-none">{{ $address['address'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-city d-none">{{ $address['city'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-zip d-none">{{ $address['zip'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-country d-none">{{ $address['country'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-address-type d-none">{{ $address['address_type'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-latitude-type d-none">{{ $address['latitude'] }}</span>
                                                                                                    <span
                                                                                                        class="shipping-contact-longitude-type d-none">{{ $address['longitude'] }}</span>
                                                                                                </dl>
                                                                                            </address>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                    <div
                                                                        class="text-center {{ $shipping_addresses->count() > 0 ? 'd--none' : '' }}">
                                                                        <img src="{{ theme_asset('assets/img/svg/address.svg') }}"
                                                                            alt="address" class="w-25">
                                                                        <h5 class="my-3 pt-1 text-muted">
                                                                            {{ translate('no_address_is_saved') }}!
                                                                        </h5>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">{{ translate('close') }}</button>
                                                                <button type="button" class="btn btn-primary"
                                                                    data-bs-dismiss="modal">{{ translate('save') }}</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <div class="col-sm-{{ auth('customer')->check() ? 6 : 12 }}">
                                                            <div class="form-group mb-4">
                                                                <label for="name"
                                                                    class="text-capitalize">{{ translate('contact_person_name') }}
                                                                    <span class="text-danger">*</span></label>
                                                                <input type="text" name="contact_person_name"
                                                                    id="name" class="form-control"
                                                                    placeholder="{{ translate('ex') }}: {{ translate('Jhon_Doe') }}"
                                                                    {{ $shipping_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="phone">{{ translate('phone') }} <span
                                                                        class="text-danger">*</span></label>
                                                                <input type="tel" id="phoneNumber" name="phone"
                                                                    class="form-control"
                                                                    placeholder="{{ translate('ex') }}: {{ translate('+8801000000000') }}"
                                                                    {{ $shipping_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>
                                                        @if (!auth('customer')->check())
                                                            <div class="col-sm-6">
                                                                <div class="form-group mb-4">
                                                                    <label for="email">{{ translate('email') }} <span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="email" name="email" id="email"
                                                                        class="form-control"
                                                                        placeholder="{{ translate('ex') }}: {{ translate('email@domain.com') }}"
                                                                        required>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="address-type"
                                                                    class="text-capitalize">{{ translate('address_type') }}</label>
                                                                <select name="address_type" id="address-type"
                                                                    class="form-select">
                                                                    <option value="permanent">{{ translate('permanent') }}
                                                                    </option>
                                                                    <option value="home">{{ translate('home') }}
                                                                    </option>
                                                                    <option value="office">{{ translate('office') }}
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="country">{{ translate('country') }} <span
                                                                        class="text-danger">*</span></label>
                                                                <select name="country" id="country"
                                                                    class="form-control select_picker select2">
                                                                    @forelse($countries as $country)
                                                                        <option value="{{ $country['name'] }}">
                                                                            {{ $country['name'] }}</option>
                                                                    @empty
                                                                        <option value="">
                                                                            {{ translate('no_country_to_deliver') }}
                                                                        </option>
                                                                    @endforelse
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="city">{{ translate('city') }} <span
                                                                        class="text-danger">*</span></label>
                                                                <input type="text" name="city" id="city"
                                                                    placeholder="{{ translate('ex') }}: {{ translate('dhaka') }}"
                                                                    class="form-control"
                                                                    {{ $shipping_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="city"
                                                                    class="text-capitalize">{{ translate('zip_code') }}
                                                                    <span class="text-danger">*</span></label>
                                                                @if ($zip_restrict_status == 1)
                                                                    <select name="zip" id="zip"
                                                                        class="form-control select2 select_picker"
                                                                        data-live-search="true" required>
                                                                        @forelse($zip_codes as $code)
                                                                            <option value="{{ $code->zipcode }}">
                                                                                {{ $code->zipcode }}</option>
                                                                        @empty
                                                                            <option value="">
                                                                                {{ translate('no_zip_to_deliver') }}
                                                                            </option>
                                                                        @endforelse
                                                                    </select>
                                                                @else
                                                                    <input type="text" class="form-control"
                                                                        id="zip" name="zip"
                                                                        placeholder="{{ translate('ex') }}: {{ translate('1216') }}"
                                                                        {{ $shipping_addresses->count() == 0 ? 'required' : '' }}>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-12">
                                                            <div class="form-group mb-4">
                                                                <div
                                                                    class="d-flex gap-2 align-items-center justify-content-between mb-2">
                                                                    <label for="address"
                                                                        class="mb-0">{{ translate('address') }} <span
                                                                            class="text-danger">*</span></label>
                                                                    @if (getWebConfig('map_api_status') == 1)
                                                                        <a href="javascript:" type="button"
                                                                            data-bs-toggle="modal"
                                                                            data-bs-target="#shippingMapModal"
                                                                            class="btn-link text-primary text-capitalize">{{ translate('Set_Precise_Location') }}
                                                                            <i
                                                                                class="fi fi-sr-land-layer-location d-flex"></i>
                                                                        </a>
                                                                        <div class="modal fade" id="shippingMapModal"
                                                                            tabindex="-1" aria-hidden="true">
                                                                            <div
                                                                                class="modal-dialog modal-lg modal-dialog-centered">
                                                                                <div class="modal-content">
                                                                                    <div class="modal-body">
                                                                                        <div class="product-quickview">
                                                                                            <button type="button"
                                                                                                class="btn-close outside"
                                                                                                data-bs-dismiss="modal"
                                                                                                aria-label="Close"></button>
                                                                                            <input id="pac-input"
                                                                                                class="controls rounded __inline-46"
                                                                                                title="{{ translate('search_your_location_here') }}"
                                                                                                type="text"
                                                                                                placeholder="{{ translate('search_here') }}" />
                                                                                            <div class="dark-support rounded w-100 __h-14rem"
                                                                                                id="location_map_canvas">
                                                                                            </div>
                                                                                            <input type="hidden"
                                                                                                id="latitude"
                                                                                                name="latitude"
                                                                                                class="form-control d-inline"
                                                                                                placeholder="{{ translate('ex') }} : {{ translate('-94.22213') }}"
                                                                                                value="{{ $default_location ? $default_location['lat'] : 0 }}"
                                                                                                required readonly>
                                                                                            <input type="hidden"
                                                                                                name="longitude"
                                                                                                class="form-control"
                                                                                                placeholder="{{ translate('ex') }} : {{ translate('103.344322') }}"
                                                                                                id="longitude"
                                                                                                value="{{ $default_location ? $default_location['lng'] : 0 }}"
                                                                                                required>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <input type="text" name="address" id="address"
                                                                    class="form-control"
                                                                    placeholder="{{ translate('your_address') }}"
                                                                    {{ $shipping_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>

                                                        <div class="col-sm-12">
                                                            <label class="custom-checkbox align-items-center fw-bold"
                                                                id="save-address-label">
                                                                <input type="hidden" name="shipping_method_id"
                                                                    id="shipping-method-id" value="0">
                                                                @if (auth('customer')->check())
                                                                    <input type="checkbox" name="save_address"
                                                                        id="saveAddress">
                                                                    {{ translate('save_this_address') }}
                                                                @endif
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </form>

                                @if (!Auth::guard('customer')->check() && $web_config['guest_checkout_status'])
                                    <div class="card __card mt-3">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                                <div
                                                    class="min-h-45 d-flex gap-2 align-items-center cursor-pointer user-select-none">
                                                    <input type="checkbox" id="is_check_create_account"
                                                        name="is_check_create_account">
                                                    <label class="fw-bold fs-13 mb-0" for="is_check_create_account">
                                                        {{ translate('Create_an_account_with_the_above_info') }}
                                                    </label>
                                                </div>

                                                <div class="is_check_create_account_password_group d--none">
                                                    <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_password" type="password"
                                                                    id="customer_password" class="form-control"
                                                                    placeholder="{{ translate('new_Password') }}"
                                                                    required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                            </div>
                                                        </div>
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_confirm_password" type="password"
                                                                    id="customer_confirm_password" class="form-control"
                                                                    placeholder="{{ translate('confirm_Password') }}"
                                                                    required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @else
                                {{-- =========================================================
                                     DIGITAL-ONLY CHECKOUT — No shipping address required
                                     ========================================================= --}}
                                <div class="card mt-3">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start gap-3 mb-3 p-3 rounded bg-soft-primary">
                                            <i class="bi bi-lightning-charge fs-4 text-primary flex-shrink-0 mt-1"></i>
                                            <div>
                                                <h5 class="mb-1 fs-15 fw-semibold">
                                                    {{ translate('Instant_Digital_Delivery') }}</h5>
                                                <p class="mb-0 text-muted fs-13">
                                                    {{ translate('Your_digital_code(s)_will_be_delivered_to_your_email_immediately_after_payment_is_confirmed._No_shipping_address_is_required.') }}
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            class="d-flex align-items-center gap-2 mb-3 p-2 rounded border border-danger bg-soft-danger">
                                            <i class="bi bi-x-circle text-danger fs-16"></i>
                                            <span
                                                class="text-danger fs-13">{{ translate('Digital_products_are_non-returnable_and_non-refundable_once_the_code_is_delivered.') }}</span>
                                        </div>

                                        <form action="{{ route('digital-checkout-proceed') }}" method="POST"
                                            id="digital-checkout-form">
                                            @csrf
                                            <h5 class="fs-15 fw-semibold mb-3">
                                                <i class="bi bi-envelope me-1"></i>
                                                {{ translate('Confirm_your_delivery_email') }}
                                            </h5>
                                            <p class="text-muted fs-13 mb-3">
                                                {{ translate('Your_digital_code(s)_will_be_sent_to_this_email_address.') }}
                                            </p>
                                            <div class="form-group mb-0">
                                                <label class="fw-semibold fs-14">
                                                    {{ translate('Email_address') }}
                                                    <span class="text-danger">*</span>
                                                </label>
                                                <input type="email" class="form-control" name="digital_delivery_email"
                                                    value="{{ auth('customer')->check() ? auth('customer')->user()?->email : old('digital_delivery_email') }}"
                                                    placeholder="{{ translate('your@email.com') }}" required>
                                                @if (auth('customer')->check())
                                                    <small class="text-muted mt-1 d-block">
                                                        <i class="bi bi-check-circle text-success"></i>
                                                        {{ translate('This_is_your_account_email._You_can_change_it_if_needed.') }}
                                                    </small>
                                                @endif
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            @if ($billing_input_by_customer && $physical_product_view)
                                <div class="card card-body mt-3 {{ $billing_input_by_customer ? '' : 'd-none' }}">
                                    <div class="bg-light rounded p-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-3">
                                            <h6 class="text-capitalize">{{ translate('billing_address') }}</h6>
                                            <div class="d-flex gap-3 align-items-center flex-wrap">
                                                @if (auth('customer')->check())
                                                    <a href="javascript:" type="button" data-bs-toggle="modal"
                                                        data-bs-target="#billingSavedAddressModal"
                                                        class="btn-link text-primary text-capitalize">{{ translate('Saved_Address') }}</a>
                                                @endif
                                                @if ($physical_product_view)
                                                    <label class="custom-checkbox" class="text-capitalize">
                                                        {{ translate('same_as_delivery_address') }}
                                                        <input type="checkbox" id="same-as-shipping-address"
                                                            name="same_as_shipping_address"
                                                            class="billing-address-checkbox" checked>
                                                    </label>
                                                @endif
                                            </div>

                                        </div>
                                    </div>

                                    @if (!$physical_product_view)
                                        <div class="mt-3 alert--info">
                                            <div class="d-flex align-items-center gap-2">
                                                <img class="mb-1"
                                                    src="{{ theme_asset('assets/img/icons/info-light.svg') }}"
                                                    alt="Info">
                                                <span>{{ translate('When_you_input_all_the_required_information_for_this_billing_address_it_will_be_stored_for_future_purchases') }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    @if (auth('customer')->check())
                                        <div class="modal fade" id="billingSavedAddressModal" data-bs-backdrop="static"
                                            data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
                                            <div
                                                class="modal-dialog modal-lg modal-dialog-centered justify-content-center">
                                                <div class="modal-content border-0 max-width-500">
                                                    <div class="modal-header">
                                                        <h5 class="text-capitalize" id="contact_sellerModalLabel">
                                                            {{ translate('saved_addresses') }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>

                                                    <div class="modal-body custom-scrollbar">
                                                        <div class="product-quickview">
                                                            <div
                                                                class="billing-saved-addresses {{ $billing_addresses->count() < 1 ? 'd--none' : '' }}">
                                                                <div class="row gy-3 text-dark py-4">
                                                                    @foreach ($billing_addresses as $key => $address)
                                                                        <div class="col-md-12">
                                                                            <div class="card border-0 ">
                                                                                <div
                                                                                    class="card-header bg-transparent gap-2 align-items-center d-flex flex-wrap justify-content-between">
                                                                                    <label
                                                                                        class="d-flex align-items-center gap-3 cursor-pointer mb-0">
                                                                                        <input type="radio"
                                                                                            value="{{ $address['id'] }}"
                                                                                            name="billing_method_id"
                                                                                            {{ $key == 0 ? 'checked' : '' }}>
                                                                                        <h6>{{ $address['address_type'] }}
                                                                                        </h6>
                                                                                    </label>
                                                                                    <div
                                                                                        class="d-flex align-items-center gap-3">
                                                                                        <button type="button"
                                                                                            onclick="location.href='{{ route('address-edit', ['id' => $address->id]) }}'"
                                                                                            class="p-0 bg-transparent border-0">
                                                                                            <img src="{{ theme_asset('assets/img/svg/location-edit.svg') }}"
                                                                                                alt=""
                                                                                                class="svg">
                                                                                        </button>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="card-body pb-0">
                                                                                    <address>
                                                                                        <dl
                                                                                            class="mb-0 flexible-grid sm-down-1 width--5rem">
                                                                                            <dt>{{ translate('name') }}
                                                                                            </dt>
                                                                                            <dd
                                                                                                class="billing-contact-name">
                                                                                                {{ $address['contact_person_name'] }}
                                                                                            </dd>

                                                                                            <dt>{{ translate('phone') }}
                                                                                            </dt>
                                                                                            <dd class="">
                                                                                                <a href="tel:{{ $address['phone'] }}"
                                                                                                    class="text-dark billing-contact-phone">{{ $address['phone'] }}</a>
                                                                                            </dd>

                                                                                            <dt>{{ translate('address') }}
                                                                                            </dt>
                                                                                            <dd>{{ $address['address'] }}
                                                                                                , {{ $address['city'] }}
                                                                                                , {{ $address['zip'] }}
                                                                                            </dd>
                                                                                            <span
                                                                                                class="billing-contact-address d-none">{{ $address['address'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-city d-none">{{ $address['city'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-zip d-none">{{ $address['zip'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-country d-none">{{ $address['country'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-address-type d-none">{{ $address['address_type'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-latitude-type d-none">{{ $address['latitude'] }}</span>
                                                                                            <span
                                                                                                class="billing-contact-longitude-type d-none">{{ $address['longitude'] }}</span>
                                                                                        </dl>
                                                                                    </address>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="text-center {{ $billing_addresses->count() > 0 ? 'd--none' : '' }}">
                                                                <img src="{{ theme_asset('assets/img/svg/address.svg') }}"
                                                                    alt="address" class="w-25">
                                                                <h5 class="my-3 pt-1 text-muted">
                                                                    {{ translate('no_address_is_saved') }}!
                                                                </h5>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">{{ translate('close') }}</button>
                                                        <button type="button" class="btn btn-primary"
                                                            data-bs-dismiss="modal">{{ translate('save') }}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    <form method="post" id="billing-address-form">
                                        <div class="toggle-billing-address mt-3 d--none" id="hide-billing-address">
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <div class="col-sm-{{ auth('customer')->check() ? 6 : 12 }}">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-contact-person-name"
                                                                    class="text-capitalize">{{ translate('contact_person_name') }}
                                                                    <span class="text-danger">*</span></label>
                                                                <input type="text" name="billing_contact_person_name"
                                                                    id="billing-contact-person-name" class="form-control"
                                                                    placeholder="{{ translate('ex') }}: {{ translate('Jhon_Doe') }}"
                                                                    {{ $billing_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-phone">
                                                                    {{ translate('phone') }} <span
                                                                        class="text-danger">*</span>
                                                                </label>
                                                                <input type="tel" name="billing_phone"
                                                                    id="billing-phone" class="form-control"
                                                                    placeholder="{{ translate('ex') }}: {{ translate('+88 01000000000') }}"
                                                                    {{ $billing_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>
                                                        @if (!auth('customer')->check())
                                                            <div class="col-sm-6">
                                                                <div class="form-group mb-4">
                                                                    <label for="billing_contact_email">
                                                                        {{ translate('email') }} <span
                                                                            class="text-danger">*</span>
                                                                    </label>
                                                                    <input type="email" name="billing_contact_email"
                                                                        id="billing-contact-email" class="form-control"
                                                                        placeholder="{{ translate('ex') }}: {{ translate('email@domain.com') }}"
                                                                        required>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing_address_type"
                                                                    class="text-capitalize">{{ translate('address_type') }}</label>
                                                                <select name="billing_address_type"
                                                                    id="billing-address-type" class="form-select">
                                                                    <option value="permanent">
                                                                        {{ translate('permanent') }}</option>
                                                                    <option value="home">{{ translate('home') }}
                                                                    </option>
                                                                    <option value="office">{{ translate('office') }}
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-country">{{ translate('country') }}
                                                                    <span class="text-danger">*</span></label>
                                                                <select name="billing_country" id="billing-country"
                                                                    class="form-control select_picker select2">
                                                                    @forelse($countries as $country)
                                                                        <option value="{{ $country['name'] }}">
                                                                            {{ $country['name'] }}</option>
                                                                    @empty
                                                                        <option value="">
                                                                            {{ translate('no_country_to_deliver') }}
                                                                        </option>
                                                                    @endforelse
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-city">{{ translate('city') }} <span
                                                                        class="text-danger">*</span></label>
                                                                <input type="text" name="billing_city"
                                                                    id="billing-city"
                                                                    placeholder="{{ translate('ex') }}: {{ translate('Dhaka') }}"
                                                                    class="form-control"
                                                                    {{ $billing_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group mb-4">
                                                                <label for="billing-zip">{{ translate('Zip_Code') }}
                                                                    <span class="text-danger">*</span></label>
                                                                @if ($zip_restrict_status == 1)
                                                                    <select name="billing_zip" id="billing-zip"
                                                                        class="form-control select2 select_picker"
                                                                        data-live-search="true" required>
                                                                        @forelse($zip_codes as $code)
                                                                            <option value="{{ $code->zipcode }}">
                                                                                {{ $code->zipcode }}</option>
                                                                        @empty
                                                                            <option value="">
                                                                                {{ translate('no_zip_to_deliver') }}
                                                                            </option>
                                                                        @endforelse
                                                                    </select>
                                                                @else
                                                                    <input type="text" class="form-control"
                                                                        id="billing-zip" name="billing_zip"
                                                                        placeholder="{{ translate('ex') }}: {{ translate('1216') }}"
                                                                        {{ $billing_addresses->count() == 0 ? 'required' : '' }}>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-12">
                                                            <div class="form-group mb-4">
                                                                <div
                                                                    class="d-flex gap-2 align-items-center justify-content-between mb-2">
                                                                    <label class="mb-0"
                                                                        for="billing_address">{{ translate('address') }}
                                                                        <span class="text-danger">*</span></label>
                                                                    @if (getWebConfig('map_api_status') == 1)
                                                                        <a href="javascript:" data-bs-toggle="modal"
                                                                            data-bs-target="#billingMapModal"
                                                                            class="btn-link text-primary text-capitalize">
                                                                            {{ translate('Set_Precise_Location') }}
                                                                            <i
                                                                                class="fi fi-sr-land-layer-location d-flex"></i>
                                                                        </a>
                                                                        <div class="modal fade" id="billingMapModal"
                                                                            tabindex="-1" aria-hidden="true">
                                                                            <div
                                                                                class="modal-dialog modal-lg modal-dialog-centered">
                                                                                <div class="modal-content">
                                                                                    <div class="modal-body">
                                                                                        <div class="product-quickview">
                                                                                            <button type="button"
                                                                                                class="btn-close outside"
                                                                                                data-bs-dismiss="modal"
                                                                                                aria-label="Close"></button>
                                                                                            <input id="pac-input-billing"
                                                                                                class="controls rounded __inline-46"
                                                                                                title="{{ translate('search_your_location_here') }}"
                                                                                                type="text"
                                                                                                placeholder="{{ translate('search_here') }}" />
                                                                                            <div class="dark-support rounded w-100 __h-14rem"
                                                                                                id="billing-location-map-canvas">
                                                                                            </div>
                                                                                            <input type="hidden"
                                                                                                id="billing-latitude"
                                                                                                name="billing_latitude"
                                                                                                class="form-control d-inline"
                                                                                                placeholder="{{ translate('ex') }} : {{ translate('-94.22213') }}"
                                                                                                value="{{ $default_location ? $default_location['lat'] : 0 }}"
                                                                                                required readonly>
                                                                                            <input type="hidden"
                                                                                                name="billing_longitude"
                                                                                                class="form-control"
                                                                                                placeholder="{{ translate('ex') }} : {{ translate('103.344322') }}"
                                                                                                id="billing-longitude"
                                                                                                value="{{ $default_location ? $default_location['lng'] : 0 }}"
                                                                                                required>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <input type="text" name="billing_address"
                                                                    id="billing_address" class="form-control"
                                                                    placeholder="{{ translate('your_address') }}"
                                                                    {{ $shipping_addresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>

                                                        <input type="hidden" name="billing_method_id"
                                                            id="billing-method-id" value="0">
                                                        @if (auth('customer')->check())
                                                            <div class="col-sm-12">
                                                                <label class="custom-checkbox save-billing-address fw-bold"
                                                                    id="save-billing-address-label">
                                                                    <input type="checkbox" name="save_address_billing"
                                                                        id="save_address_billing">
                                                                    {{ translate('save_this_address') }}
                                                                </label>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </form>
                                </div>

                                @if (!Auth::guard('customer')->check() && $web_config['guest_checkout_status'] && !$physical_product_view)
                                    <div class="card __card mt-3">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                                <div
                                                    class="d-flex gap-2 align-items-center cursor-pointer user-select-none">
                                                    <input type="checkbox" id="is_check_create_account"
                                                        name="is_check_create_account">
                                                    <label class="fw-bold fs-13 text-capitalize mb-0"
                                                        for="is_check_create_account">
                                                        {{ translate('Create_an_account_with_the_above_info') }}
                                                    </label>
                                                </div>

                                                <div class="is_check_create_account_password_group d--none">
                                                    <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_password" type="password"
                                                                    id="customer_password" class="form-control"
                                                                    placeholder="{{ translate('new_Password') }}"
                                                                    required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                            </div>
                                                        </div>
                                                        <div class="">
                                                            <div class="input-inner-end-ele">
                                                                <input name="customer_confirm_password" type="password"
                                                                    id="customer_confirm_password" class="form-control"
                                                                    placeholder="{{ translate('confirm_Password') }}"
                                                                    required="">
                                                                <i class="bi bi-eye-slash-fill togglePassword"></i>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                @include('theme-views.partials._order-summery')
            </div>
        </div>
    </main>

    <span id="shipping-address-location" data-latitude="{{ $default_location ? $default_location['lat'] : '' }}"
        data-longitude="{{ $default_location ? $default_location['lng'] : '' }}">
    </span>
    <span class="get-payment-method-list" data-action="{{ route('pay-offline-method-list') }}"></span>
@endsection
@push('script')
    <script src="{{ theme_asset('assets/js/payment-page.js') }}"></script>
    <script src="{{ theme_asset('assets/js/shipping-page.js') }}"></script>

    @if (getWebConfig('map_api_status') == 1)
        <script
            src="https://maps.googleapis.com/maps/api/js?key={{ getWebConfig('map_api_key') }}&callback=mapsLoading&loading=async&libraries=places&v=3.56"
            defer></script>
    @endif

    <script>
        // Digital-only checkout: save email via AJAX, then submit the selected payment form.
        if ($('#digital-checkout-form').length > 0) {
            $('#proceed-to-next-action').off('click').on('click', function() {
                var emailField = $('#digital-checkout-form [name="digital_delivery_email"]');
                if (!emailField.val() || !emailField[0].checkValidity()) {
                    emailField[0].reportValidity();
                    return;
                }

                var $checkedRadio = $(".payment-method-list-page").find('input[type="radio"]:checked');
                if ($checkedRadio.length === 0) {
                    toastr.error('Please select a payment method to proceed.');
                    return;
                }
                $.ajaxSetup({
                    headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') }
                });
                $.post({
                    url: '{{ route("digital-checkout-proceed") }}',
                    data: $('#digital-checkout-form').serialize(),
                    beforeSend: function() { $('#loading').addClass('d-grid'); },
                    success: function(data) {
                        if (data.success) {
                            let $checkedRadio = $(".payment-method-list-page")
                                .find('input[type="radio"]:checked');
                            let formId = $checkedRadio.data("form");
                            let paymentValue = $checkedRadio.val();

                            if (paymentValue === "wallet_payment") {
                                $('#wallet_payment_form').submit();
                            } else if (paymentValue === "offline_payment") {
                                new bootstrap.Modal(document.getElementById('offline_payment_submit_button')).show();
                            } else if (formId && formId !== "") {
                                $(formId).submit();
                            }
                        }
                    },
                    complete: function() { $('#loading').removeClass('d-grid'); },
                    error: function(xhr) {
                        var errors = xhr.responseJSON?.errors;
                        if (errors) {
                            Object.values(errors).forEach(function(msgs) {
                                toastr.error(msgs[0]);
                            });
                        }
                    }
                });
            });
        }
    </script>
@endpush
