<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Contracts\Repositories\BusinessSettingRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\LoginSetupRepositoryInterface;
use App\Contracts\Repositories\PhoneOrEmailVerificationRepositoryInterface;
use App\Events\CustomerRegisteredViaReferralEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\CustomerRegistrationRequest;
use App\Models\PhoneOrEmailVerification;
use App\Services\FirebaseService;
use App\Services\RecaptchaService;
use App\Services\ReferByEarnCustomerService;
use App\Services\Web\CustomerAuthService;
use App\Traits\EmailTemplateTrait;
use App\Utils\CartManager;
use App\Utils\CustomerManager;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    use EmailTemplateTrait;

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepo,
        private readonly BusinessSettingRepositoryInterface $businessSettingRepo,
        private readonly PhoneOrEmailVerificationRepositoryInterface $phoneOrEmailVerificationRepo,
        private readonly LoginSetupRepositoryInterface $loginSetupRepo,
        private readonly CustomerAuthService $customerAuthService,
        private readonly ReferByEarnCustomerService $referByEarnCustomerService,
        private readonly FirebaseService $firebaseService,
    ) {
        $this->middleware('guest:customer', ['except' => ['logout']]);
    }

    public function getRegisterView(): View
    {
        $this->customerAuthService->storeCustomerAuthReturnURL();
        $keepCustomerLoginRedirectUrl = session('keep_customer_login_redirect_url');
        $recaptcha = getWebConfig(name: 'recaptcha');
        $mathNum1 = rand(1, 9);
        $mathNum2 = rand(1, 9);
        session(['default_recaptcha_id_customer_auth' => $mathNum1 + $mathNum2]);

        return view('web-views.customer-views.auth.register', [
            'recaptcha' => $recaptcha,
            'keepCustomerLoginRedirectUrl' => $keepCustomerLoginRedirectUrl,
            'mathNum1' => $mathNum1,
            'mathNum2' => $mathNum2,
        ]);
    }

    public function submitRegisterData(CustomerRegistrationRequest $request): JsonResponse|RedirectResponse
    {
        $result = RecaptchaService::verificationStatus(request: $request, session: 'default_recaptcha_id_customer_auth', action: 'customer_auth', firebase: true);
        if ($result && ! $result['status']) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => $result['message'],
                ]);
            }

            Toastr::error($result['message']);

            return back();
        }

        if ($request['keep_customer_login_redirect_url']) {
            session()->put('keep_customer_login_redirect_url', $request['keep_customer_login_redirect_url']);
        } else {
            $this->customerAuthService->storeCustomerAuthReturnURL();
        }

        $referUser = $request['referral_code'] ? $this->customerRepo->getFirstWhere(params: ['referral_code' => $request['referral_code']]) : null;
        $referralConfig = getWebConfig(name: 'ref_earning_customer');
        $referralEarningRate = $this->businessSettingRepo->getFirstWhere(params: ['type' => 'ref_earning_exchange_rate']);
        $regData = $this->customerAuthService->getCustomerRegisterData($request, $referUser);

        $phoneVerification = getLoginConfig(key: 'phone_verification');
        $emailVerification = getLoginConfig(key: 'email_verification');

        if ($phoneVerification || $emailVerification) {
            $identity = ($phoneVerification ? $regData['phone'] : $regData['email']);
            cache()->put('registration_data_'.$identity, [
                'reg_data' => $regData,
                'refer_user' => $referUser,
            ], now()->addMinutes(60));

            $user = (object) $regData; // Temporary object for verification check methods
            $user->is_phone_verified = 0;
            $user->is_email_verified = 0;

            if ($request->ajax()) {
                if ($phoneVerification) {
                    $this->phoneOrEmailVerificationRepo->delete(params: ['phone_or_email' => $user->phone]);
                    $verificationResult = $this->getCustomerVerificationCheck((array) $user, 'phone');
                    if (($verificationResult['status'] ?? '') !== 'success') {
                        return response()->json([
                            'error' => $verificationResult['message'] ?? translate('email_failed'),
                        ]);
                    }

                    return response()->json([
                        'redirect_url' => route('customer.auth.check-verification', ['identity' => base64_encode($user->phone), 'type' => base64_encode('phone_verification')]),
                    ]);
                } elseif ($emailVerification) {
                    $this->phoneOrEmailVerificationRepo->delete(params: ['phone_or_email' => $user->email]);
                    $verificationResult = $this->getCustomerVerificationCheck((array) $user, 'email');
                    if (($verificationResult['status'] ?? '') !== 'success') {
                        return response()->json([
                            'error' => $verificationResult['message'] ?? translate('email_failed'),
                        ]);
                    }

                    return response()->json([
                        'redirect_url' => route('customer.auth.check-verification', ['identity' => base64_encode($user->email), 'type' => base64_encode('email_verification')]),
                    ]);
                }
            } else {
                if ($phoneVerification) {
                    $this->phoneOrEmailVerificationRepo->delete(params: ['phone_or_email' => $user->phone]);
                    $verificationResult = $this->getCustomerVerificationCheck((array) $user, 'phone');
                    if (($verificationResult['status'] ?? '') !== 'success') {
                        Toastr::error($verificationResult['message'] ?? translate('email_failed'));

                        return back()->withInput();
                    }

                    return redirect(route('customer.auth.check-verification', ['identity' => base64_encode($user->phone), 'type' => base64_encode('phone_verification')]));
                }
                if ($emailVerification) {
                    $this->phoneOrEmailVerificationRepo->delete(params: ['phone_or_email' => $user->email]);
                    $verificationResult = $this->getCustomerVerificationCheck((array) $user, 'email');
                    if (($verificationResult['status'] ?? '') !== 'success') {
                        Toastr::error($verificationResult['message'] ?? translate('email_failed'));

                        return back()->withInput();
                    }

                    return redirect(route('customer.auth.check-verification', ['identity' => base64_encode($user->email), 'type' => base64_encode('email_verification')]));
                }
            }
        }

        $user = $this->customerRepo->add(data: $regData);
        if (! empty($referUser) && isset($referralConfig['ref_earning_discount_status']) && $referralConfig['ref_earning_discount_status'] == 1) {
            $referralCustomer = $this->referByEarnCustomerService->addReferralCustomerData(referralData: $referralConfig, referralEarningRate: $referralEarningRate, referUser: $referUser, userId: $user->id);
            event(new CustomerRegisteredViaReferralEvent($referralCustomer, $referUser));
        }

        auth('customer')->login($user);
        CustomerManager::updateCustomerSessionData(userId: auth('customer')->id());
        if ($request->ajax()) {
            return response()->json([
                'status' => 1,
                'message' => translate('registration_successful'),
                'redirect_url' => $this->customerAuthService->getCustomerAuthReturnURL(),
            ]);
        }
        Toastr::success(translate('registration_successful'));

        return redirect($this->customerAuthService->getCustomerAuthReturnURL());
    }

    public function getCustomerVerificationCheck($user, $type, $config = []): array
    {
        $token = $this->customerAuthService->getCustomerVerificationToken();
        $phoneVerification = getLoginConfig(key: 'phone_verification');
        $emailVerification = getLoginConfig(key: 'email_verification');
        if (isset($config['phone_verification'])) {
            $phoneVerification = $config['phone_verification'];
        }
        if (isset($config['email_verification'])) {
            $emailVerification = $config['email_verification'];
        }
        $firebaseOTPVerification = getWebConfig(name: 'firebase_otp_verification') ?? [];

        $response = [
            'status' => 'error',
            'message' => translate('email_failed'),
        ];

        if ($type === 'phone' && $phoneVerification && empty($user['is_phone_verified'])) {
            if ($firebaseOTPVerification && ($firebaseOTPVerification['status'] ?? 0)) {
                $response = $this->firebaseService->sendOtp($user['phone']);
                if (($response['status'] ?? '') === 'error') {
                    return [
                        'status' => 'error',
                        'message' => translate(strtolower($response['errors'] ?? 'failed')),
                    ];
                }
                $token = $response['sessionInfo'];
                $response = [
                    'status' => 'success',
                    'message' => translate('please_check_your_SMS_for_OTP'),
                ];
            } else {
                $response = $this->customerAuthService->sendCustomerPhoneVerificationToken($user['phone'], $token);
                if (($response['status'] ?? '') === 'success') {
                    Toastr::success($response['message']);
                }
            }
        } elseif ($type === 'email' && $emailVerification && empty($user['is_email_verified'])) {
            $response = $this->customerAuthService->sendCustomerEmailVerificationToken($user, $token);
        }

        if (($response['status'] ?? '') !== 'success') {
            return [
                'status' => 'error',
                'message' => $response['message'] ?? translate('email_failed'),
            ];
        }

        $this->phoneOrEmailVerificationRepo->add(data: [
            'phone_or_email' => $type === 'email' ? $user['email'] : $user['phone'],
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $response;
    }

    public function verificationCheckView(Request $request): View
    {
        $phoneVerification = getLoginConfig(key: 'phone_verification');
        $emailVerification = getLoginConfig(key: 'email_verification');

        $user = $this->customerRepo->getByIdentity(filters: ['identity' => base64_decode($request['identity'])]);
        if (! $user) {
            $cachedData = cache()->get('registration_data_'.base64_decode($request['identity']));
            if ($cachedData) {
                $user = $cachedData['reg_data'];
            }
        }

        $getTime = 0;
        $userVerify = 1;
        $verifyType = '';
        if ($user && $phoneVerification && (! isset($user['is_phone_verified']) || ! $user['is_phone_verified'])) {
            $userVerify = 0;
            $verifyType = 'phone';
        } elseif ($user && $emailVerification && (! isset($user['is_email_verified']) || ! $user['is_email_verified'])) {
            $userVerify = 0;
            $verifyType = 'email';
        }

        $OTPIdentity = $request['type'] && base64_decode($request['type']) == 'phone_verification' ? ($user['phone'] ?? base64_decode($request['identity'])) : ($user['email'] ?? base64_decode($request['identity']));
        $token = $this->phoneOrEmailVerificationRepo->getFirstWhere(params: ['phone_or_email' => $OTPIdentity]);
        if ($token) {
            $otpResendTime = getWebConfig(name: 'otp_resend_time') > 0 ? getWebConfig(name: 'otp_resend_time') : 0;
            $tokenTime = Carbon::parse($token['created_at']);
            $convertTime = $tokenTime->addSeconds((int) $otpResendTime);
            $getTime = $convertTime > Carbon::now() ? Carbon::now()->diffInSeconds($convertTime) : 0;
        }

        return view(VIEW_FILE_NAMES['customer_auth_verify'], [
            'user' => $user,
            'user_verify' => $userVerify,
            'verifyType' => $verifyType,
            'get_time' => $getTime,
        ]);
    }

    // Customer Default Verify
    public function verifyRegistration(Request $request): RedirectResponse|JsonResponse
    {
        $request->merge([
            'token' => trim((string) $request->input('token', '')),
        ]);

        Validator::make($request->all(), [
            'token' => 'required|digits:6',
        ])->validate();

        $result = RecaptchaService::verificationStatus(request: $request, session: 'default_recaptcha_id_customer_auth', action: 'customer_auth', firebase: true);
        if ($result && ! $result['status']) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => $result['message'],
                ]);
            }

            Toastr::error($result['message']);

            return back();
        }

        $maxOTPHit = getWebConfig(name: 'maximum_otp_hit') ?? 5;
        $maxOTPHitTime = getWebConfig(name: 'otp_resend_time') ?? 60; // seconds
        $tempBlockTime = getWebConfig(name: 'temporary_block_time') ?? 600; // seconds
        $firebaseOTPVerification = getWebConfig(name: 'firebase_otp_verification') ?? [];

        $customer = $this->customerRepo->getByIdentity(filters: ['identity' => base64_decode($request['identity'])]);
        $verificationType = base64_decode($request['type']);
        $identity = $customer ? ($verificationType == 'email_verification' ? $customer['email'] : $customer['phone']) : base64_decode($request['identity']);
        $identityType = $verificationType == 'email_verification' ? 'email' : 'phone';
        $getToken = $this->phoneOrEmailVerificationRepo->getFirstWhere(params: ['phone_or_email' => $identity]);

        if ($getToken) {
            if (isset($getToken->temp_block_time) && Carbon::parse($getToken->temp_block_time)->diffInSeconds() <= $tempBlockTime) {
                $time = $tempBlockTime - Carbon::parse($getToken->temp_block_time)->diffInSeconds();
                Toastr::error(translate('please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans());

                return redirect()->back();
            }

            if ($getToken['is_temp_blocked'] == 1 && Carbon::parse($getToken['updated_at'])->DiffInSeconds() >= $tempBlockTime) {
                $this->phoneOrEmailVerificationRepo->updateOrCreate(params: ['phone_or_email' => $identity], value: [
                    'otp_hit_count' => 0,
                    'is_temp_blocked' => 0,
                    'temp_block_time' => null,
                ]);
            }

            if ($getToken['otp_hit_count'] >= $maxOTPHit && Carbon::parse($getToken['updated_at'])->DiffInSeconds() < $maxOTPHitTime && $getToken['is_temp_blocked'] == 0) {
                $this->phoneOrEmailVerificationRepo->updateOrCreate(params: ['phone_or_email' => $identity], value: [
                    'is_temp_blocked' => 1,
                    'temp_block_time' => now(),
                ]);

                $time = $tempBlockTime - Carbon::parse($getToken['temp_block_time'])->DiffInSeconds();
                $errorMsg = translate('Too_many_attempts.').' '.translate('please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans();
                if (request()->ajax()) {
                    return response()->json([
                        'status' => 0,
                        'message' => $errorMsg,
                    ]);
                }
                Toastr::error($errorMsg);

                return redirect()->back();
            }

            if ($identityType == 'phone' && $firebaseOTPVerification && $firebaseOTPVerification['status']) {
                $firebaseVerify = $this->firebaseService->verifyOtp($getToken['token'], $getToken['phone_or_email'], $request['token']);
                $tokenVerifyStatus = (bool) ($firebaseVerify['status'] == 'success');
                if (! $tokenVerifyStatus) {
                    $this->phoneOrEmailVerificationRepo->updateOrCreate(params: ['phone_or_email' => $identity], value: [
                        'otp_hit_count' => ($getToken['otp_hit_count'] + 1),
                        'updated_at' => now(),
                        'temp_block_time' => null,
                    ]);
                    Toastr::error(translate(strtolower($firebaseVerify['errors'])));

                    return back();
                }
            } else {
                $tokenVerify = $this->phoneOrEmailVerificationRepo->getFirstWhere(params: [
                    'phone_or_email' => $identity,
                    'token' => (string) $request['token'],
                ]);
                $tokenVerifyStatus = (bool) $tokenVerify;
            }

            if ($tokenVerifyStatus) {
                if ($customer) {
                    $data = $verificationType == 'phone_verification' ? ['is_phone_verified' => 1] : ['is_email_verified' => 1];
                    $this->customerRepo->updateWhere(params: ['id' => $customer['id']], data: $data);
                    $user = $this->customerRepo->getFirstWhere(params: ['id' => $customer['id']]);
                } else {
                    $cachedData = cache()->get('registration_data_'.$identity);
                    if ($cachedData) {
                        $regData = $cachedData['reg_data'];
                        $referUser = $cachedData['refer_user'];
                        if ($verificationType == 'phone_verification') {
                            $regData['is_phone_verified'] = 1;
                        } else {
                            $regData['email_verified_at'] = now();
                            $regData['is_email_verified'] = 1;
                        }
                        $user = $this->customerRepo->add(data: $regData);

                        $referralData = getWebConfig(name: 'ref_earning_customer');
                        $referralEarningRate = $this->businessSettingRepo->getFirstWhere(params: ['type' => 'ref_earning_exchange_rate']);
                        if (! empty($referUser) && isset($referralData['ref_earning_discount_status']) && $referralData['ref_earning_discount_status'] == 1) {
                            $referralCustomer = $this->referByEarnCustomerService->addReferralCustomerData(
                                referralData: $referralData,
                                referralEarningRate: $referralEarningRate,
                                referUser: $referUser,
                                userId: $user['id']
                            );
                            event(new CustomerRegisteredViaReferralEvent($referralCustomer, $referUser));
                        }
                        cache()->forget('registration_data_'.$identity);
                    } else {
                        Toastr::error(translate('Registration data expired or not found.'));

                        return redirect()->back();
                    }
                }

                $this->phoneOrEmailVerificationRepo->delete(params: ['phone_or_email' => $identity]);
                auth('customer')->login($user);
                CustomerManager::updateCustomerSessionData(userId: auth('customer')->id());
                Toastr::success(translate('verification_done_successfully'));

                return redirect($this->customerAuthService->getCustomerAuthReturnURL());
            } else {
                if (isset($getToken->temp_block_time) && Carbon::parse($getToken->temp_block_time)->diffInSeconds() <= $tempBlockTime) {
                    $time = $tempBlockTime - Carbon::parse($getToken->temp_block_time)->diffInSeconds();
                    Toastr::error(translate('please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans());
                } elseif ($getToken['is_temp_blocked'] == 1 && isset($getToken->created_at) && Carbon::parse($getToken->created_at)->diffInSeconds() >= $tempBlockTime) {
                    $this->phoneOrEmailVerificationRepo->update(id: $getToken['id'], data: [
                        'otp_hit_count' => 1,
                        'is_temp_blocked' => 0,
                        'temp_block_time' => null,
                        'updated_at' => now(),
                    ]);
                } elseif ($getToken['otp_hit_count'] >= $maxOTPHit && $getToken['is_temp_blocked'] == 0) {
                    $this->phoneOrEmailVerificationRepo->update(id: $getToken['id'], data: [
                        'is_temp_blocked' => 1,
                        'temp_block_time' => now(),
                        'updated_at' => now(),
                    ]);

                    $time = $tempBlockTime - Carbon::parse($getToken['temp_block_time'])->diffInSeconds();
                    Toastr::error(translate('too_many_attempts. please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans());
                } else {
                    $this->phoneOrEmailVerificationRepo->update(id: $getToken['id'], data: [
                        'otp_hit_count' => $getToken['otp_hit_count'] + 1,
                        'updated_at' => now(),
                    ]);
                }
                Toastr::error(translate('invalid_OTP'));

                return back();
            }
        } else {
            $verificationData = $this->phoneOrEmailVerificationRepo->getFirstWhere(params: ['phone_or_email' => $identity]);

            if ($verificationData) {
                if (isset($verificationData->temp_block_time) && Carbon::parse($verificationData->temp_block_time)->DiffInSeconds() <= $tempBlockTime) {
                    $time = $tempBlockTime - Carbon::parse($verificationData->temp_block_time)->DiffInSeconds();
                    $errorMsg = translate('please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans();
                    if (request()->ajax()) {
                        return response()->json([
                            'status' => 0,
                            'message' => $errorMsg,
                        ]);
                    }
                    Toastr::error($errorMsg);

                    return redirect()->back();
                }

                if ($verificationData['is_temp_blocked'] == 1 && Carbon::parse($verificationData['updated_at'])->DiffInSeconds() >= $tempBlockTime) {
                    $this->phoneOrEmailVerificationRepo->updateOrCreate(params: ['phone_or_email' => $identity], value: [
                        'otp_hit_count' => 0,
                        'is_temp_blocked' => 0,
                        'temp_block_time' => null,
                    ]);
                }

                if ($verificationData['otp_hit_count'] >= $maxOTPHit && Carbon::parse($verificationData['updated_at'])->DiffInSeconds() < $maxOTPHitTime && $verificationData['is_temp_blocked'] == 0) {
                    $this->phoneOrEmailVerificationRepo->updateOrCreate(params: ['phone_or_email' => $identity], value: [
                        'is_temp_blocked' => 1,
                        'temp_block_time' => now(),
                    ]);

                    $time = $tempBlockTime - Carbon::parse($verificationData['temp_block_time'])->DiffInSeconds();
                    $errorMsg = translate('Too_many_attempts. please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans();
                    if (request()->ajax()) {
                        return response()->json([
                            'status' => 0,
                            'message' => $errorMsg,
                        ]);
                    }
                    Toastr::error($errorMsg);

                    return redirect()->back();
                }

                $this->phoneOrEmailVerificationRepo->updateOrCreate(params: ['phone_or_email' => $identity], value: [
                    'otp_hit_count' => ($verificationData['otp_hit_count'] + 1),
                    'updated_at' => now(),
                    'temp_block_time' => null,
                ]);
            }
        }

        $errorMsg = translate('OTP_is_not_matched');
        if (request()->ajax()) {
            return response()->json([
                'status' => 0,
                'message' => $errorMsg,
            ]);
        }
        Toastr::error($errorMsg);

        return redirect()->back();
    }

    // Customer Ajax Verify
    public function ajax_verify(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'token' => 'required',
        ])->validate();

        $email_status = getLoginConfig(key: 'email_verification');
        $phone_status = getLoginConfig(key: 'phone_verification');

        $user = $this->customerRepo->getFirstWhere(params: ['id' => $request['id']]);
        $verify = PhoneOrEmailVerification::where(['phone_or_email' => $user['email'], 'token' => $request['token']])->first();

        $maxOTPHit = getWebConfig(name: 'maximum_otp_hit') ?? 5;
        $temp_block_time = getWebConfig(name: 'temporary_block_time') ?? 5; // minute

        if (isset($verify)) {
            if (isset($verify->temp_block_time) && Carbon::parse($verify->temp_block_time)->diffInSeconds() <= $temp_block_time) {
                $time = $temp_block_time - Carbon::parse($verify->temp_block_time)->diffInSeconds();

                $verify_status = 'error';
                $message = translate('please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans();

                return response()->json([
                    'status' => $verify_status,
                    'message' => $message,
                ]);
            }

            ($email_status == 1 || ($phone_status == '0' && $email_status == '0')) ? ($user->is_email_verified = 1) : ($user->is_phone_verified = 1);
            $user->save();
            $verify->delete();

            $verify_status = 'success';
            $message = translate('verification_done_successfully');
        } else {
            $verification = PhoneOrEmailVerification::where(['phone_or_email' => $user['email']])->first();

            if ($verification) {
                if (isset($verification->temp_block_time) && Carbon::parse($verification->temp_block_time)->diffInSeconds() <= $temp_block_time) {
                    $time = $temp_block_time - Carbon::parse($verification->temp_block_time)->diffInSeconds();

                    $verify_status = 'error';
                    $message = translate('please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans();
                } elseif ($verification->is_temp_blocked == 1 && isset($verification->created_at) && Carbon::parse($verification->created_at)->diffInSeconds() >= $temp_block_time) {
                    $verification->otp_hit_count = 1;
                    $verification->is_temp_blocked = 0;
                    $verification->temp_block_time = null;
                    $verification->updated_at = now();
                    $verification->save();

                    $verify_status = 'error';
                    $message = translate('Verification_OTP_mismatched');
                } elseif ($verification->otp_hit_count >= $maxOTPHit && $verification->is_temp_blocked == 0) {
                    $verification->is_temp_blocked = 1;
                    $verification->temp_block_time = now();
                    $verification->updated_at = now();
                    $verification->save();

                    $time = $temp_block_time - Carbon::parse($verification->temp_block_time)->diffInSeconds();
                    $verify_status = 'error';
                    $message = translate('too_many_attempts. please_try_again_after_').CarbonInterval::seconds($time)->cascade()->forHumans();
                } else {
                    $verification->otp_hit_count += 1;
                    $verification->save();

                    $verify_status = 'error';
                    $message = translate('Verification code/ OTP mismatched');
                }
            } else {
                $verify_status = 'error';
                $message = translate('Verification code/ OTP mismatched');
            }
        }

        return response()->json([
            'status' => $verify_status,
            'message' => $message,
        ]);
    }

    public static function login_process($user, $email, $password): ?string
    {
        if (auth('customer')->attempt(['email' => $email, 'password' => $password], true)) {
            CustomerManager::updateCustomerSessionData(userId: auth('customer')->id());
            CartManager::cartListSessionToDatabase();

            return translate('welcome_to').' '.getWebConfig(name: 'company_name').'!';
        }

        return translate('credentials_are_not_matched_or_your_account_is_not_active');
    }

    public function resendOTPToCustomer(Request $request): JsonResponse|RedirectResponse
    {
        $result = RecaptchaService::verificationStatus(request: $request, session: 'default_recaptcha_id_customer_auth', action: 'customer_auth', firebase: true);
        if ($result && ! $result['status']) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => $result['message'],
                ]);
            }

            Toastr::error($result['message']);

            return back();
        }

        $maxOTPHitTime = getWebConfig(name: 'otp_resend_time') ?? 60; // seconds
        $verificationType = base64_decode($request['type']);
        $resolvedCustomer = $this->resolveCustomerForOtpResend($request, $verificationType);
        $customer = $resolvedCustomer['customer'];
        $identity = $resolvedCustomer['identity'];
        $identityType = $resolvedCustomer['identityType'];
        $getToken = $this->phoneOrEmailVerificationRepo->getFirstWhere(params: ['phone_or_email' => $identity]);

        $timeDifferance = 0;
        if ($getToken) {
            $tokenTime = Carbon::parse($getToken['created_at']);
            $addTime = $tokenTime->addSeconds((int) $maxOTPHitTime);
            $timeDifferance = $addTime > Carbon::now() ? Carbon::now()->diffInSeconds($addTime) : 0;
        }

        if ($timeDifferance > 0) {
            $message = translate('please_try_again_after_').CarbonInterval::seconds($timeDifferance)->cascade()->forHumans();
            if ($request->ajax()) {
                return response()->json([
                    'status' => 0,
                    'message' => $message,
                ]);
            }

            Toastr::error($message);

            return redirect()->back();
        }

        $this->phoneOrEmailVerificationRepo->delete(params: ['phone_or_email' => $identity]);

        if ($identityType === 'phone') {
            $phoneVerification = $verificationType === 'phone_verification' ? 1 : getLoginConfig(key: 'phone_verification');
            $verificationResult = $this->getCustomerVerificationCheck($customer, 'phone', ['phone_verification' => $phoneVerification]);
        } elseif ($identityType === 'email') {
            $verificationResult = $this->getCustomerVerificationCheck($customer, 'email', ['email_verification' => 1]);
        } else {
            Toastr::success(translate('registration_success_login_now'));

            return redirect(route('customer.auth.login'));
        }

        if (($verificationResult['status'] ?? '') !== 'success') {
            $errorMessage = $verificationResult['message'] ?? translate('email_failed');
            if ($request->ajax()) {
                return response()->json([
                    'status' => 0,
                    'message' => $errorMessage,
                ]);
            }

            Toastr::error($errorMessage);

            return redirect()->back();
        }

        $otpResendTime = getWebConfig(name: 'otp_resend_time') > 0 ? getWebConfig(name: 'otp_resend_time') : 0;
        if ($request->ajax()) {
            return response()->json([
                'status' => 1,
                'message' => translate('OTP_sent_successfully'),
                'new_time' => $otpResendTime,
            ]);
        }

        Toastr::success(translate('OTP_sent_successfully'));

        return redirect()->back();
    }

    private function resolveCustomerForOtpResend(Request $request, string $verificationType): array
    {
        $identity = base64_decode($request['identity']);
        $cachedData = cache()->get('registration_data_'.$identity);

        if ($cachedData) {
            $regData = $cachedData['reg_data'];
            $regData['is_phone_verified'] = $regData['is_phone_verified'] ?? 0;
            $regData['is_email_verified'] = $regData['is_email_verified'] ?? 0;

            if ($verificationType === 'email_verification') {
                $regData['email'] = $regData['email'] ?? $identity;
            } elseif ($verificationType === 'phone_verification') {
                $regData['phone'] = $regData['phone'] ?? $identity;
            }

            return [
                'customer' => $regData,
                'identity' => $identity,
                'identityType' => $verificationType === 'email_verification' ? 'email' : 'phone',
            ];
        }

        $customer = $this->customerRepo->getByIdentity(filters: ['identity' => $identity]);
        if ($customer) {
            return [
                'customer' => $customer,
                'identity' => $verificationType === 'email_verification' ? $customer['email'] : $customer['phone'],
                'identityType' => $verificationType === 'email_verification' ? 'email' : 'phone',
            ];
        }

        return [
            'customer' => [
                'phone' => $identity,
                'email' => $identity,
                'f_name' => '',
                'is_phone_verified' => 0,
                'is_email_verified' => 0,
            ],
            'identity' => $identity,
            'identityType' => $verificationType === 'email_verification' ? 'email' : 'phone',
        ];
    }
}
