@extends('layouts.front-end.app')

@section('title', translate('kyc_verification'))

@section('content')
    <div class="container py-2 py-md-4 p-0 p-md-2 user-profile-container px-5px">
        <div class="row">
            @include('web-views.partials._profile-aside')

            <section class="col-lg-9 __customer-profile px-0">
                <div class="card">
                    <div class="card-body">
                        <h5 class="font-bold m-0 fs-16 mb-3">{{ translate('kyc_verification') }}</h5>

                        @if (!$kycStatus['verification_enabled'])
                            <div class="alert alert-warning mb-0">
                                {{ translate('kyc_verification_is_currently_unavailable') }}
                            </div>
                        @elseif ($kycStatus['is_verified'])
                            <div class="alert alert-success d-flex align-items-center gap-2 mb-0">
                                <i class="czi-check-circle fs-20"></i>
                                <div>
                                    <strong>{{ translate('your_account_is_verified') }}</strong>
                                    @if ($kycStatus['verified_at'])
                                        <div class="fs-12 text-muted">{{ $kycStatus['verified_at'] }}</div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info">
                                <p class="mb-1 font-weight-medium">
                                    {{ translate('you_need_to_verify_your_account_before_placing_more_orders') }}
                                </p>
                                <p class="mb-0 fs-13">
                                    {{ translate('kyc_documents_and_face_scan_required') }}
                                </p>
                            </div>

                            <div class="d-flex flex-wrap gap-3 mb-3 fs-13">
                                <span class="badge badge-soft-secondary">
                                    {{ translate('total_purchase') }}:
                                    {{ webCurrencyConverter(amount: $purchaseTotal) }}
                                </span>
                                <span class="badge badge-soft-secondary">
                                    {{ translate('verification_threshold') }}:
                                    {{ webCurrencyConverter(amount: $threshold) }}
                                </span>
                                <span class="badge badge-soft-{{ $kycStatus['is_verified'] ? 'success' : 'warning' }}">
                                    {{ translate('status') }}: {{ translate($kycStatus['status']) }}
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
                                    'kycTokenUrl' => route('customer.kyc.token'),
                                    'kycStatusUrl' => route('customer.kyc.status'),
                                ])
                            </div>
                        @endif
                    </div>
                </div>
            </section>
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

                        if (kyc.is_verified) {
                            window.location.reload();
                            return;
                        }

                        if (kyc.is_in_progress || kyc.needs_resubmission) {
                            window.location.reload();
                            return;
                        }

                        // The user closed the SDK early - allow a few retries
                        // before telling them nothing was submitted.
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
                    showMessage('');
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
