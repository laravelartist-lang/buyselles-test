<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ translate('Digital_Code_Receipt') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #063c93; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 22px; color: #063c93; }
        .meta { margin-bottom: 16px; }
        .meta table { width: 100%; }
        .meta td { padding: 3px 0; }
        .meta td:last-child { text-align: right; }
        .code-block { border: 1px solid #ddd; border-radius: 6px; padding: 12px; margin-bottom: 12px; page-break-inside: avoid; }
        .product { font-weight: bold; margin-bottom: 6px; }
        .code { font-size: 16px; font-weight: bold; letter-spacing: 2px; word-break: break-all; margin: 6px 0; }
        .meta-line { color: #555; font-size: 11px; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #666; border-top: 1px dashed #999; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ strtoupper(getWebConfig(name: 'company_name') ?? 'Buyselles') }}</h1>
        <div>{{ translate('Digital_Product_Codes') }}</div>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td>{{ translate('Order') }} #</td>
                <td>{{ $orderId }}</td>
            </tr>
            <tr>
                <td>{{ translate('Date') }}</td>
                <td>{{ $orderDate }}</td>
            </tr>
            @if (!empty($customerName))
                <tr>
                    <td>{{ translate('Customer') }}</td>
                    <td>{{ $customerName }}</td>
                </tr>
            @endif
        </table>
    </div>

    @foreach ($codes as $item)
        <div class="code-block">
            <div class="product">{{ $item['productName'] }}</div>
            <div class="code">{{ $item['code'] }}</div>
            <div class="meta-line">
                @if (!empty($item['pin']))
                    <strong>{{ translate('PIN') }}:</strong> {{ $item['pin'] }}
                @endif
                @if (!empty($item['serial']))
                    &nbsp; <strong>{{ translate('S/N') }}:</strong> {{ $item['serial'] }}
                @endif
                @if (!empty($item['expiry']))
                    &nbsp; <strong>{{ translate('Exp') }}:</strong> {{ $item['expiry'] }}
                @endif
            </div>
        </div>
    @endforeach

    <div class="footer">
        {{ translate('Thank_you_for_your_purchase!') }}<br>
        {{ translate('Keep_this_receipt_for_your_records.') }}<br>
        {{ translate('Do_not_share_your_codes_with_anyone.') }}
    </div>
</body>
</html>
