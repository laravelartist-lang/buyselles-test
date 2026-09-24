<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ translate('kyc_verification') }}</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        #sumsub-websdk-container {
            min-height: 100vh;
        }

        .kyc-launch-state {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            padding: 24px;
            text-align: center;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div id="sumsub-websdk-container">
        <div class="kyc-launch-state">{{ translate('please_wait') }}...</div>
    </div>

    <script>
        window.KycAppBridge = {
            /**
             * The apps intercept this scheme to close the WebView and refresh
             * the stored verification status.
             */
            scheme: 'buyselles-kyc',

            complete: function (status) {
                var target = this.scheme + '://complete?status=' + encodeURIComponent(status || 'pending');
                window.location.href = target;
            },

            cancelled: function () {
                window.location.href = this.scheme + '://cancelled';
            }
        };
    </script>

    <script src="https://static.sumsub.com/idensic/static/sns-websdk-builder.js"></script>

    <script>
        (function () {
            window.snsWebSdk
                .init(@json($applicant['token'] ?? ''), function () {
                    return fetch(@json($refreshUrl), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    }).then(function (response) {
                        return response.json();
                    }).then(function (data) {
                        return data.token;
                    });
                })
                .withConf({ lang: @json(str_replace('_', '-', app()->getLocale())) })
                .withOptions({ addViewportTag: false, adaptIframeHeight: true })
                .on('idCheck.onApplicantSubmitted', function () {
                    window.KycAppBridge.complete('pending');
                })
                .on('idCheck.onApplicantStatusChanged', function (payload) {
                    var answer = payload && payload.reviewResult ? payload.reviewResult.reviewAnswer : null;
                    window.KycAppBridge.complete(answer ? answer.toLowerCase() : 'pending');
                })
                .on('idCheck.onError', function (error) {
                    console.error('Sumsub WebSDK error', error);
                })
                .onMessage(function (type) {
                    if (type === 'idCheck.onApplicantSubmitted') {
                        window.KycAppBridge.complete('pending');
                    }
                })
                .build()
                .launch('#sumsub-websdk-container');
        })();
    </script>
</body>
</html>
