@extends('layouts.admin.app')

@section('title', translate('edit_supplier'))

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4">{{ translate('edit_supplier') }}: {{ $supplier->name }}</h3>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.supplier.update', $supplier->id) }}" method="post" id="supplier-form" novalidate>
                @csrf

                <div class="row gy-3">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name', $supplier->name) }}" required>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('driver') }} <span class="text-danger">*</span></label>
                            <select name="driver" class="form-control" id="driver-select" required>
                                @foreach($drivers as $driver)
                                    @php
                                        $isLegacyDriver = ! in_array($driver, array_keys($driverPresets), true);
                                    @endphp
                                    <option value="{{ $driver }}" {{ old('driver', $supplier->driver) == $driver ? 'selected' : '' }}>
                                        {{ $driverPresets[$driver]['label'] ?? ucfirst(str_replace('_', ' ', $driver)) }}
                                        @if($isLegacyDriver) ({{ translate('legacy') }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            <small id="driver-description" class="text-muted d-block mt-2"></small>
                        </div>
                    </div>

                    <div class="col-lg-6" id="connector-preset-wrapper">
                        <div class="form-group">
                            <label class="form-label">{{ translate('connector_preset') ?: 'Connector Preset' }}</label>
                            <select class="form-control" id="connector-preset-select">
                                <option value="">{{ translate('custom_configuration') ?: 'Custom configuration' }}</option>
                                @foreach(($connectorPresets ?? []) as $presetKey => $connectorPreset)
                                    <option value="{{ $presetKey }}">{{ $connectorPreset['label'] ?? $presetKey }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">{{ translate('connector_preset_hint') ?: 'Apply a pre-built API configuration (Secret Orca, etc.).' }}</small>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('base_url') }} <span class="text-danger">*</span></label>
                            <input type="url" name="base_url" id="base-url-input" class="form-control"
                                   value="{{ old('base_url', $supplier->base_url) }}" required>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('auth_type') }} <span class="text-danger">*</span></label>
                            <select name="auth_type" id="auth-type-select" class="form-control" required></select>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('rate_limit_per_minute') }} <span class="text-danger">*</span></label>
                            <input type="number" name="rate_limit_per_minute" id="rate-limit-input" class="form-control"
                                   value="{{ old('rate_limit_per_minute', $supplier->rate_limit_per_minute) }}"
                                   min="1" max="1000" required>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('priority') }} <span class="text-danger">*</span></label>
                            <input type="number" name="priority" class="form-control"
                                   value="{{ old('priority', $supplier->priority) }}" min="0" required>
                            <small class="text-muted">{{ translate('lower_number_=_higher_priority') }}</small>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('sandbox_mode') }}</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_sandbox" value="1"
                                       id="sandbox-toggle" {{ old('is_sandbox', $supplier->is_sandbox) ? 'checked' : '' }}>
                                <label class="form-check-label" for="sandbox-toggle">{{ translate('enable_sandbox') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supports_direct_top_up') }}</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="supports_direct_top_up" value="1"
                                       id="topup-toggle" {{ old('supports_direct_top_up', $supplier->supports_direct_top_up) ? 'checked' : '' }}>
                                <label class="form-check-label" for="topup-toggle">{{ translate('enable_direct_top_up') }}</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border mt-3">
                    <div class="d-flex gap-3 align-items-center flex-wrap">
                        <span class="fw-semibold">{{ translate('health_status') }}:</span>
                        @php
                            $healthBadge = match($supplier->health_status) {
                                'healthy' => 'bg-success',
                                'degraded' => 'bg-warning text-dark',
                                'down' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                        @endphp
                        <span class="badge {{ $healthBadge }}">{{ $supplier->health_status }}</span>
                        @if($supplier->health_checked_at)
                            <small class="text-muted">{{ translate('last_checked') }}: {{ $supplier->health_checked_at->diffForHumans() }}</small>
                        @endif
                        @if($supplier->last_sync_at)
                            <span class="ms-3">|</span>
                            <small class="text-muted">{{ translate('last_sync') }}: {{ $supplier->last_sync_at->diffForHumans() }}</small>
                        @endif
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="mb-3"><i class="fi fi-rr-lock"></i> {{ translate('credentials') }}</h5>
                <p class="text-muted mb-3">{{ translate('leave_blank_to_keep_existing_credentials') }}</p>
                <div class="row gy-3" id="credentials-section"></div>

                <hr class="my-4">
                <h5 class="mb-3"><i class="fi fi-rr-settings"></i> {{ translate('driver_settings') }}</h5>
                <div class="row gy-3" id="settings-section"></div>

                @if($supplier->supports_direct_top_up)
                <hr class="my-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div>
                        <h5 class="mb-1"><i class="fi fi-rr-test"></i> {{ translate('test_direct_topup') ?: 'Test Direct Top-Up' }}</h5>
                        <p class="text-muted mb-0">{{ translate('test_direct_topup_hint') ?: 'Place a sandbox test order without affecting customer orders. Uses the same API payload as the Secret Orca CLI test commands.' }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($supplier->is_sandbox)
                            <span class="badge bg-info text-dark">{{ translate('sandbox_mode') }}</span>
                        @else
                            <span class="badge bg-warning text-dark">{{ translate('live_mode') ?: 'Live Mode' }}</span>
                        @endif
                        @if($isSecretOrcaSupplier ?? false)
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="test-topup-repair-btn">
                                {{ translate('repair_secret_orca_settings') ?: 'Repair Secret Orca Settings' }}
                            </button>
                        @endif
                    </div>
                </div>
                <div class="row gy-3" id="test-topup-panel">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('mapped_product') ?: 'Mapped Product' }}</label>
                            <select class="form-control" id="test-topup-mapping-select">
                                <option value="">{{ translate('select_mapped_product') ?: 'Select a direct top-up mapping' }}</option>
                                @foreach($testTopUpMappings ?? [] as $mapping)
                                    <option value="{{ $mapping['id'] }}"
                                            data-product-id="{{ $mapping['supplier_product_id'] }}"
                                            data-region="{{ $mapping['region'] }}"
                                            data-quantity="{{ $mapping['default_quantity'] }}"
                                            data-account-label="{{ $mapping['account_label'] }}">
                                        #{{ $mapping['id'] }}
                                        @if(!empty($mapping['product_name']))
                                            — {{ $mapping['product_name'] }}
                                        @endif
                                        @if(!empty($mapping['supplier_product_name']))
                                            ({{ $mapping['supplier_product_name'] }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @if(empty($testTopUpMappings))
                                <small class="text-warning d-block mt-1">
                                    {{ translate('no_direct_topup_mappings_found') ?: 'No active direct top-up mappings found. Create one under Supplier Mappings first.' }}
                                </small>
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label">{{ translate('supplier_product_id_SKU') }}</label>
                            <input type="text" class="form-control" id="test-topup-product-id" placeholder="Product UUID" readonly>
                            <small class="text-muted">{{ translate('auto_filled_from_mapping') ?: 'Auto-filled from the selected mapping.' }}</small>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label class="form-label" id="test-topup-account-label">{{ translate('target_account') ?: 'Target Account' }}</label>
                            <input type="text" class="form-control" id="test-topup-target-account" placeholder="Player ID">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('quantity') }}</label>
                            <input type="number" class="form-control" id="test-topup-quantity" min="1" step="1" value="900">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="form-group">
                            <label class="form-label">{{ translate('direct_topup_region') ?: 'Region' }}</label>
                            <input type="text" class="form-control text-uppercase" id="test-topup-region" maxlength="2" placeholder="EG">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="test-topup-auto-poll" checked>
                            <label class="form-check-label" for="test-topup-auto-poll">
                                {{ translate('auto_poll_order_status') ?: 'Auto-poll order status until completed or failed (same as CLI --poll)' }}
                            </label>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" id="test-topup-place-btn">
                            {{ translate('place_test_order') ?: 'Place Test Order' }}
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="test-topup-poll-btn" disabled>
                            {{ translate('poll_status') ?: 'Poll Status' }}
                        </button>
                    </div>
                    <div class="col-12">
                        <pre class="bg-light border rounded p-3 mb-0 small" id="test-topup-result">{{ translate('test_order_result_will_appear_here') ?: 'Test order result will appear here.' }}</pre>
                    </div>
                </div>
                @endif

                <div class="d-flex gap-3 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fi fi-sr-check"></i> {{ translate('update') }}
                    </button>
                    <a href="{{ route('admin.supplier.list') }}" class="btn btn-secondary">
                        {{ translate('cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin-views.supplier.partials._supplier-driver-script', [
    'formMode' => 'edit',
    'defaultDriver' => old('driver', $supplier->driver),
    'supplierId' => $supplier->id,
    'connectorPresets' => $connectorPresets ?? [],
    'testTopUpMappings' => $testTopUpMappings ?? [],
    'isSecretOrcaSupplier' => $isSecretOrcaSupplier ?? false,
    'isSandboxSupplier' => (bool) $supplier->is_sandbox,
])
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/supplier-form.js') }}"></script>
@endpush
