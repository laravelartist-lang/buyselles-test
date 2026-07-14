<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ translate('reseller_api_documentation') }} v1</title>
    <style>
        @page { margin: 24px 28px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.45;
        }
        h1 { font-size: 20px; margin: 0 0 6px; color: #1455ac; }
        h2 { font-size: 14px; margin: 22px 0 8px; color: #1455ac; page-break-after: avoid; }
        h3 { font-size: 12px; margin: 14px 0 6px; page-break-after: avoid; }
        p { margin: 0 0 8px; }
        .meta { color: #666; font-size: 10px; margin-bottom: 16px; }
        .version-badge {
            display: inline-block;
            background: #1455ac;
            color: #fff;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 3px;
            vertical-align: middle;
        }
        .docs-section {
            border-left: 3px solid #1455ac;
            padding-left: 12px;
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        .info-box, .warning-box {
            background: #f5f8ff;
            border: 1px solid #c9d8ff;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .warning-box { background: #fff8e6; border-color: #ffd97d; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 5px 6px;
            vertical-align: top;
        }
        th { background: #f0f0f0; text-align: left; }
        code, .api-endpoint { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
        pre.code-block {
            background: #f4f4f4;
            border: 1px solid #ddd;
            padding: 8px;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 9px;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0 0 10px;
        }
        .endpoint-card {
            border: 1px solid #ddd;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .endpoint-header {
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
            padding: 8px 10px;
            font-weight: bold;
        }
        .endpoint-body { padding: 10px; }
        .method-get { color: #04bb7b; font-weight: bold; }
        .method-post { color: #1455ac; font-weight: bold; }
        .toc-item { display: inline-block; margin-right: 12px; margin-bottom: 4px; }
        .muted { color: #666; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>{{ translate('reseller_api_documentation') }} <span class="version-badge">v1</span></h1>
    <p>{{ translate('partner_api_docs_description') }}</p>
    <div class="meta">
        {{ translate('base_url') }}: {{ $partnerApiBaseUrl }} |
        {{ translate('generated_on') ?: 'Generated on' }}: {{ $generatedAt }}
    </div>

    @include('partner-api.partials._docs-content', [
        'apiExamples' => app(\App\Services\Partner\PartnerApiDocumentationExamplesService::class)->build(),
        'apiExampleFormatter' => app(\App\Services\Partner\PartnerApiDocumentationExamplesService::class),
    ])
</body>
</html>
