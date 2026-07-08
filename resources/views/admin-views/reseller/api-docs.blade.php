@extends('layouts.admin.app')

@section('title', translate('reseller_api_documentation'))

@push('css_or_js')
<style>
    .api-endpoint { font-family: monospace; }
    .method-badge-get  { background: #04bb7b; }
    .method-badge-post { background: #1455ac; }
    .docs-section {
        border-left: 4px solid var(--bs-primary, #1455ac);
        padding-left: 16px;
        margin-bottom: 36px;
    }
    pre.code-block {
        background: #1e1e2e;
        color: #cdd6f4;
        border-radius: 8px;
        padding: 16px;
        font-size: 13px;
        overflow-x: auto;
        white-space: pre;
    }
    .endpoint-card { border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 16px; }
    .endpoint-header { background: #f8f9fa; padding: 12px 16px; border-radius: 8px 8px 0 0; border-bottom: 1px solid #e9ecef; }
    .endpoint-body { padding: 16px; }
    .info-box { background: #f0f4ff; border: 1px solid #c9d8ff; border-radius: 8px; padding: 14px 16px; }
    .warning-box { background: #fff8e6; border: 1px solid #ffd97d; border-radius: 8px; padding: 14px 16px; }
    .toc-link { text-decoration: none; color: var(--bs-primary); font-size: 14px; }
    .toc-link:hover { text-decoration: underline; }
    .version-badge { font-size: 11px; background: #1455ac; color: #fff; border-radius: 4px; padding: 2px 7px; vertical-align: middle; }
</style>
@endpush

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-body">

            {{-- Page header --}}
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
                <div>
                    <h3 class="mb-1">
                        {{ translate('reseller_api_documentation') }}
                        <span class="version-badge">v1</span>
                    </h3>
                    <p class="text-muted mb-0">{{ translate('partner_api_docs_description') }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.reseller-keys.api-docs.download') }}" class="btn btn-primary btn-sm">
                        <i class="fi fi-rr-download me-1"></i>{{ translate('download_partner_api_documentation_pdf') ?: 'Download PDF' }}
                    </a>
                    <a href="{{ route('admin.reseller-keys.list') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fi fi-rr-arrow-left me-1"></i>{{ translate('back_to_keys') }}
                    </a>
                </div>
            </div>

            @include('admin-views.reseller.partials._api-docs-content')

        </div>
    </div>
</div>
@endsection
