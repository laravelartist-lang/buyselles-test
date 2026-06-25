@php
    $codes = $codes ?? [];
    $orderIds = $orderIds ?? [];
    $codesContainerId = $codesContainerId ?? 'purchase-modal-codes';
    $codeIdPrefix = $codeIdPrefix ?? 'purchase-modal-code';
    $copyBtnClass = $copyBtnClass ?? 'purchase-modal-copy-btn';
    $showAlert = $showAlert ?? true;
    $alertClass = $alertClass ?? 'alert alert-warning py-2 px-3 fs-13 mb-3';
@endphp

@if ($showAlert)
    <div class="{{ $alertClass }}" id="{{ $alertId ?? '' }}">
        <i class="fa fa-exclamation-triangle me-1"></i>
        <strong>{{ translate('Important') }}:</strong>
        {{ translate('Copy_or_print_your_codes_below._They_are_also_sent_to_your_email.') ?: translate('Copy or print your codes below. They are also sent to your email.') }}
    </div>
@endif

@include('web-views.partials._digital-code-delivery-actions', [
    'orderIds' => $orderIds,
    'viewTarget' => $codesContainerId,
    'codesContainer' => $codesContainerId,
    'layout' => 'cards',
    'context' => 'modal',
])

<hr class="my-3">

<div id="{{ $codesContainerId }}">
    @foreach ($codes as $idx => $item)
        <div class="border rounded p-3 mb-3 bg-light digital-code-item">
            <p class="text-muted mb-1 fw-semibold digital-code-item__label" style="font-size:0.8rem;">
                <i class="fa fa-box me-1"></i>
                {{ $item['productName'] ?? translate('Digital_Product') }}
                @if (!empty($item['orderId']))
                    &mdash; <span class="text-secondary">{{ translate('Order') }} #{{ $item['orderId'] }}</span>
                @endif
            </p>
            <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                <code class="fs-5 fw-bold text-dark bg-white px-3 py-2 rounded border flex-grow-1 text-center"
                    id="{{ $codeIdPrefix }}-{{ $idx }}"
                    style="letter-spacing:4px;font-family:'Courier New',monospace;word-break:break-all;">
                    {{ $item['code'] }}
                </code>
                <button type="button"
                    class="btn btn-sm btn-outline-primary {{ $copyBtnClass }}"
                    data-target="{{ $codeIdPrefix }}-{{ $idx }}"
                    title="{{ translate('Copy_Code') }}">
                    <i class="fa fa-copy"></i> {{ translate('Copy') }}
                </button>
            </div>
            @if (!empty($item['pin']) || !empty($item['serial']) || !empty($item['expiry']))
                <p class="text-muted mb-0 mt-1 digital-code-item__meta" style="font-size:0.76rem;">
                    @if (!empty($item['pin']))
                        <strong>{{ translate('PIN') }}:</strong> <code class="text-dark fw-semibold">{{ $item['pin'] }}</code>
                    @endif
                    @if (!empty($item['serial']))
                        &nbsp;&nbsp;<strong>{{ translate('S/N') }}:</strong> {{ $item['serial'] }}
                    @endif
                    @if (!empty($item['expiry']))
                        &nbsp;&nbsp;<strong>{{ translate('Exp') }}:</strong> {{ $item['expiry'] }}
                    @endif
                </p>
            @endif
        </div>
    @endforeach

</div>
