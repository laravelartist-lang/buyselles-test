@php
    $config = $directTopUpConfig ?? null;
    if (! $config && isset($product)) {
        $config = app(\App\Services\DirectTopUp\DirectTopUpService::class)->buildApiPayload($product);
    }
@endphp

@if ($config)
    @php
        $minQty = (float) $config['min_quantity'];
        $maxQty = (float) $config['max_quantity'];
        $perUnit = (float) $config['price_per_unit'];
        $accountLabel = (string) $config['account_label'];
    @endphp

    <div class="direct-topup-purchase-section mb-3"
        id="direct-topup-purchase-section"
        data-min-quantity="{{ $minQty }}"
        data-max-quantity="{{ $maxQty }}"
        data-price-per-unit="{{ $perUnit }}"
        data-currency="{{ $config['currency'] ?? (getWebConfig(name: 'currency_code') ?? 'USD') }}">

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

        <div class="d-flex align-items-start gap-3 mb-3 flex-wrap">
            <div class="product-description-label __color-9B9B9B fs-14 text-nowrap pt-1">
                {{ translate('direct_topup_purchase_mode') ?: translate('purchase_type') ?: 'Purchase by' }}
            </div>
            <div class="list-inline checkbox-alphanumeric checkbox-alphanumeric--style-1 mb-0 flex-start ps-0">
                <div class="user-select-none">
                    <div class="for-mobile-capacity">
                        <input type="radio" hidden
                            id="direct-topup-qty-tab"
                            name="direct_topup_mode"
                            value="quantity"
                            checked>
                        <label for="direct-topup-qty-tab" class="__text-12px">
                            <span class="text-nowrap">{{ translate('direct_topup_by_quantity') ?: 'By Quantity' }}</span>
                        </label>
                    </div>
                </div>
                <div class="user-select-none">
                    <div class="for-mobile-capacity">
                        <input type="radio" hidden
                            id="direct-topup-price-tab"
                            name="direct_topup_mode"
                            value="price">
                        <label for="direct-topup-price-tab" class="__text-12px">
                            <span class="text-nowrap">{{ translate('direct_topup_by_price') ?: 'By Price' }}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="direct-topup-pane mb-2" id="direct-topup-qty-pane">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="product-description-label __color-9B9B9B fs-14 text-nowrap">
                    {{ translate('quantity') }}
                </div>
                <input type="number"
                    id="direct-topup-quantity-input"
                    class="form-control w-130px text-center"
                    step="1"
                    min="{{ floor($minQty) }}"
                    max="{{ ceil($maxQty) }}"
                    value="{{ floor($minQty) }}">
                <span class="text-muted fs-12">
                    ({{ floor($minQty) }} — {{ ceil($maxQty) }})
                </span>
            </div>
        </div>

        <div class="direct-topup-pane mb-2 d-none" id="direct-topup-price-pane">
            <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                <div class="product-description-label __color-9B9B9B fs-14 text-nowrap">
                    {{ translate('amount') }}
                </div>
                <input type="number"
                    id="direct-topup-price-input"
                    class="form-control w-180px text-center"
                    step="0.01"
                    min="{{ round($minQty * $perUnit, 2) }}"
                    max="{{ round($maxQty * $perUnit, 2) }}"
                    placeholder="{{ round($minQty * $perUnit, 2) }} - {{ round($maxQty * $perUnit, 2) }}">
                <span class="text-muted fs-12">
                    ({{ webCurrencyConverter(amount: round($minQty * $perUnit, 2)) }}
                    —
                    {{ webCurrencyConverter(amount: round($maxQty * $perUnit, 2)) }})
                </span>
            </div>
            <div class="text-muted fs-14 ps-0">
                {{ translate('direct_topup_calculated_quantity') ?: 'You will receive' }}:
                <strong id="direct-topup-qty-from-price">{{ floor($minQty) }}</strong>
                {{ translate('credits') ?: 'credits' }}
            </div>
            <div class="text-muted fs-14 mt-1 d-none" id="direct-topup-actual-row">
                {{ translate('actual_total') ?: 'Actual charge' }}:
                <strong id="direct-topup-actual-total"></strong>
            </div>
        </div>

        <input type="hidden" name="direct_topup_quantity" id="direct-topup-quantity-hidden"
            value="{{ floor($minQty) }}">
        <input type="hidden" name="quantity" value="1">
    </div>
@endif
