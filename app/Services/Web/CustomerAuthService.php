<?php

namespace App\Services\Web;

use App\Traits\EmailTemplateTrait;
use App\Utils\Helpers;
use App\Utils\SMSModule;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CustomerAuthService
{
    use EmailTemplateTrait;

    /**
     * @var list<string>
     */
    private const BLOCKED_RETURN_PATH_PATTERNS = [
        'admin/*',
        'vendor/*',
        'login/*',
        'customer/auth/*',
        'authentication-failed*',
    ];

    public function getCustomerVerificationToken(): string
    {
        return (string) rand(100000, 999999);
    }

    public function getCustomerLoginDataReset(): array
    {
        return [
            'login_hit_count' => 0,
            'is_temp_blocked' => 0,
            'temp_block_time' => null,
            'updated_at' => now(),
        ];
    }

    public function sendCustomerPhoneVerificationToken($phone, $token): array
    {
        $response = SMSModule::sendCentralizedSMS($phone, $token);

        return [
            'response' => $response,
            'status' => 'success',
            'message' => translate('please_check_your_SMS_for_OTP'),
        ];
    }

    public function sendCustomerEmailVerificationToken(object|array $user, string $token): array
    {
        $emailServicesSmtp = getWebConfig(name: 'mail_config');
        if ($emailServicesSmtp['status'] == 0) {
            $emailServicesSmtp = getWebConfig(name: 'mail_config_sendgrid');
        }

        $email = trim((string) (is_array($user) ? ($user['email'] ?? '') : ($user->email ?? '')));
        if ($emailServicesSmtp['status'] != 1 || $email === '') {
            return [
                'status' => 'error',
                'message' => translate('email_failed'),
            ];
        }

        try {
            $data = [
                'userName' => Arr::get(is_array($user) ? $user : $user->toArray(), 'f_name')
                    ?: Arr::get(is_array($user) ? $user : $user->toArray(), 'name', ''),
                'subject' => translate('registration_Verification_Code'),
                'title' => translate('registration_Verification_Code'),
                'verificationCode' => $token,
                'userType' => 'customer',
                'templateName' => 'registration-verification',
            ];

            $sent = $this->sendingMail(
                sendMailTo: $email,
                userType: 'customer',
                templateName: 'registration-verification',
                data: $data,
                sendSync: true,
            );

            if (! $sent) {
                return [
                    'status' => 'error',
                    'message' => translate('email_failed'),
                ];
            }

            return [
                'status' => 'success',
                'message' => translate('check_your_email'),
            ];
        } catch (Exception $exception) {
            return [
                'status' => 'error',
                'message' => translate('email_is_not_configured').'. '.translate('contact_with_the_administrator'),
            ];
        }
    }

    public function getCustomerRegisterData(object|array $request, object|array|null $referUser): array
    {
        return [
            'name' => $request['f_name'].' '.$request['l_name'],
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'is_active' => 1,
            'password' => bcrypt($request['password']),
            'referral_code' => Helpers::generate_referer_code(),
            'referred_by' => $referUser ? $referUser['id'] : null,
        ];
    }

    public function customerDashboardUrl(): string
    {
        return route('home');
    }

    public function sanitizeCustomerReturnUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            $url = url($url);
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if ($urlHost !== null && $appHost !== null && $urlHost !== $appHost) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $request = Request::create($path);

        if ($request->is(self::BLOCKED_RETURN_PATH_PATTERNS)) {
            return null;
        }

        return $url;
    }

    public function rememberCustomerReturnUrl(?string $url): void
    {
        $sanitizedUrl = $this->sanitizeCustomerReturnUrl($url);

        if ($sanitizedUrl !== null) {
            session()->put('keep_customer_login_redirect_url', $sanitizedUrl);

            return;
        }

        $this->storeCustomerAuthReturnURL();
    }

    public function storeCustomerAuthReturnURL(): void
    {
        $historyUrls = session('recent_user_routes_history', []);
        if (! empty($historyUrls)) {
            $lastUrl = $this->sanitizeCustomerReturnUrl((string) end($historyUrls));

            if ($lastUrl !== null) {
                session()->put('keep_customer_login_redirect_url', $lastUrl);

                return;
            }
        }

        session()->forget('keep_customer_login_redirect_url');
    }

    public function getCustomerAuthReturnURL(): string
    {
        if (session()->has('keep_customer_login_redirect_url')) {
            $sanitizedUrl = $this->sanitizeCustomerReturnUrl(
                (string) session('keep_customer_login_redirect_url')
            );

            if ($sanitizedUrl !== null) {
                return $sanitizedUrl;
            }

            session()->forget('keep_customer_login_redirect_url');
        }

        return $this->customerDashboardUrl();
    }
}
