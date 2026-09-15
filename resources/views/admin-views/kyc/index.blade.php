@extends('layouts.admin.app')

@section('title', translate('kyc_verifications'))

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-header flex-wrap gap-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fi fi-rr-shield-check"></i> {{ translate('kyc_verifications') }}</h5>
            <div class="d-flex gap-2">
                <span class="badge {{ $sumsubConfigured ? 'bg-success' : 'bg-danger' }}">
                    {{ $sumsubConfigured ? translate('sumsub_configured') : translate('sumsub_not_configured') }}
                </span>
                <span class="badge {{ $kycEnabled ? 'bg-success' : 'bg-secondary' }}">
                    {{ $kycEnabled ? translate('enabled') : translate('disabled') }}
                </span>
                <a href="{{ route('admin.kyc.settings') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fi fi-rr-settings"></i> {{ translate('sumsub_settings') }}
                </a>
            </div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.kyc.index') }}" method="GET" class="mb-4">
                <div class="row gy-2 gx-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="form-label">{{ translate('user_type') }}</label>
                        <select name="user_type" class="form-control form-control-sm">
                            <option value="">{{ translate('all') }}</option>
                            @foreach($userTypes as $userType)
                                <option value="{{ $userType }}" {{ ($filters['user_type'] ?? '') == $userType ? 'selected' : '' }}>
                                    {{ translate($userType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">{{ translate('status') }}</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('all') }}</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ ($filters['status'] ?? '') == $status ? 'selected' : '' }}>
                                    {{ translate($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">{{ translate('search') }}</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="{{ translate('applicant_id_or_external_user_id') }}">
                    </div>
                    <div class="col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fi fi-rr-search"></i> {{ translate('filter') }}
                        </button>
                        <a href="{{ route('admin.kyc.index') }}" class="btn btn-secondary btn-sm">
                            {{ translate('reset') }}
                        </a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-borderless align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('user') }}</th>
                            <th>{{ translate('user_type') }}</th>
                            <th>{{ translate('level') }}</th>
                            <th>{{ translate('applicant_id') }}</th>
                            <th>{{ translate('status') }}</th>
                            <th>{{ translate('required_at') }}</th>
                            <th>{{ translate('verified_at') }}</th>
                            <th class="text-center">{{ translate('action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($verifications as $key => $verification)
                            <tr>
                                <td>{{ $verifications->firstItem() + $key }}</td>
                                <td>{{ $verification->external_user_id }}</td>
                                <td><span class="badge bg-info text-dark">{{ translate($verification->user_type) }}</span></td>
                                <td>{{ $verification->level_name }}</td>
                                <td class="text-truncate" style="max-width: 180px;" title="{{ $verification->applicant_id }}">
                                    {{ $verification->applicant_id ?? '-' }}
                                </td>
                                <td>
                                    <span class="badge {{ $verification->isApproved() ? 'bg-success' : ($verification->isRejected() ? 'bg-danger' : 'bg-warning') }}">
                                        {{ translate($verification->status) }}
                                    </span>
                                    @if($verification->reject_type)
                                        <span class="badge bg-secondary">{{ $verification->reject_type }}</span>
                                    @endif
                                </td>
                                <td>{{ $verification->required_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>{{ $verification->verified_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <form action="{{ route('admin.kyc.sync', $verification->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-info btn-sm" title="{{ translate('sync_from_sumsub') }}">
                                                <i class="fi fi-rr-refresh"></i>
                                            </button>
                                        </form>
                                        @if($verification->applicant_id)
                                            <form action="{{ route('admin.kyc.reset', $verification->id) }}" method="POST"
                                                  onsubmit="return confirm('{{ translate('do_you_want_to_reset_this_verification') }}')">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ translate('reset_verification') }}">
                                                    <i class="fi fi-rr-undo"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <i class="fi fi-sr-inbox-in" style="font-size: 2rem;"></i>
                                    <p class="mt-2">{{ translate('no_kyc_verification_found') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {!! $verifications->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection
