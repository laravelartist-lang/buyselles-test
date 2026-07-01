<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $data['subject'] ?? translate('direct_topup_order_completed') }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <p>{{ translate('hello') }} {{ $data['customerName'] ?? '' }},</p>
    <p>{{ translate('direct_topup_order_completed') }}</p>
    <ul>
        <li><strong>{{ translate('order') }} #:</strong> {{ $data['orderId'] ?? '' }}</li>
        <li><strong>{{ translate('product') }}:</strong> {{ $data['productName'] ?? '' }}</li>
        <li><strong>{{ translate('quantity') }}:</strong> {{ $data['quantity'] ?? '' }}</li>
    </ul>
    <p>{{ translate('thank_you_for_your_purchase') ?? 'Thank you for your purchase.' }}</p>
</body>
</html>
