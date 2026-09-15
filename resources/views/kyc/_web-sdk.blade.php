{{--
    Shared Sumsub WebSDK launcher.

    Required variables:
      $kycTokenUrl  - endpoint that returns a fresh SDK access token (POST)
      $kycStatusUrl - endpoint that returns the current verification state (GET)

    Optional:
      $kycContainerId - DOM id for the WebSDK iframe (default: sumsub-websdk-container)
      $kycWebSdkLang  - ISO 639-1 language code (defaults to the app locale)

    The access token is minted by the backend, so the exact same endpoint and
    the exact same Sumsub applicant serve the web storefront and both mobile
    apps: verifying here unblocks the app instantly.
--}}
@php
    $kycContainerId = $kycContainerId ?? 'sumsub-websdk-container';
    $kycWebSdkLang = $kycWebSdkLang ?? str_replace('_', '-', app()->getLocale());
@endphp

<div id="{{ $kycContainerId }}" class="w-100"></div>

<script src="https://static.sumsub.com/idensic/static/sns-websdk-builder.js"></script>
<script>
    window.KycWebSdk = (function () {
        const config = {
            tokenUrl: @json($kycTokenUrl),
            statusUrl: @json($kycStatusUrl),
            containerId: @json($kycContainerId),
            csrfToken: @json(csrf_token()),
            lang: @json($kycWebSdkLang),
        };

        let instance = null;

        function headers() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            };
        }

        function requestAccessToken() {
            return fetch(config.tokenUrl, {
                method: 'POST',
                headers: headers(),
                credentials: 'same-origin',
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.token) {
                        throw new Error(data.message || 'Unable to start verification.');
                    }

                    return data.token;
                });
            });
        }

        function launch(onMessage) {
            const container = document.getElementById(config.containerId);

            if (container) {
                container.innerHTML = '';
            }

            return requestAccessToken().then(function (accessToken) {
                instance = snsWebSdk
                    .init(accessToken, function () {
                        // The access token is short lived; hand the SDK a fresh one.
                        return requestAccessToken();
                    })
                    .withConf({ lang: config.lang })
                    .withOptions({ addViewportTag: false, adaptIframeHeight: true })
                    .on('idCheck.onError', function (error) {
                        console.error('Sumsub WebSDK error', error);
                    })
                    .onMessage(function (type, payload) {
                        if (typeof onMessage === 'function') {
                            onMessage(type, payload);
                        }
                    })
                    .build();

                return instance.launch('#' + config.containerId);
            });
        }

        function fetchStatus() {
            return fetch(config.statusUrl, {
                headers: headers(),
                credentials: 'same-origin',
            }).then(function (response) {
                return response.json();
            });
        }

        return {
            launch: launch,
            fetchStatus: fetchStatus,
        };
    })();
</script>
