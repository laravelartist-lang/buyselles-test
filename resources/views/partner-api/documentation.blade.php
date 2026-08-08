<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>{{ translate('reseller_api_documentation') }} — {{ getWebConfig(name: 'company_name') ?? config('app.name') }}</title>
    <link rel="shortcut icon" href="{{ getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type: 'backend-logo') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/bootstrap.min.css') }}">
    @include('partner-api.partials._docs-styles')
</head>
<body class="bg-light">
    <div class="container py-4 py-md-5 partner-api-docs-page">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                @include('partner-api.partials._docs-header', ['showAdminBack' => false])
                @include('partner-api.partials._docs-content')
            </div>
        </div>
    </div>
</body>
</html>
