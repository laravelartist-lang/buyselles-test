@extends('layouts.vendor.app')

@section('title', translate('vendor_kyc_verification'))

@section('content')
    <div class="content container-fluid">
        <div class="mb-3">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <i class="tio-verified fs-25"></i>
                {{ translate('vendor_kyc_verification') }}
            </h2>
        </div>

        <div class="row">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body">
                        @if (!$sumsubConfigured)
                            <div class="alert alert-warning mb-0">
                                {{ translate('kyc_verification_is_currently_unavailable') }}
                            </div>
                        @elseif ($kycStatus['is_verified'])
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-0">
                                <i class="tio-checkmark-circle fs-20"></i>
                                <div>
                                    <strong>{{ translate('your_account_is_verified') }}</strong>
                                    @if ($kycStatus['verified_at'])
                                        <div class="fs-12">{{ translate('kyc_verified_at') }}: {{ $kycStatus['verified_at'] }}</div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <p class="mb-1 font-weight-medium">
                                    {{ translate('please_complete_your_kyc_verification_to_continue') }}
                                </p>
                                <p class="mb-0 fs-13">
                                    {{ translate('kyc_documents_and_face_scan_required') }}
                                </p>
                            </div>

                            <div class="d-flex flex-wrap gap-3 mb-3 fs-13">
                                <span class="badge badge-soft-secondary">
                                    {{ translate('status') }}: {{ translate($kycStatus['status']) }}
                                </span>
                                <span class="badge badge-soft-secondary">
                                    {{ translate('level') }}: {{ $kycStatus['level_name'] }}
                                </span>
                            </div>

                            @if ($kycStatus['is_in_progress'])
                                <div class="alert alert-warning">
                                    <strong>{{ translate('your_kyc_verification_is_under_review') }}</strong>
                                    <p class="mb-0 fs-13">{{ translate('we_will_notify_you_once_the_review_is_complete') }}</p>
                                </div>
                            @endif

                            @if ($kycStatus['needs_resubmission'])
                                <div class="alert alert-danger">
                                    <strong>{{ translate('your_kyc_verification_was_rejected_please_submit_again') }}</strong>
                                    @if (!empty($kycStatus['rejection_reason']))
                                        <p class="mb-0 fs-13">{{ $kycStatus['rejection_reason'] }}</p>
                                    @endif
                                </div>
                            @endif

                            <button type="button" id="kyc-start-button" class="btn btn--primary">
                                {{ $kycStatus['status'] === 'not_started' ? translate('start_verification') : translate('continue_verification') }}
                            </button>

                            <p id="kyc-message" class="fs-13 text-muted mt-2 mb-0 d-none"></p>

                            <div class="mt-3">
                                @include('kyc._web-sdk', [
                                    'kycTokenUrl' => route('vendor.kyc.token'),
                                    'kycStatusUrl' => route('vendor.kyc.status'),
                                ])
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function () {
            const startButton = document.getElementById('kyc-start-button');
            const message = document.getElementById('kyc-message');

            if (!startButton || typeof window.KycWebSdk === 'undefined') {
                return;
            }

            let statusChecks = 0;

            function showMessage(text) {
                if (!message) {
                    return;
                }
                message.textContent = text;
                message.classList.remove('d-none');
            }

            function checkStatus() {
                window.KycWebSdk.fetchStatus()
                    .then(function (response) {
                        const kyc = response && response.kyc ? response.kyc : null;

                        if (!kyc) {
                            return;
                        }

                        if (kyc.is_verified || kyc.is_in_progress || kyc.needs_resubmission) {
                            window.location.reload();
                            return;
                        }

                        statusChecks += 1;
                        if (statusChecks < 4) {
                            setTimeout(checkStatus, 2500);
                        } else {
                            showMessage(@json(translate('verification_was_not_completed_you_can_try_again_anytime')));
                        }
                    })
                    .catch(function () {
                        showMessage(@json(translate('unable_to_check_verification_status_please_refresh_the_page')));
                    });
            }

            startButton.addEventListener('click', function () {
                startButton.disabled = true;
                showMessage(@json(translate('opening_verification_please_wait')));

                window.KycWebSdk.launch().then(function () {
                    startButton.disabled = false;
                    message.classList.add('d-none');
                    checkStatus();
                }).catch(function (error) {
                    startButton.disabled = false;
                    showMessage(error.message || @json(translate('unable_to_start_kyc_verification_please_try_again')));
                });
            });
        })();
    </script>
@endpush
