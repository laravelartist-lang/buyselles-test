@php
    $config = $directTopUpConfig ?? null;
    if (! $config && isset($product)) {
        $config = app(\App\Services\DirectTopUp\DirectTopUpService::class)->buildApiPayload($product);
    }
    $directTopUpQuantity = max(1, (int) ($product->minimum_order_qty ?? 1));
@endphp

@if ($config)
    @php
        $directTopUpPricing = app(\App\Services\DirectTopUp\DirectTopUpService::class)->buildPricingPayload($product);
        $directTopUpQuantity = (float) $directTopUpPricing['quantity'];
        $accountLabel = (string) $config['account_label'];
    @endphp

    <div class="direct-topup-purchase-section mb-3"
        id="direct-topup-purchase-section"
        data-direct-topup-quantity="{{ $directTopUpQuantity }}">

        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <div class="product-description-label __color-9B9B9B fs-14 text-nowrap">
                {{ $accountLabel }} <span class="text-danger">*</span>
            </div>
            <input type="text"
                name="direct_topup_account_id"
                id="direct-topup-account-input"
                class="form-control flex-grow-1"
                style="min-width: 180px; max-width: 320px;"
                maxlength="255"
                required
                autocomplete="off"
                placeholder="{{ $accountLabel }}">
        </div>

        <input type="hidden" name="direct_topup_quantity" id="direct-topup-quantity-hidden"
            value="{{ $directTopUpQuantity }}">
        <input type="hidden" name="quantity" value="1">

        <div class="bg-light rounded p-3 fs-14">
            <div class="d-flex justify-content-between mb-1">
                <span class="text-muted">{{ $directTopUpPricing['quantity_label'] }}</span>
                <span>{{ number_format($directTopUpQuantity, 0) }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">{{ translate('direct_topup_total_price') ?: translate('total_price') }}</span>
                <strong>{{ $directTopUpPricing['formatted_line_total'] }}</strong>
            </div>
            @if (! empty($directTopUpPricing['region']))
                <div class="d-flex justify-content-between mt-1">
                    <span class="text-muted">{{ translate('direct_topup_region') ?: 'Region' }}</span>
                    <span>{{ $directTopUpPricing['region'] }}</span>
                </div>
            @endif
        </div>
    </div>
@endif
