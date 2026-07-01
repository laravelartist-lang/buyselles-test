@php
    $dtService = app(\App\Services\DirectTopUp\DirectTopUpService::class);
    $minQty = (float) $product->direct_topup_min_quantity;
    $maxQty = (float) $product->direct_topup_max_quantity;
    $perUnit = $dtService->getPricePerUnit($product);
    $accountLabel = $product->direct_topup_account_label ?: translate('direct_topup_account_label_placeholder');
@endphp

<div class="direct-topup-purchase-section mb-3"
    id="direct-topup-purchase-section"
    data-min-quantity="{{ $minQty }}"
    data-max-quantity="{{ $maxQty }}"
    data-price-per-unit="{{ $perUnit }}"
    data-currency="{{ getWebConfig(name: 'currency_code') ?? 'USD' }}">

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

@push('script')
<script>
(function () {
    'use strict';

    $(function () {
        var $section = $('#direct-topup-purchase-section');
        if (!$section.length) return;

        var minQty = parseFloat($section.data('min-quantity')) || 0;
        var maxQty = parseFloat($section.data('max-quantity')) || 0;
        var perUnit = parseFloat($section.data('price-per-unit')) || 0;

        var $qtyPane = $('#direct-topup-qty-pane');
        var $pricePane = $('#direct-topup-price-pane');
        var $qtyInput = $('#direct-topup-quantity-input');
        var $priceInput = $('#direct-topup-price-input');
        var $hiddenQty = $('#direct-topup-quantity-hidden');
        var $qtyFromPr = $('#direct-topup-qty-from-price');
        var $actualT = $('#direct-topup-actual-total');
        var $actualRow = $('#direct-topup-actual-row');
        var $priceDisp = $('.product-details-chosen-price-amount');

        var currencySymbol = @json(getCurrencySymbol());
        var symbolPosition = @json(getWebConfig('currency_symbol_position'));
        var decimalPoints = parseInt(@json(getWebConfig('decimal_point_settings')), 10) || 2;

        var currentQty = minQty;
        var syncing = false;

        function formatPrice(value) {
            var num = Number(value).toFixed(decimalPoints);
            return symbolPosition === 'left' ? currencySymbol + num : num + currencySymbol;
        }

        function totalFor(q) {
            return Math.round(q * perUnit * 100) / 100;
        }

        function clampQ(q) {
            if (q < minQty) {
                return minQty;
            }
            if (q > maxQty) {
                return maxQty;
            }
            return Math.round(q);
        }

        function refresh() {
            var q = clampQ(currentQty);
            var t = totalFor(q);
            $hiddenQty.val(q);
            $qtyFromPr.text(q);
            if ($priceDisp.length) {
                $priceDisp.text(formatPrice(t));
            }
        }

        function switchToQty() {
            if (syncing) {
                return;
            }
            syncing = true;
            $qtyPane.removeClass('d-none');
            $pricePane.addClass('d-none');
            $qtyInput.val(Math.floor(clampQ(currentQty)));
            refresh();
            $actualRow.addClass('d-none');
            syncing = false;
        }

        function switchToPrice() {
            if (syncing) {
                return;
            }
            syncing = true;
            $pricePane.removeClass('d-none');
            $qtyPane.addClass('d-none');
            $priceInput.val(totalFor(clampQ(currentQty)).toFixed(decimalPoints));
            refresh();
            $actualRow.addClass('d-none');
            syncing = false;
        }

        $qtyInput.on('input', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            var v = parseInt(String(this.value).replace(/[^0-9]/g, ''), 10);
            if (isNaN(v) || v < 1) {
                v = minQty;
            }
            currentQty = clampQ(v);
            this.value = Math.floor(currentQty);
            refresh();
            $actualRow.addClass('d-none');
            syncing = false;
        });

        $qtyInput.on('change blur', function () {
            refresh();
        });

        $priceInput.on('input', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            var v = parseFloat(String(this.value).replace(/[^0-9.]/g, ''));
            if (isNaN(v) || v <= 0) {
                v = totalFor(minQty);
            }
            var lo = totalFor(minQty);
            var hi = totalFor(maxQty);
            if (v > hi) {
                v = hi;
            }
            if (v < lo) {
                v = lo;
            }

            currentQty = perUnit > 0 ? clampQ(v / perUnit) : minQty;
            refresh();

            var a = totalFor(clampQ(currentQty));
            if (Math.abs(a - v) > 0.001) {
                $actualT.text(formatPrice(a));
                $actualRow.removeClass('d-none');
            } else {
                $actualRow.addClass('d-none');
            }
            syncing = false;
        });

        $(document).on('change', 'input[name="direct_topup_mode"]', function () {
            if ($(this).val() === 'price') {
                switchToPrice();
            } else {
                switchToQty();
            }
        });

        currentQty = minQty;
        refresh();
    });
})();
</script>
@endpush
