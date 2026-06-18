<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <title>{{ translate('Digital_Code_Receipt') }}</title>
</head>
<body>
    <h2 style="text-align:center;">{{ getWebConfig(name: 'company_name') ?? 'Buyselles' }}</h2>
    <h3 style="text-align:center;">{{ translate('Digital_Product_Codes') }}</h3>
    <p>
        <strong>{{ translate('Order') }} #:</strong> {{ $orderId }}<br>
        <strong>{{ translate('Date') }}:</strong> {{ $orderDate }}<br>
        @if (!empty($customerName))
            <strong>{{ translate('Customer') }}:</strong> {{ $customerName }}<br>
        @endif
    </p>
    <hr>
    @foreach ($codes as $item)
        <p>
            <strong>{{ $item['productName'] }}</strong><br>
            <strong>{{ translate('Code') }}:</strong> {{ $item['code'] }}<br>
            @if (!empty($item['pin']))
                <strong>{{ translate('PIN') }}:</strong> {{ $item['pin'] }}<br>
            @endif
            @if (!empty($item['serial']))
                <strong>{{ translate('S/N') }}:</strong> {{ $item['serial'] }}<br>
            @endif
            @if (!empty($item['expiry']))
                <strong>{{ translate('Exp') }}:</strong> {{ $item['expiry'] }}<br>
            @endif
        </p>
        <hr>
    @endforeach
    <p style="text-align:center;font-size:11px;">
        {{ translate('Thank_you_for_your_purchase!') }}<br>
        {{ translate('Do_not_share_your_codes_with_anyone.') }}
    </p>
</body>
</html>
