<?php

namespace App\Http\Controllers\Vendor\Auth;

use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Enums\KycUserType;
use App\Enums\SessionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\LoginRequest;
use App\Repositories\VendorWalletRepository;
use App\Services\Kyc\KycService;
use App\Services\RecaptchaService;
use App\Services\VendorService;
use App\Traits\RecaptchaTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    use RecaptchaTrait;

    public function __construct(
        private readonly VendorRepositoryInterface $vendorRepo,
        private readonly VendorService $vendorService,
        private readonly VendorWalletRepository $vendorWalletRepo,
        private readonly KycService $kycService,

    ) {
        $this->middleware('guest:seller', ['except' => ['logout']]);
    }

    public function getLoginView(): View
    {
        $recaptchaBuilder = $this->generateDefaultReCaptcha(4);
        $recaptcha = getWebConfig(name: 'recaptcha');
        $mathNum1 = rand(1, 9);
        $mathNum2 = rand(1, 9);

        if (isset($recaptcha) && $recaptcha['status'] == 1) {
            Session::put(SessionKey::VENDOR_RECAPTCHA_KEY, $recaptchaBuilder->getPhrase());
        } else {
            Session::put(SessionKey::VENDOR_RECAPTCHA_KEY, $mathNum1 + $mathNum2);
        }

        return view('vendor-views.auth.login', compact('recaptchaBuilder', 'recaptcha', 'mathNum1', 'mathNum2'));
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $result = RecaptchaService::verificationStatus(request: $request, session: SessionKey::VENDOR_RECAPTCHA_KEY, action: 'login');
        if ($result && ! $result['status']) {
            ToastMagic::error($result['message']);

            return back();
        }

        $vendor = $this->vendorRepo->getFirstWhere(['identity' => $request['email']]);
        if (! $vendor) {
            ToastMagic::error(translate('credentials_doesnt_match').'!');

            return back();
        }
        $passwordCheck = Hash::check($request['password'], $vendor['password']);

        /*
         * Vendors still completing KYC are allowed in so they can finish
         * verification. The seller middleware keeps them on the KYC screen
         * until they are verified and approved.
         */
        $kycIncomplete = $this->kycService->isEnabled()
            && ! $this->kycService->isVerified(KycUserType::VENDOR, $vendor['id']);

        if ($passwordCheck && $vendor['status'] !== 'approved' && ! $kycIncomplete) {
            ToastMagic::error(translate('Not_approve_yet').'!');

            return back();
        }
        if ($this->vendorService->isLoginSuccessful($request->email, $request->password, $request->remember)) {
            if ($this->vendorWalletRepo->getFirstWhere(params: ['id' => auth('seller')->id()]) === false) {
                $this->vendorWalletRepo->add($this->vendorService->getInitialWalletData(vendorId: auth('seller')->id()));
            }
            ToastMagic::info(translate('welcome_to_your_dashboard').'.');

            return redirect()->route('vendor.dashboard.index');
        } else {
            ToastMagic::error(translate('credentials_doesnt_match').'!');

            return back();
        }
    }

    public function logout(): RedirectResponse
    {
        $this->vendorService->logout();
        ToastMagic::success(translate('logged_out_successfully').'.');

        return redirect()->route('vendor.auth.login');
    }
}
