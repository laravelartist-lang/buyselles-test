<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <h3 class="mb-1">
            {{ translate('reseller_api_documentation') }}
            <span class="version-badge">v1</span>
        </h3>
        <p class="text-muted mb-0">{{ translate('partner_api_docs_description') }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('partner-api.docs.postman') }}" class="btn btn-primary btn-sm">
            {{ translate('download_postman_collection') ?: 'Download Postman Collection' }}
        </a>
        @if (! empty($showAdminBack))
            <a href="{{ route('admin.reseller-keys.list') }}" class="btn btn-outline-secondary btn-sm">
                {{ translate('back_to_keys') }}
            </a>
        @endif
    </div>
</div>
