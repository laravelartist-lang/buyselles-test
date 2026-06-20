@extends('layouts.front-end.app')

@section('title', translate('order_Details') . ' - ' . translate('digital_codes'))

@section('content')
    <div class="container pb-5 mb-2 mb-md-4 mt-3 rtl __inline-47 text-start">
        <div class="row g-3">
            @include('web-views.partials._profile-aside')

            <section class="col-lg-9">
                @include('web-views.users-profile.account-details.partial')

                <div class="bg-sm-white mt-3">
                    <div class="p-sm-3 d-flex flex-column gap-3 pb-md-5">

                        @if ($digitalCodes->count() > 0 && $order->payment_status == 'paid')
                            <div class="bg-white border rounded-10 overflow-hidden">
                                <div class="d-flex align-items-center gap-2 px-3 py-2"
                                    style="background: linear-gradient(135deg, #006161 0%, #063c93 100%);">
                                    <i class="fi fi-rr-key text-white fs-16"></i>
                                    <h6 class="m-0 text-white fs-14 fw-semibold">
                                        {{ translate('Your_Digital_Codes') }}
                                    </h6>
                                </div>

                                <div class="p-3">
                                    @include('web-views.partials._digital-code-delivery-actions', [
                                        'orderIds' => [$order->id],
                                        'viewTarget' => 'digital-codes-container',
                                        'codesContainer' => 'digital-codes-container',
                                        'layout' => 'cards',
                                    ])

                                    <hr class="my-3">

                                    <div id="digital-codes-container" class="row g-2">
                                        @foreach ($digitalCodes as $idx => $dCode)
                                            <div class="col-md-6">
                                                <div class="border rounded-8 p-3 h-100 position-relative digital-code-item"
                                                    style="background: #f8fbff;">
                                                    <div class="fs-12 fw-semibold text-dark mb-1 text-truncate"
                                                        title="{{ $dCode['productName'] }}">
                                                        {{ $dCode['productName'] }}
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 mb-2">
                                                        <code class="fs-15 fw-bold text-break flex-grow-1"
                                                            id="detail-code-{{ $idx }}"
                                                            style="letter-spacing:1.5px; color:#063c93; background:transparent;">
                                                            {{ $dCode['code'] }}
                                                        </code>
                                                        <button type="button"
                                                            class="btn btn-sm p-0 border-0 copy-digital-code-btn"
                                                            data-code="{{ $dCode['code'] }}"
                                                            title="{{ translate('Copy') }}">
                                                            <i class="fi fi-rr-copy fs-14 text-muted"></i>
                                                        </button>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-2 fs-11 text-muted">
                                                        @if (!empty($dCode['pin']))
                                                            <span>
                                                                <strong>{{ translate('PIN') }}:</strong>
                                                                <code class="text-dark fw-semibold">{{ $dCode['pin'] }}</code>
                                                            </span>
                                                        @endif
                                                        @if (!empty($dCode['serial']))
                                                            <span>
                                                                <strong>{{ translate('Serial') }}:</strong>
                                                                {{ $dCode['serial'] }}
                                                            </span>
                                                        @endif
                                                        @if (!empty($dCode['expiry']))
                                                            <span>
                                                                <strong>{{ translate('Expires') }}:</strong>
                                                                {{ $dCode['expiry'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="text-center mt-2">
                                        <small class="text-danger fs-11">
                                            <i class="fi fi-rr-shield-exclamation"></i>
                                            {{ translate('Keep_these_codes_safe._Do_not_share_with_anyone.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-white border rounded-10 p-4 text-center">
                                <i class="fi fi-rr-key fs-30 text-muted mb-2 d-block"></i>
                                <h6 class="text-muted fs-14">{{ translate('No_digital_codes_available') }}</h6>
                                @if ($order->payment_status != 'paid')
                                    <p class="text-muted fs-12 mb-0">
                                        {{ translate('Digital_codes_will_be_available_once_payment_is_confirmed.') }}
                                    </p>
                                @endif
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
        $(document).on('click', '.copy-digital-code-btn', function() {
            var code = $(this).data('code');
            var $btn = $(this);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function() {
                    $btn.find('i').removeClass('fi-rr-copy').addClass('fi-rr-check text-success');
                    setTimeout(function() {
                        $btn.find('i').removeClass('fi-rr-check text-success').addClass('fi-rr-copy');
                    }, 2000);
                });
            } else {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(code).select();
                document.execCommand('copy');
                $temp.remove();
                $btn.find('i').removeClass('fi-rr-copy').addClass('fi-rr-check text-success');
                setTimeout(function() {
                    $btn.find('i').removeClass('fi-rr-check text-success').addClass('fi-rr-copy');
                }, 2000);
            }
        });
    </script>
    @include('web-views.partials._digital-code-delivery-script')
@endpush
