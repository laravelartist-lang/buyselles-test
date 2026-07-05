@if (! empty($isDirectTopUpProduct) && ! empty($directTopUpConfig))
    @php
        $minQty = (float) $directTopUpConfig['min_quantity'];
        $maxQty = (float) $directTopUpConfig['max_quantity'];
        $perUnit = (float) $directTopUpConfig['price_per_unit'];
        $accountLabel = (string) $directTopUpConfig['account_label'];
        $directTopUpModalConfig = [
            'product_id' => $product->id,
            'account_label' => $accountLabel,
            'min_quantity' => $minQty,
            'max_quantity' => $maxQty,
            'price_per_unit' => $perUnit,
            'currency' => $directTopUpConfig['currency'] ?? (getWebConfig(name: 'currency_code') ?? 'USD'),
            'currency_symbol' => getCurrencySymbol(),
            'symbol_position' => getWebConfig('currency_symbol_position'),
            'decimal_points' => (int) (getWebConfig('decimal_point_settings') ?? 2),
        ];
    @endphp

    <script type="application/json" id="direct-topup-config-json">
        @json($directTopUpModalConfig)
    </script>

    <script type="text/template" id="direct-topup-buy-now-modal-template">
        <div class="modal-header border-0 pb-0">
            <h5 class="modal-title">{{ translate('buy_now') }}</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('close') }}">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <form id="direct-topup-buy-now-form" class="direct-topup-buy-now-form">
            <div class="modal-body pt-2">
                <div class="direct-topup-purchase-section mb-3"
                    id="direct-topup-purchase-section"
                    data-min-quantity="{{ $minQty }}"
                    data-max-quantity="{{ $maxQty }}"
                    data-price-per-unit="{{ $perUnit }}"
                    data-currency="{{ $directTopUpConfig['currency'] ?? (getWebConfig(name: 'currency_code') ?? 'USD') }}">

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
                                        id="modal-direct-topup-qty-tab"
                                        name="direct_topup_mode"
                                        value="quantity"
                                        checked>
                                    <label for="modal-direct-topup-qty-tab" class="__text-12px">
                                        <span class="text-nowrap">{{ translate('direct_topup_by_quantity') ?: 'By Quantity' }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="user-select-none">
                                <div class="for-mobile-capacity">
                                    <input type="radio" hidden
                                        id="modal-direct-topup-price-tab"
                                        name="direct_topup_mode"
                                        value="price">
                                    <label for="modal-direct-topup-price-tab" class="__text-12px">
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
                    <input type="hidden" name="id" value="{{ $product->id }}">
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-2">
                    <span class="text-muted fs-14">{{ translate('total_price') }}</span>
                    <strong class="fs-18 text-base" id="direct-topup-modal-total">
                        {{ webCurrencyConverter(amount: round($minQty * $perUnit, 2)) }}
                    </strong>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('close') }}</button>
                <button type="submit" class="btn btn--primary" id="direct-topup-proceed-btn">
                    {{ translate('proceed_to_checkout') ?: translate('Proceed_to_Checkout') ?: 'Proceed to Checkout' }}
                </button>
            </div>
        </form>
    </script>
@endif
