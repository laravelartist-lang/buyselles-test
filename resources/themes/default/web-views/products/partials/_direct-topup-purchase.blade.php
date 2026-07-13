@php
    $config = $directTopUpConfig ?? null;
    if (! $config && isset($product)) {
        $config = app(\App\Services\DirectTopUp\DirectTopUpService::class)->buildApiPayload($product);
    }
    $directTopUpQuantity = max(1, (int) ($product->minimum_order_qty ?? 1));
@endphp

@if ($config)
    @php
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
    </div>
@endif
