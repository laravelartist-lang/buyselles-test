@extends('layouts.admin.app')

@section('title', translate('sumsub_settings'))

@section('content')
<div class="content container-fluid">
    <div class="mb-3">
        <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
            <i class="fi fi-rr-shield-check"></i>
            {{ translate('sumsub_settings') }}
        </h2>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('kyc_verification_status') }}</h5>
                </div>
                <div class="card-body">
                    @if (! $sumsubConfigured)
                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
                            <i class="fi fi-rr-triangle-warning mt-1"></i>
                            <div>{{ translate('kyc_requires_sumsub_credentials_before_it_can_be_enabled') }}</div>
                        </div>
                    @endif

                    <form action="{{ route('admin.kyc.settings.update') }}" method="POST">
                        @csrf

                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0">{{ translate('enable_kyc_verification') }}</label>
                                <label class="switcher">
                                    <input type="checkbox" name="kyc_verification_status" value="1"
                                           class="switcher_input" {{ $kycEnabled ? 'checked' : '' }}
                                           {{ $sumsubConfigured ? '' : 'disabled' }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                            @if (! $sumsubConfigured)
                                <small class="text-muted d-block mt-1">
                                    {{ translate('kyc_toggle_is_disabled_until_sumsub_is_configured') }}
                                </small>
                            @endif
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="kyc_customer_purchase_threshold">
                                {{ translate('kyc_customer_purchase_threshold') }}
                            </label>
                            <input type="number" min="0" step="0.01" class="form-control"
                                   id="kyc_customer_purchase_threshold"
                                   name="kyc_customer_purchase_threshold"
                                   value="{{ $threshold }}" required>
                            <small class="text-muted">
                                {{ translate('kyc_customer_purchase_threshold_hint') }}
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ translate('update') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('credentials') }}</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span>{{ translate('sumsub_credentials') }}</span>
                        <span class="badge {{ $sumsubConfigured ? 'bg-success' : 'bg-danger' }}">
                            {{ $sumsubConfigured ? translate('configured') : translate('not_configured') }}
                        </span>
                    </div>

                    <p class="text-muted fs-12 mb-3">
                        {{ translate('sumsub_credentials_are_configured_in_the_env_file') }}
                    </p>

                    <ul class="list-unstyled mb-3 fs-13">
                        <li><code>SUMSUB_APP_TOKEN</code></li>
                        <li><code>SUMSUB_SECRET_KEY</code></li>
                        <li><code>SUMSUB_WEBHOOK_SECRET</code></li>
                        <li><code>SUMSUB_ENABLED</code></li>
                    </ul>

                    <div class="mb-3">
                        <label class="form-label mb-1">{{ translate('customer_level') }}</label>
                        <input type="text" class="form-control form-control-sm" value="{{ $customerLevel }}" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">{{ translate('vendor_level') }}</label>
                        <input type="text" class="form-control form-control-sm" value="{{ $vendorLevel }}" readonly>
                    </div>

                    <div>
                        <label class="form-label mb-1">{{ translate('webhook_url') }}</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="kyc-webhook-url" value="{{ $webhookUrl }}" readonly>
                            <button class="btn btn-outline-secondary" type="button"
                                    onclick="navigator.clipboard.writeText(document.getElementById('kyc-webhook-url').value)">
                                <i class="fi fi-rr-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">
                            {{ translate('add_this_url_as_an_http_webhook_in_your_sumsub_dashboard') }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
