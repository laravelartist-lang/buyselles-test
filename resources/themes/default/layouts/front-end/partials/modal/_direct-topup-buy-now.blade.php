@if (! empty($isDirectTopUpProduct) && ! empty($directTopUpConfig))
    @php
        $directTopUpService = app(\App\Services\DirectTopUp\DirectTopUpService::class);
        $directTopUpPricing = $directTopUpPricing ?? $directTopUpService->buildPricingPayload($product);
        $accountLabel = (string) $directTopUpConfig['account_label'];
        $requiresAccountVerification = (bool) ($directTopUpConfig['requires_account_verification'] ?? false);
        $showVerifyButton = false;
        $directTopUpQuantity = (float) $directTopUpPricing['quantity'];
        $lineTotal = (float) $directTopUpPricing['line_total'];
        $directTopUpModalConfig = [
            'product_id' => $product->id,
            'account_label' => $accountLabel,
            'direct_topup_quantity' => $directTopUpQuantity,
            'unit_price' => (float) $directTopUpPricing['unit_price'],
            'line_total' => $lineTotal,
            'formatted_unit_price' => $directTopUpPricing['formatted_unit_price'],
            'formatted_line_total' => $directTopUpPricing['formatted_line_total'],
            'quantity_label' => $directTopUpPricing['quantity_label'],
            'region' => $directTopUpPricing['region'],
            'currency' => getWebConfig(name: 'currency_code') ?? 'USD',
            'currency_symbol' => getCurrencySymbol(),
            'symbol_position' => getWebConfig('currency_symbol_position'),
            'decimal_points' => (int) $directTopUpPricing['decimal_points'],
            'requires_account_verification' => $requiresAccountVerification,
            'show_verify_button' => $showVerifyButton,
            'validate_account_url' => route('cart.validate-direct-topup-account'),
            'labels' => [
                'verify' => translate('direct_topup_verify_account') ?: translate('verify') ?: 'Verify',
                'verifying' => translate('direct_topup_account_verifying') ?: 'Verifying...',
                'verified' => translate('direct_topup_account_verified') ?: 'Verified',
                'verify_first' => translate('direct_topup_verify_account_first') ?: 'Please verify your account before proceeding.',
                'invalid' => translate('direct_topup_account_invalid') ?: 'Player ID is invalid.',
            ],
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
                    data-direct-topup-quantity="{{ $directTopUpQuantity }}"
                    data-formatted-line-total="{{ $directTopUpPricing['formatted_line_total'] }}"
                    data-line-total="{{ $lineTotal }}"
                    data-requires-account-verification="{{ $requiresAccountVerification ? '1' : '0' }}">

                    <div class="d-flex align-items-start gap-3 mb-2 flex-wrap">
                        <div class="product-description-label __color-9B9B9B fs-14 text-nowrap pt-2">
                            {{ $accountLabel }} <span class="text-danger">*</span>
                        </div>
                        <div class="flex-grow-1" style="min-width: 220px; max-width: 420px;">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="text"
                                    name="direct_topup_account_id"
                                    id="direct-topup-account-input"
                                    class="form-control flex-grow-1"
                                    style="min-width: 160px;"
                                    maxlength="255"
                                    required
                                    autocomplete="off"
                                    placeholder="{{ $accountLabel }}">
                                @if ($requiresAccountVerification)
                                    <button type="button"
                                        class="btn btn-outline-primary text-nowrap @unless ($showVerifyButton) d-none @endunless"
                                        id="direct-topup-verify-btn">
                                        {{ translate('direct_topup_verify_account') ?: translate('verify') ?: 'Verify' }}
                                    </button>
                                @endif
                            </div>
                            <div class="fs-12 mt-2" id="direct-topup-verify-status" aria-live="polite"></div>
                        </div>
                    </div>

                    <input type="hidden" name="direct_topup_quantity" id="direct-topup-quantity-hidden"
                        value="{{ $directTopUpQuantity }}">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="id" value="{{ $product->id }}">
                </div>

                <div class="bg-light rounded p-3 mb-3 fs-14" id="direct-topup-package-breakdown">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">{{ $directTopUpPricing['quantity_label'] }}</span>
                        <span>{{ number_format($directTopUpQuantity, 0) }}</span>
                    </div>
                    @if (! empty($directTopUpPricing['region']))
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">{{ translate('direct_topup_region') ?: 'Region' }}</span>
                            <span>{{ $directTopUpPricing['region'] }}</span>
                        </div>
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-2">
                    <span class="text-muted fs-14">{{ translate('total_price') }}</span>
                    <strong class="fs-18 text-base" id="direct-topup-modal-total"
                        data-formatted-line-total="{{ $directTopUpPricing['formatted_line_total'] }}"
                        data-line-total="{{ $lineTotal }}">
                        {{ $directTopUpPricing['formatted_line_total'] }}
                    </strong>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('close') }}</button>
                <button type="submit"
                    class="btn btn--primary"
                    id="direct-topup-proceed-btn">
                    {{ translate('proceed_to_checkout') ?: translate('Proceed_to_Checkout') ?: 'Proceed to Checkout' }}
                </button>
            </div>
        </form>
    </script>
@endif
