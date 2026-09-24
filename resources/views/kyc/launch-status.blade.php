<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ translate('kyc_verification') }}</title>
    <style>
        html, body {
            margin: 0;
            height: 100%;
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 24px;
            text-align: center;
            color: #374151;
            font-size: 16px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="state">{{ $message }}</div>

    <script>
        (function () {
            var target = 'buyselles-kyc://complete?status=' + encodeURIComponent(@json($status));
            window.location.href = target;
        })();
    </script>
</body>
</html>
