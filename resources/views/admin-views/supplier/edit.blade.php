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
])
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/assets/back-end/js/admin/supplier-form.js') }}"></script>
@endpush
