<?php

namespace App\Services\Web;

use App\Traits\EmailTemplateTrait;
use App\Utils\Helpers;
use App\Utils\SMSModule;
use Exception;
use Illuminate\Support\Arr;

class CustomerAuthService
{
    use EmailTemplateTrait;

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

    public function storeCustomerAuthReturnURL(): void
    {
        $historyUrls = session('recent_user_routes_history', []);
        if (! empty($historyUrls)) {
            $lastUrl = end($historyUrls);
            session()->put('keep_customer_login_redirect_url', $lastUrl);
        } else {
            session()->forget('keep_customer_login_redirect_url');
        }
    }

    public function getCustomerAuthReturnURL(): string
    {
        $keepReturnUrl = route('home');
        if (session()->has('keep_customer_login_redirect_url')) {
            $keepReturnUrl = session('keep_customer_login_redirect_url');
        }

        return $keepReturnUrl;
    }
}
