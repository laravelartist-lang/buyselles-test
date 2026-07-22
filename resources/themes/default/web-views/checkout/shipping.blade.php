@extends('layouts.front-end.app')

@section('title', translate('shipping_Address'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/bootstrap-select.min.css') }}">
    <link rel="stylesheet"
        href="{{ theme_asset(path: 'public/assets/front-end/plugin/intl-tel-input/css/intlTelInput.css') }}">
    <link rel="stylesheet" href="{{ theme_asset(path: 'public/assets/front-end/css/payment.css') }}">
@endpush

@section('content')
    @php $billingInputByCustomer = getWebConfig(name: 'billing_input_by_customer'); @endphp
    <div class="container py-4 pt-3 rtl __inline-56 px-0 px-md-3 text-align-direction">
        <div class="row mx-max-md-0">
            <section class="col-lg-8 px-max-md-0">
                <div class="checkout_details">
                    <div class="px-3 px-md-3 mb-20">
                        @include('web-views.partials._checkout-steps', ['step' => 2])
                    </div>
                    @php $defaultLocation = getWebConfig(name: 'default_location'); @endphp

                    {{-- =========================================================
                         PAYMENT METHOD SELECTION (Consolidated Checkout)
                         ========================================================= --}}
                    <div class="mt-4" id="payment-methods-section">
                        <div class="px-3 px-md-0">
                            <h4 class="pb-2 fs-18 text-capitalize">{{ translate('payment_method') }}</h4>
                        </div>
                        <div class="card __card">
                            <div class="card-body p-0">
                                @if (!$activeMinimumMethods)
                                    <div class="d-flex justify-content-center py-3">
                                        <div class="text-center">
                                            <img src="{{ theme_asset(path: 'public/assets/front-end/img/icons/nodata.svg') }}"
                                                alt="" class="mb-4" width="70">
                                            <h5 class="fs-14 text-muted">
                                                {{ translate('payment_methods_are_not_available_at_this_time.') }}</h5>
                                        </div>
                                    </div>
                                @else
                                    <div class="p-20">
                                        @if (
                                            ($cashOnDeliveryBtnShow && $cash_on_delivery['status']) ||
                                                ($digital_payment['status'] ?? 0) == 1 ||
                                                (auth('customer')->check() && $wallet_status == 1))
                                            @if (($cashOnDeliveryBtnShow && $cash_on_delivery['status']) || (auth('customer')->check() && $wallet_status == 1))
                                                <p class="text-capitalize mt-0">
                                                    {{ translate('select_a_payment_method_to_proceed') }}</p>

                                                <div class="d-flex flex-sm-nowrap flex-wrap w-100 gap-3 mb-3">
                                                    @if ($cashOnDeliveryBtnShow && $cash_on_delivery['status'])
                                                        <div id="cod-for-cart" class="w-100 h-100 cod-for-cart">
                                                            <div class="card cursor-pointer">
                                                                <form action="{{ route('checkout-complete') }}" method="get"
                                                                    class="needs-validation" id="cash_on_delivery_form">
                                                                    <label class="m-0 pt-2 pb-1">
                                                                        <input type="hidden" name="payment_method"
                                                                            value="cash_on_delivery" checked>
                                                                        <input type="hidden" class="form-control"
                                                                            name="bring_change_amount"
                                                                            id="bring_change_amount_value">
                                                                        <span
                                                                            class="btn btn-block click-if-alone py-3 d-flex gap-2 align-items-center cursor-pointer">
                                                                            <input type="radio" id="cash_on_delivery"
                                                                                class="custom-radio" checked>
                                                                            <img width="20"
                                                                                src="{{ theme_asset(path: 'public/assets/front-end/img/icons/money.png') }}"
                                                                                alt="">
                                                                            <span class="fs-12">
                                                                                {{ translate('cash_on_Delivery') }}
                                                                            </span>
                                                                        </span>
                                                                    </label>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if (auth('customer')->check() && $wallet_status == 1)
                                                        <div class="w-100 h-100">
                                                            <div class="card cursor-pointer"
                                                                onclick="var r=document.getElementById('wallet_payment'); r.checked=true; r.dispatchEvent(new Event('change', {bubbles: true}));">
                                                                <div
                                                                    class="btn btn-block click-if-alone d-flex justify-content-between gap-2 align-items-center">
                                                                    <div class="d-flex gap-2 align-items-start">
                                                                        <input type="radio" id="wallet_payment"
                                                                            name="online_payment"
                                                                            class="custom-radio flex-shrink-0 mt-1"
                                                                            value="wallet_payment">
                                                                        <img width="20"
                                                                            src="{{ theme_asset(path: 'public/assets/front-end/img/icons/wallet-sm.png') }}"
                                                                            alt="" />
                                                                        <span class="fs-12 text-start">
                                                                            {{ translate('pay_via_Wallet') }} <br>
                                                                            <span
                                                                                class="fs-18 fw-semibold text-dark">{{ webCurrencyConverter(amount: auth('customer')->user()?->wallet_balance ?? 0) }}</span>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                                @if ($cashOnDeliveryBtnShow && $cash_on_delivery['status'])
                                                    <div class="bring_change_amount_section">
                                                        <div class="collapse show mb-10px" id="bring_change_amount"
                                                            data-more="{{ translate('See_More') }}"
                                                            data-less="{{ translate('See_Less') }}">
                                                            <div
                                                                class="bring_change_amount_details row justify-content-start align-items-center rounded-10 g-2 px-3 py-12">
                                                                <div class="col-sm-6">
                                                                    <h6 class="fs-12 mb-1 fw-bold">
                                                                        {{ translate('Bring_Change_Instruction') }}
                                                                    </h6>
                                                                    <p class="mb-0 fs-12 opacity-50">
                                                                        {{ translate('Insert_amount_if_you_need_deliveryman_to_bring') }}
                                                                    </p>
                                                                </div>
                                                                <div class="col-sm-6">
                                                                    <label class="fs-12 fw-bold" for="">
                                                                        {{ translate('Change_Amount') }}
                                                                        ({{ getCurrencySymbol(type: 'web') }})
                                                                    </label>
                                                                    <input type="text"
                                                                        class="form-control max-w-210px only-integer-input-field"
                                                                        id="bring_change_amount_input"
                                                                        placeholder="{{ translate('Amount') }}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-center mb-10px">
                                                            <a id="bring_change_amount_btn"
                                                                class="btn text-center text-capitalize text--primary fs-12 p-0"
                                                                data-toggle="collapse" href="#bring_change_amount"
                                                                role="button" aria-expanded="false"
                                                                aria-controls="change_amount">
                                                                {{ translate('See_Less') }}
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endif

                                            @endif
                                        @endif

                                        <div class="bg-primary-light rounded p-4 mb-20">
                                            @if (
                                                (($digital_payment['status'] ?? 0) == 1 && count($payment_gateways_list) > 0) ||
                                                    (isset($offline_payment) && $offline_payment['status'] && count($offline_payment_methods) > 0))
                                                <div class="gap-2 mb-4">
                                                    <div class="d-flex justify-content-between">
                                                        <div class="d-flex align-items-end gap-2">
                                                            <h5 class="mb-0 text-nowrap">
                                                                {{ translate('pay_via_online') }}
                                                            </h5>
                                                            <span class="fs-10 text-capitalize mt-1">
                                                                ({{ translate('faster_&_secure_way_to_pay') }})
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if (($digital_payment['status'] ?? 0) == 1)
                                                <div class="row gx-4">
                                                    @foreach ($payment_gateways_list as $payment_gateway)
                                                        @php $additionalData = $payment_gateway['additional_data'] != null ? json_decode($payment_gateway['additional_data']) : []; @endphp
                                                        <?php
                                                        $gatewayImgPath = dynamicAsset(path: 'public/assets/back-end/img/modal/payment-methods/' . $payment_gateway->key_name . '.png');
                                                        if ($additionalData != null && $additionalData?->gateway_image && file_exists(base_path('storage/app/public/payment_modules/gateway_image/' . $additionalData->gateway_image))) {
                                                            $gatewayImgPath = $additionalData->gateway_image ? dynamicStorage(path: 'storage/app/public/payment_modules/gateway_image/' . $additionalData->gateway_image) : $gatewayImgPath;
                                                        }
                                                        ?>

                                                        <div class="col-sm-6">
                                                            <form method="post" class="digital_payment"
                                                                id="{{ $payment_gateway->key_name }}_form"
                                                                action="{{ route('customer.web-payment-request') }}">
                                                                @csrf
                                                                <input type="hidden" name="user_id"
                                                                    value="{{ auth('customer')->check() ? auth('customer')->user()->id : session('guest_id') }}">
                                                                <input type="hidden" name="customer_id"
                                                                    value="{{ auth('customer')->check() ? auth('customer')->user()->id : session('guest_id') }}">
                                                                <input type="hidden" name="payment_method"
                                                                    value="{{ $payment_gateway->key_name }}">
                                                                <input type="hidden" name="payment_platform" value="web">

                                                                @if ($payment_gateway->mode == 'live' && isset($payment_gateway->live_values['callback_url']))
                                                                    <input type="hidden" name="callback"
                                                                        value="{{ $payment_gateway->live_values['callback_url'] }}">
                                                                @elseif ($payment_gateway->mode == 'test' && isset($payment_gateway->test_values['callback_url']))
                                                                    <input type="hidden" name="callback"
                                                                        value="{{ $payment_gateway->test_values['callback_url'] }}">
                                                                @else
                                                                    <input type="hidden" name="callback" value="">
                                                                @endif

                                                                <input type="hidden" name="external_redirect_link"
                                                                    value="{{ route('web-payment-success') }}">
                                                                <label
                                                                    class="d-flex align-items-center px-0 gap-2 mb-0 form-check py-2 cursor-pointer">
                                                                    <input type="radio"
                                                                        id="{{ $payment_gateway->key_name }}"
                                                                        name="online_payment"
                                                                        class="form-check-input custom-radio"
                                                                        value="{{ $payment_gateway->key_name }}">
                                                                    <img width="30" src="{{ $gatewayImgPath }}"
                                                                        alt="">
                                                                    <span class="text-capitalize form-check-label">
                                                                        @if ($payment_gateway->additional_data && json_decode($payment_gateway->additional_data)->gateway_title != null)
                                                                            {{ json_decode($payment_gateway->additional_data)->gateway_title }}
                                                                        @else
                                                                            {{ str_replace('_', ' ', $payment_gateway->key_name) }}
                                                                        @endif
                                                                    </span>
                                                                </label>
                                                            </form>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        @if (isset($offline_payment) && $offline_payment['status'] && count($offline_payment_methods) > 0)
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <div class="bg-primary-light rounded p-4">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center gap-2 position-relative">
                                                            <span class="d-flex align-items-center gap-3">
                                                                <input type="radio" id="pay_offline" name="online_payment"
                                                                    class="custom-radio" value="pay_offline">
                                                                <label for="pay_offline"
                                                                    class="cursor-pointer d-flex align-items-center gap-2 mb-0 text-capitalize">{{ translate('pay_offline') }}</label>
                                                            </span>

                                                            <div data-toggle="tooltip"
                                                                title="{{ translate('for_offline_payment_options,_please_follow_the_steps_below') }}">
                                                                <i class="tio-info text-primary"></i>
                                                            </div>
                                                        </div>

                                                        <div class="mt-4 pay_offline_card d-none">
                                                            <div class="d-flex flex-wrap gap-3">
                                                                @foreach ($offline_payment_methods as $method)
                                                                    <button type="button"
                                                                        class="btn btn-light offline_payment_button text-capitalize"
                                                                        id="{{ $method->id }}">{{ $method->method_name }}</button>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if (!$physical_product_view)
                        {{-- =========================================================
                             DIGITAL-ONLY CHECKOUT — No shipping address required
                             ========================================================= --}}
                        <div class="card mt-4">
                            <div class="card-body p-4">
                                {{-- Digital delivery notice --}}
                                <div class="d-flex align-items-start gap-3 mb-4 p-3 rounded"
                                    style="background: rgba(13,110,253,.06); border-left: 4px solid var(--bs-primary, #556ee6);">
                                    <i class="tio-flash-outlined fs-24 web-text-primary flex-shrink-0 mt-1"></i>
                                    <div>
                                        <h5 class="mb-1 fs-15 fw-semibold">{{ translate('Instant_Digital_Delivery') }}</h5>
                                        <p class="mb-0 text-muted fs-13">
                                            {{ translate('Your_digital_code(s)_will_be_delivered_to_your_email_immediately_after_payment_is_confirmed._No_shipping_address_is_required.') }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Non-returnable notice --}}
                                <div class="d-flex align-items-center gap-2 mb-4 p-2 rounded"
                                    style="background: rgba(220,53,69,.06); border: 1px solid rgba(220,53,69,.2);">
                                    <i class="tio-block-outlined text-danger fs-16"></i>
                                    <span
                                        class="text-danger fs-13">{{ translate('Digital_products_are_non-returnable_and_non-refundable_once_the_code_is_delivered.') }}</span>
                                </div>

                                {{-- Email confirmation form --}}
                                <form action="{{ route('digital-checkout-proceed') }}" method="POST"
                                    id="digital-checkout-form">
                                    @csrf
                                    <h5 class="fs-15 fw-semibold mb-3">
                                        <i class="tio-email-outlined me-1"></i>
                                        {{ translate('Confirm_your_delivery_email') }}
                                    </h5>
                                    <p class="text-muted fs-13 mb-3">
                                        {{ translate('Your_digital_code(s)_will_be_sent_to_this_email_address.') }}
                                    </p>
                                    <div class="form-group mb-4">
                                        <label class="fw-semibold fs-14">
                                            {{ translate('Email_address') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" class="form-control" name="digital_delivery_email"
                                            value="{{ auth('customer')->check() ? auth('customer')->user()?->email : old('digital_delivery_email') }}"
                                            placeholder="{{ translate('your@email.com') }}" required>
                                        @if (auth('customer')->check())
                                            <small class="text-muted mt-1 d-block">
                                                <i class="tio-checkmark-circle text-success"></i>
                                                {{ translate('This_is_your_account_email._You_can_change_it_if_needed.') }}
                                            </small>
                                        @endif
                                    </div>

                                </form>
                            </div>
                        </div>
                    @else
                        {{-- =========================================================
                             PHYSICAL (or MIXED) CHECKOUT — Full address forms
                             ========================================================= --}}
                        <input type="hidden" id="physical_product" name="physical_product"
                            value="{{ $physical_product_view ? 'yes' : 'no' }}">
                        <div class="px-3 px-md-0">
                            <h4 class="pb-2 mt-4 fs-18 text-capitalize">{{ translate('shipping_address') }}</h4>
                        </div>

                        @php $shippingAddresses = \App\Models\ShippingAddress::where(['customer_id' => auth('customer')->id(), 'is_guest' => 0])->get(); @endphp
                        <form method="post" class="card __card" id="address-form">
                            <div class="card-body p-0">
                                <ul class="list-group">
                                    <li class="list-group-item add-another-address">
                                        @if ($shippingAddresses->count() > 0)
                                            <div class="d-flex align-items-center justify-content-end gap-3">
                                                <div class="dropdown">
                                                    <button class="form-control dropdown-toggle text-capitalize"
                                                        type="button" id="dropdownMenuButton" data-toggle="dropdown"
                                                        aria-haspopup="true" aria-expanded="false">
                                                        {{ translate('saved_address') }}
                                                    </button>

                                                    <div class="dropdown-menu dropdown-menu-right saved-address-dropdown scroll-bar-saved-address"
                                                        aria-labelledby="dropdownMenuButton">
                                                        @foreach ($shippingAddresses as $key => $address)
                                                            <div class="dropdown-item select_shipping_address {{ $key == 0 ? 'active' : '' }}"
                                                                id="shippingAddress{{ $key }}">
                                                                <input type="hidden"
                                                                    class="selected_shippingAddress{{ $key }}"
                                                                    value="{{ $address }}">
                                                                <input type="hidden" name="shipping_method_id"
                                                                    value="{{ $address['id'] }}">
                                                                <div class="media gap-2">
                                                                    <div class="">
                                                                        <i class="tio-briefcase"></i>
                                                                    </div>
                                                                    <div class="media-body">
                                                                        <div class="mb-1 text-capitalize">
                                                                            {{ $address->address_type }}</div>
                                                                        <div
                                                                            class="text-muted fs-12 text-capitalize text-wrap">
                                                                            {{ $address->address }}</div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                        <div id="accordion">
                                            <div class="">
                                                <div class="mt-3">
                                                    <div class="row">
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('contact_person_name') }}
                                                                    <span class="text-danger">*</span>
                                                                </label>
                                                                <input type="text" class="form-control"
                                                                    name="contact_person_name"
                                                                    {{ $shippingAddresses->count() == 0 ? 'required' : '' }}
                                                                    id="name">
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('phone') }}
                                                                    <span class="text-danger">*</span>
                                                                </label>
                                                                <input type="tel" class="form-control" id="phone"
                                                                    {{ $shippingAddresses->count() == 0 ? 'required' : '' }}
                                                                    name="phone">
                                                            </div>
                                                        </div>
                                                        @if (!auth('customer')->check())
                                                            <div class="col-sm-12">
                                                                <div class="form-group">
                                                                    <label for="exampleInputEmail1">
                                                                        {{ translate('email') }}
                                                                        <span class="text-danger">*</span>
                                                                    </label>
                                                                    <input type="email" class="form-control"
                                                                        name="email" id="email"
                                                                        {{ $shippingAddresses->count() == 0 ? 'required' : '' }}>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        <div class="col-12">
                                                            <div class="form-group">
                                                                <label>{{ translate('address_type') }}</label>
                                                                <select class="form-control" name="address_type"
                                                                    id="address_type">
                                                                    <option value="permanent">{{ translate('permanent') }}
                                                                    </option>
                                                                    <option value="home">{{ translate('home') }}
                                                                    </option>
                                                                    <option value="office">{{ translate('office') }}
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="form-group">
                                                                <label>{{ translate('country') }}
                                                                    <span class="text-danger">*</span></label>
                                                                <select name="country" id="country"
                                                                    class="form-control selectpicker"
                                                                    data-live-search="true" required>
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
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('city') }}<span
                                                                        class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="city"
                                                                    id="city"
                                                                    {{ $shippingAddresses->count() == 0 ? 'required' : '' }}>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="form-group">
                                                                <label>{{ translate('zip_code') }}
                                                                    <span class="text-danger">*</span></label>
                                                                @if ($zip_restrict_status == 1)
                                                                    <select name="zip"
                                                                        class="form-control selectpicker"
                                                                        data-live-search="true" id="select2-zip-container"
                                                                        required>
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
                                                                        name="zip" id="zip"
                                                                        {{ $shippingAddresses->count() == 0 ? 'required' : '' }}>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="form-group mb-1">
                                                                <label>{{ translate('address') }}<span
                                                                        class="text-danger">*</span></label>
                                                                <textarea class="form-control" id="address" type="text" name="address"
                                                                    {{ $shippingAddresses->count() == 0 ? 'required' : '' }}></textarea>
                                                                <span
                                                                    class="fs-14 text-danger font-semi-bold opacity-0 map-address-alert">
                                                                    {{ translate('note') }}:
                                                                    {{ translate('you_need_to_select_address_from_your_selected_country') }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @if (getWebConfig('map_api_status') == 1)
                                                        <div
                                                            class="form-group location-map-canvas-area map-area-alert-border">
                                                            <input id="pac-input"
                                                                class="controls rounded __inline-46 location-search-input-field"
                                                                title="{{ translate('search_your_location_here') }}"
                                                                type="text"
                                                                placeholder="{{ translate('search_here') }}" />
                                                            <div class="__h-200px" id="location_map_canvas"></div>
                                                        </div>
                                                    @endif

                                                    <div class="d-flex gap-3 align-items-center">
                                                        <label class="form-check-label d-flex gap-2 align-items-center"
                                                            id="save_address_label">
                                                            <input type="hidden" name="shipping_method_id"
                                                                id="shipping_method_id" value="0">
                                                            @if (auth('customer')->check())
                                                                <input type="checkbox" name="save_address"
                                                                    id="save_address">
                                                                {{ translate('save_this_Address') }}
                                                            @endif
                                                        </label>
                                                    </div>

                                                    <input type="hidden" id="latitude" name="latitude"
                                                        class="form-control d-inline"
                                                        placeholder="{{ translate('ex') }} : -94.22213"
                                                        value="{{ $defaultLocation ? $defaultLocation['lat'] : 0 }}"
                                                        required readonly>
                                                    <input type="hidden" name="longitude" class="form-control"
                                                        placeholder="{{ translate('ex') }} : 103.344322" id="longitude"
                                                        value="{{ $defaultLocation ? $defaultLocation['lng'] : 0 }}"
                                                        required readonly>

                                                    <button type="submit" class="btn btn--primary d--none"
                                                        id="address_submit"></button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </form>

                        @if (!Auth::guard('customer')->check() && $web_config['guest_checkout_status'])
                            <div class="card __card mt-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                        <div
                                            class="min-h-45 form-check d-flex gap-3 align-items-center cursor-pointer user-select-none">
                                            <input type="checkbox" id="is_check_create_account"
                                                name="is_check_create_account" class="form-check-input mt-0"
                                                value="1">
                                            <label class="form-check-label font-weight-bold fs-13"
                                                for="is_check_create_account">
                                                {{ translate('Create_an_account_with_the_above_info') }}
                                            </label>
                                        </div>

                                        <div class="is_check_create_account_password_group d--none">
                                            <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction"
                                                            name="customer_password" type="password"
                                                            id="customer_password"
                                                            placeholder="{{ translate('new_Password') }}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction w-100"
                                                            name="customer_confirm_password" type="password"
                                                            id="customer_confirm_password"
                                                            placeholder="{{ translate('confirm_Password') }}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if ($billingInputByCustomer && $physical_product_view)
                        <div>
                            <div
                                class="billing-methods_label d-flex flex-wrap justify-content-between gap-2 mt-4 pb-3 px-3 px-md-0">
                                <h4 class="mb-0 fs-18 text-capitalize">{{ translate('billing_address') }}</h4>
                                @php $billingAddresses = \App\Models\ShippingAddress::where(['customer_id' => auth('customer')->id(), 'is_guest' => '0'])->get(); @endphp
                                <div class="form-check d-flex gap-3 align-items-center">
                                    <input type="checkbox" id="same_as_shipping_address" name="same_as_shipping_address"
                                        class="form-check-input action-hide-billing-address mt-0"
                                        {{ $billingInputByCustomer == 1 ? '' : 'checked' }}>
                                    <label class="form-check-label user-select-none" for="same_as_shipping_address">
                                        {{ translate('same_as_shipping_address') }}
                                    </label>
                                </div>
                            </div>

                            <form method="post" class="card __card" id="billing-address-form">
                                <div id="hide_billing_address" class="">
                                    <ul class="list-group">

                                        <li class="list-group-item action-billing-address-hide">
                                            @if ($billingAddresses->count() > 0)
                                                <div class="d-flex align-items-center justify-content-end gap-3">

                                                    <div class="dropdown">
                                                        <button class="form-control dropdown-toggle text-capitalize"
                                                            type="button" id="dropdownMenuButton" data-toggle="dropdown"
                                                            aria-haspopup="true" aria-expanded="false">
                                                            {{ translate('saved_address') }}
                                                        </button>

                                                        <div class="dropdown-menu dropdown-menu-right saved-address-dropdown scroll-bar-saved-address"
                                                            aria-labelledby="dropdownMenuButton">
                                                            @foreach ($billingAddresses as $key => $address)
                                                                <div class="dropdown-item select_billing_address {{ $key == 0 ? 'active' : '' }}"
                                                                    id="billingAddress{{ $key }}">
                                                                    <input type="hidden"
                                                                        class="selected_billingAddress{{ $key }}"
                                                                        value="{{ $address }}">
                                                                    <input type="hidden" name="billing_method_id"
                                                                        value="{{ $address['id'] }}">
                                                                    <div class="media gap-2">
                                                                        <div class="">
                                                                            <i class="tio-briefcase"></i>
                                                                        </div>
                                                                        <div class="media-body">
                                                                            <div class="mb-1 text-capitalize">
                                                                                {{ $address->address_type }}</div>
                                                                            <div
                                                                                class="text-muted fs-12 text-capitalize text-wrap">
                                                                                {{ $address->address }}</div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            <div id="accordion">
                                                <div class="">
                                                    <div class="">
                                                        <div class="row">
                                                            <div class="col-sm-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('contact_person_name') }}<span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text" class="form-control"
                                                                        name="billing_contact_person_name"
                                                                        id="billing_contact_person_name"
                                                                        {{ $billingAddresses->count() == 0 ? 'required' : '' }}>
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('phone') }}
                                                                        <span class="text-danger">*</span>
                                                                    </label>
                                                                    <input type="tel"
                                                                        class="form-control phone-input-with-country-picker-2"
                                                                        name="billing_phone" id="billing_phone"
                                                                        {{ $billingAddresses->count() == 0 ? 'required' : '' }}>
                                                                </div>
                                                            </div>
                                                            @if (!auth('customer')->check())
                                                                <div class="col-sm-12">
                                                                    <div class="form-group">
                                                                        <label
                                                                            for="exampleInputEmail1">{{ translate('email') }}
                                                                            <span class="text-danger">*</span></label>
                                                                        <input type="email" class="form-control"
                                                                            name="billing_contact_email"
                                                                            id="billing_contact_email" id
                                                                            {{ $billingAddresses->count() == 0 ? 'required' : '' }}>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            <div class="col-12">
                                                                <div class="form-group">
                                                                    <label>{{ translate('address_type') }}</label>
                                                                    <select class="form-control"
                                                                        name="billing_address_type"
                                                                        id="billing_address_type">
                                                                        <option value="permanent">
                                                                            {{ translate('permanent') }}</option>
                                                                        <option value="home">{{ translate('home') }}
                                                                        </option>
                                                                        <option value="office">{{ translate('office') }}
                                                                        </option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="form-group">
                                                                    <label>{{ translate('country') }}<span
                                                                            class="text-danger">*</span></label>
                                                                    <select name="billing_country"
                                                                        class="form-control selectpicker"
                                                                        data-live-search="true" id="billing_country">
                                                                        @foreach ($countries as $country)
                                                                            <option value="{{ $country['name'] }}">
                                                                                {{ $country['name'] }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label
                                                                        for="exampleInputEmail1">{{ translate('city') }}<span
                                                                            class="text-danger">*</span></label>
                                                                    <input type="text" class="form-control"
                                                                        id="billing_city" name="billing_city"
                                                                        {{ $billingAddresses->count() == 0 ? 'required' : '' }}>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="form-group">
                                                                    <label>{{ translate('zip_code') }}
                                                                        <span class="text-danger">*</span></label>
                                                                    @if ($zip_restrict_status)
                                                                        <select name="billing_zip"
                                                                            class="form-control selectpicker"
                                                                            data-live-search="true" id="billing_zip">
                                                                            @foreach ($zip_codes as $code)
                                                                                <option value="{{ $code->zipcode }}">
                                                                                    {{ $code->zipcode }}</option>
                                                                            @endforeach
                                                                        </select>
                                                                    @else
                                                                        <input type="text" class="form-control"
                                                                            id="billing_zip" name="billing_zip"
                                                                            {{ $billingAddresses->count() == 0 ? 'required' : '' }}>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="form-group mb-1">
                                                            <label>{{ translate('address') }}<span
                                                                    class="text-danger">*</span></label>
                                                            <textarea class="form-control" id="billing_address" type="billing_text" name="billing_address" id="billing_address"
                                                                {{ $billingAddresses->count() == 0 ? 'required' : '' }}></textarea>

                                                            <span
                                                                class="fs-14 text-danger font-semi-bold opacity-0 map-address-alert">
                                                                {{ translate('note') }}:
                                                                {{ translate('you_need_to_select_address_from_your_selected_country') }}
                                                            </span>
                                                        </div>
                                                        @if (getWebConfig('map_api_status') == 1)
                                                            <div
                                                                class="form-group map-area-alert-border location-map-billing-canvas-area">
                                                                <input id="pac-input-billing"
                                                                    class="controls rounded __inline-46 location-search-input-field"
                                                                    title="{{ translate('search_your_location_here') }}"
                                                                    type="text"
                                                                    placeholder="{{ translate('search_here') }}" />
                                                                <div class="__h-200px" id="location_map_canvas_billing">
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <input type="hidden" name="billing_method_id"
                                                            id="billing_method_id" value="0">
                                                        @if (auth('customer')->check())
                                                            <div class=" d-flex gap-3 align-items-center">
                                                                <label
                                                                    class="form-check-label d-flex gap-2 align-items-center"
                                                                    id="save-billing-address-label">
                                                                    <input type="checkbox" name="save_address_billing"
                                                                        id="save_address_billing">
                                                                    {{ translate('save_this_Address') }}
                                                                </label>
                                                            </div>
                                                        @endif

                                                        <input type="hidden" id="billing_latitude"
                                                            name="billing_latitude" class="form-control d-inline"
                                                            placeholder="{{ translate('ex') }} : -94.22213"
                                                            value="{{ $defaultLocation ? $defaultLocation['lat'] : 0 }}"
                                                            required readonly>
                                                        <input type="hidden" name="billing_longitude"
                                                            class="form-control"
                                                            placeholder="{{ translate('ex') }} : 103.344322"
                                                            id="billing_longitude"
                                                            value="{{ $defaultLocation ? $defaultLocation['lng'] : 0 }}"
                                                            required readonly>

                                                        <button type="submit" class="btn btn--primary d--none"
                                                            id="address_submit"></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </form>
                        </div>

                        @if (!Auth::guard('customer')->check() && $web_config['guest_checkout_status'] && !$physical_product_view)
                            <div class="card __card mt-3">
                                <div class="card-body">
                                    <div class="d-flex align-items-center flex-wrap justify-content-between gap-3">
                                        <div
                                            class="min-h-45 form-check d-flex gap-3 align-items-center cursor-pointer user-select-none">
                                            <input type="checkbox" id="is_check_create_account"
                                                name="is_check_create_account" class="form-check-input mt-0"
                                                value="1">
                                            <label class="form-check-label font-weight-bold fs-13"
                                                for="is_check_create_account">
                                                {{ translate('Create_an_account_with_the_above_info') }}
                                            </label>
                                        </div>

                                        <div class="is_check_create_account_password_group d--none">
                                            <div class="d-flex gap-3 flex-wrap flex-sm-nowrap">
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction"
                                                            name="customer_password" type="password"
                                                            id="customer_password"
                                                            placeholder="{{ translate('new_Password') }}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <div class="password-toggle rtl">
                                                        <input class="form-control text-align-direction"
                                                            name="customer_confirm_password" type="password"
                                                            id="customer_confirm_password"
                                                            placeholder="{{ translate('confirm_Password') }}" required>
                                                        <label class="password-toggle-btn">
                                                            <input class="custom-control-input" type="checkbox">
                                                            <i class="tio-hidden password-toggle-indicator"></i>
                                                            <span class="sr-only">{{ translate('show_password') }}</span>
                                                        </label>
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
            </section>
            @include('web-views.partials._order-summary')
        </div>
    </div>

    @if (isset($offline_payment) && $offline_payment['status'])
        <div class="modal fade" id="selectPaymentMethod" tabindex="-1" aria-labelledby="selectPaymentMethodLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered  modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('offline-payment-checkout-complete') }}" method="post"
                            class="needs-validation form-loading-button-form">
                            @csrf
                            <div class="d-flex justify-content-center mb-4">
                                <img width="52"
                                    src="{{ theme_asset(path: 'public/assets/front-end/img/select-payment-method.png') }}"
                                    alt="">
                            </div>
                            <p class="fs-14 text-center">
                                {{ translate('pay_your_bill_using_any_of_the_payment_method_below_and_input_the_required_information_in_the_form') }}
                            </p>

                            <select class="form-control mx-xl-5 max-width-661" id="pay_offline_method" name="payment_by"
                                required>
                                <option value="" disabled>{{ translate('select_Payment_Method') }}</option>
                                @foreach ($offline_payment_methods as $method)
                                    <option value="{{ $method->id }}">{{ translate('payment_Method') }} :
                                        {{ $method->method_name }}</option>
                                @endforeach
                            </select>
                            <div class="" id="payment_method_field">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (auth('customer')->check() && $wallet_status == 1)
        {{-- Hidden wallet payment form — submitted directly without modal --}}
        <form action="{{ route('checkout-complete-wallet') }}" method="post" id="wallet_payment_form" class="d-none">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ session('customer_wallet_checkout_idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
        </form>
    @endif

    <span id="message-update-this-address" data-text="{{ translate('Update_this_Address') }}"></span>
    <span id="route-customer-choose-shipping-address-other"
        data-url="{{ route('customer.choose-shipping-address-other') }}"></span>
    <span id="default-latitude-address"
        data-value="{{ $defaultLocation ? $defaultLocation['lat'] : '-33.8688' }}"></span>
    <span id="default-longitude-address"
        data-value="{{ $defaultLocation ? $defaultLocation['lng'] : '151.2195' }}"></span>
    <span id="route-action-checkout-function" data-route="checkout-details"></span>
    <span id="system-country-restrict-status" data-value="{{ $country_restrict_status }}"></span>
@endsection

@push('script')
    <script>
        "use strict";
        const deliveryRestrictedCountries = @json($countriesName);

        function deliveryRestrictedCountriesCheck(countryOrCode, elementSelector, inputElement) {
            const foundIndex = deliveryRestrictedCountries.findIndex(country => country.toLowerCase() === countryOrCode
                .toLowerCase());
            if (foundIndex !== -1) {
                $(elementSelector).removeClass('map-area-alert-danger');
                $(inputElement).parent().find('.map-address-alert').removeClass('opacity-100').addClass('opacity-0')
            } else {
                $(elementSelector).addClass('map-area-alert-danger');
                $(inputElement).val('')
                $(inputElement).parent().find('.map-address-alert').removeClass('opacity-0').addClass('opacity-100')
            }
        }

        $('#is_check_create_account').on('change', function() {
            if ($(this).is(':checked')) {
                $('.is_check_create_account_password_group').fadeIn();
            } else {
                $('.is_check_create_account_password_group').fadeOut();
            }
        });

        // Digital-only checkout: save email via AJAX, then submit payment form.
        if ($('#digital-checkout-form').length > 0) {
            $('.action-checkout-function').off('click').on('click', function() {
                var emailField = $('#digital-checkout-form [name="digital_delivery_email"]');
                if (!emailField.val() || !emailField[0].checkValidity()) {
                    emailField[0].reportValidity();
                    return;
                }
                if ($('input[type="radio"]:checked').length === 0) {
                    toastr.error('Please select a payment method to proceed.');
                    return;
                }
                $.ajaxSetup({
                    headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') }
                });
                $.post({
                    url: '{{ route("digital-checkout-proceed") }}',
                    data: $('#digital-checkout-form').serialize(),
                    beforeSend: function() { $('#loading').show(); },
                    success: function(data) {
                        if (data.success) {
                            checkoutFromPayment();
                        }
                    },
                    complete: function() { $('#loading').hide(); },
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

        // Offline payment: intercept modal form submit to save address first
        $('#selectPaymentMethod form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);

            // For digital-only carts, address is already saved by the digital checkout flow
            if ($('#digital-checkout-form').length > 0) {
                $form.off('submit')[0].submit();
                return;
            }

            var physicalProduct = '{{ ($physical_product_view ?? false) ? "yes" : "no" }}';
            var shippingData = physicalProduct === 'yes' ? $('#address-form').serialize() : null;
            var billingData = $('#billing-address-form').length ? $('#billing-address-form').serialize() : '';
            var sameBilling = $('#same_as_shipping_address').is(':checked');

            $.ajaxSetup({
                headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') }
            });
            $.post({
                url: '{{ route("customer.choose-shipping-address-other") }}',
                data: {
                    physical_product: physicalProduct,
                    shipping: shippingData,
                    billing: billingData,
                    billing_addresss_same_shipping: sameBilling,
                },
                beforeSend: function() { $('#loading').show(); },
                success: function(data) {
                    if (data.errors) {
                        for (var i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        $form.off('submit')[0].submit();
                    }
                },
                complete: function() { $('#loading').hide(); },
                error: function(xhr) {
                    toastr.error('{{ translate("please_fill_in_your_address_information_first") }}');
                }
            });
        });
    </script>

    <script src="{{ theme_asset(path: 'public/assets/front-end/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ theme_asset(path: 'public/assets/front-end/js/payment.js') }}"></script>
    <script src="{{ theme_asset(path: 'public/assets/front-end/js/shipping.js') }}"></script>

    @if (getWebConfig('map_api_status') == 1)
        <script
            src="https://maps.googleapis.com/maps/api/js?key={{ getWebConfig('map_api_key') }}&callback=mapsShopping&loading=async&libraries=places&v=3.56"
            defer></script>
    @endif
@endpush
