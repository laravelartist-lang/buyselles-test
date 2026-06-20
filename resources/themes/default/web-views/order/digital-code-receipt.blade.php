<!DOCTYPE html>
<?php
$companyName = getWebConfig(name: 'company_name') ?? 'Buyselles';
$companyPhone = getWebConfig(name: 'company_phone') ?? '';
$direction = session('direction', 'ltr');
?>
<html lang="{{ app()->getLocale() }}" dir="{{ $direction }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ translate('Digital_Code_Receipt') }} #{{ $orderId }}</title>
    <style>
        /* ── Screen styles ─────────────────────────────────────── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            background: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            color: #000;
        }

        .receipt-wrapper {
            background: #fff;
            width: 80mm;
            padding: 8mm;
            border: 1px solid #ccc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
        }

        .screen-actions {
            width: 80mm;
            margin-bottom: 10px;
            display: flex;
            gap: 8px;
        }

        .screen-actions button {
            flex: 1;
            padding: 8px;
            background: #063c93;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
        }

        .screen-actions button.btn-close-tab {
            background: #6c757d;
        }

        /* ── Receipt layout ─────────────────────────────────────── */
        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }

        .receipt-header .shop-name {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .receipt-header .shop-phone {
            font-size: 8pt;
        }

        .receipt-meta {
            font-size: 8pt;
            margin-bottom: 6px;
        }

        .receipt-meta table {
            width: 100%;
        }

        .receipt-meta td:last-child {
            text-align: right;
        }

        .receipt-divider {
            border: none;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        .receipt-title {
            text-align: center;
            font-size: 9pt;
            font-weight: bold;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .code-item {
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dotted #aaa;
        }

        .code-item:last-child {
            border-bottom: none;
        }

        .code-product {
            font-size: 8pt;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .code-value {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 3px;
            word-break: break-all;
            margin: 3px 0;
        }

        .code-meta {
            font-size: 7pt;
            color: #555;
        }

        .receipt-footer {
            border-top: 1px dashed #000;
            padding-top: 6px;
            text-align: center;
            font-size: 7pt;
            margin-top: 6px;
        }

        /* ── Print overrides ─────────────────────────────────────── */
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body {
                background: none;
                padding: 0;
            }

            .screen-actions {
                display: none !important;
            }

            .receipt-wrapper {
                width: 100%;
                border: none;
                box-shadow: none;
                padding: 4mm 6mm;
            }
        }
    </style>
</head>

<body>

    {{-- On-screen actions (hidden at print time) --}}
    <div class="screen-actions">
        <button type="button" onclick="window.print()">🖨 {{ translate('Print') }}</button>
        <button type="button" class="btn-close-tab" onclick="window.close()">✕ {{ translate('Close') }}</button>
    </div>
    <p class="screen-actions" style="width:80mm;font-size:11px;color:#666;text-align:center;margin:-6px 0 10px;">
        {{ translate('thermal_preview_hint') ?: 'Select your thermal printer in the print dialog. Paper size: 80mm or narrowest available.' }}
    </p>

    <div class="receipt-wrapper" id="receipt-content">

        {{-- Header --}}
        <div class="receipt-header">
            <div class="shop-name">{{ strtoupper($companyName) }}</div>
            @if ($companyPhone)
                <div class="shop-phone">{{ $companyPhone }}</div>
            @endif
        </div>

        {{-- Order meta --}}
        <div class="receipt-meta">
            <table>
                <tr>
                    <td>{{ translate('Order') }} #</td>
                    <td>{{ $orderId }}</td>
                </tr>
                <tr>
                    <td>{{ translate('Date') }}</td>
                    <td>{{ $orderDate }}</td>
                </tr>
                <tr>
                    <td>{{ translate('Customer') }}</td>
                    <td>{{ $customerName }}</td>
                </tr>
            </table>
        </div>

        <hr class="receipt-divider">

        <div class="receipt-title">{{ translate('Digital_Product_Codes') }}</div>

        @forelse ($codes as $item)
            <div class="code-item">
                <div class="code-product">{{ $item['productName'] }}</div>
                <div class="code-value">{{ $item['code'] }}</div>
                <div class="code-meta">
                    @if (!empty($item['pin']))
                        <strong>{{ translate('PIN') }}:</strong> {{ $item['pin'] }}
                    @endif
                    @if (!empty($item['serial']))
                        @if (!empty($item['pin'])) &nbsp; @endif
                        {{ translate('S/N') }}: {{ $item['serial'] }}
                    @endif
                    @if (!empty($item['expiry']))
                        &nbsp; {{ translate('Exp') }}: {{ $item['expiry'] }}
                    @endif
                </div>
            </div>
        @empty
            <p style="font-size:8pt; text-align:center;">{{ translate('No_codes_found_for_this_order.') }}</p>
        @endforelse

        <hr class="receipt-divider">

        {{-- Footer --}}
        <div class="receipt-footer">
            {{ translate('Thank_you_for_your_purchase!') }}<br>
            {{ translate('Keep_this_receipt_for_your_records.') }}<br>
            {{ translate('Do_not_share_your_codes_with_anyone.') }}
        </div>

    </div>

    <script>
        // Open print dialog only when explicitly requested (?print=1)
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.onload = function () {
                window.print();
            };
        }
    </script>

</body>

</html>
