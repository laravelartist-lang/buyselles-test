<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SecretOrca Catalog Demo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #1f2937; }
        .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
        .ok { color: #059669; font-weight: bold; }
        .bad { color: #dc2626; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px; text-align: right; }
        th { background: #f9fafb; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; background: #ecfdf5; color: #047857; }
        .error { background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>SecretOrca — تجربة الكتالوج المحلية</h1>

    <div class="card">
        <h2>حالة الاتصال</h2>
        @if ($error)
            <div class="error">{{ $error }}</div>
        @else
            <p>Health: <span class="{{ $connected ? 'ok' : 'bad' }}">{{ strtoupper($health_status) }}</span></p>
            <p>{{ $health_message }} @if($latency_ms) ({{ $latency_ms }}ms) @endif</p>
            <p>Pages fetched: <strong>{{ $pages_fetched }}</strong></p>
            <p>Total products: <span class="badge">{{ $total_count }}</span></p>
        @endif
    </div>

    @if (! $error && $total_count > 0)
        <div class="card">
            <h2>المنتجات</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>الفئة</th>
                        <th>Code</th>
                        <th>السعر</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $index => $product)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $product['name'] }}</td>
                            <td>{{ $product['category'] ?? '—' }}</td>
                            <td>{{ $product['code'] ?? '—' }}</td>
                            <td>{{ number_format($product['price'], 10) }} {{ $product['currency'] }}</td>
                            <td>{{ $product['stock'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
