<?php

namespace App\Http\Controllers\RestAPI\v1\auth;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\LoginSetupRepositoryInterface;
use App\Contracts\Repositories\PhoneOrEmailVerificationRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Utils\CartManager;
use App\Utils\Helpers;
use Exception;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepo,
        private readonly PhoneOrEmailVerificationRepositoryInterface $phoneOrEmailVerificationRepo,
        private readonly LoginSetupRepositoryInterface $loginSetupRepo
    ) {}

    public function social_login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'unique_id' => 'required',
            'medium' => 'required|in:google,facebook,apple',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $client = new Client;
        $token = $request['token'];
        $email = $request['email'];
        $unique_id = $request['unique_id'];

        try {
            if ($request['medium'] == 'google') {
                $data = $this->resolveGoogleUserData($token);
            } elseif ($request['medium'] == 'facebook') {
                $res = $client->request('GET', 'https://graph.facebook.com/'.$unique_id.'?access_token='.$token.'&&fields=name,email');
                $data = json_decode($res->getBody()->getContents(), true);
                if (isset($data['id'])) {
                    $data['id'] = $data['id'];
                }
            } elseif ($request['medium'] == 'apple') {
                $apple_login = BusinessSetting::where(['type' => 'apple_login'])->first();
                if ($apple_login) {
                    $apple_login = json_decode($apple_login->value)[0];
                }
                $teamId = $apple_login->team_id;
                $keyId = $apple_login->key_id;
                $sub = $apple_login->client_id;
                $aud = 'https://appleid.apple.com';
                $iat = strtotime('now');
                $exp = strtotime('+60days');
                $keyContent = file_get_contents(dynamicStorage(path: 'storage/app/public/apple-login/'.$apple_login->service_file));

                $token = JWT::encode([
                    'iss' => $teamId,
                    'iat' => $iat,
                    'exp' => $exp,
                    'aud' => $aud,
                    'sub' => $sub,
                ], $keyContent, 'ES256', $keyId);
                $redirect_uri = $apple_login->redirect_url ?? 'www.example.com/apple-callback';
                $res = Http::asForm()->post('https://appleid.apple.com/auth/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $unique_id,
                    'redirect_uri' => $redirect_uri,
                    'client_id' => $sub,
                    'client_secret' => $token,
                ]);

                $claims = explode('.', $res['id_token'])[1];
                $data = json_decode(base64_decode($claims), true);
            }
        } catch (Exception $exception) {
            return response()->json(['error' => translate('wrong_credential')]);
        }

        if ($request['medium'] == 'apple' && isset($data['email'])) {
            $fast_name = strstr($data['email'], '@', true);
            $user = User::where('email', $data['email'])->first();
            if (isset($user) == false) {
                $user = User::create([
                    'f_name' => $fast_name,
                    'email' => $data['email'],
                    'phone' => '',
                    'password' => bcrypt($data['email']),
                    'is_active' => 1,
                    'login_medium' => $request['medium'],
                    'social_id' => $data['sub'],
                    'is_phone_verified' => 0,
                    'is_email_verified' => 1,
                    'referral_code' => Helpers::generate_referer_code(),
                    'temporary_token' => Str::random(40),
                ]);
            } else {
                $user->temporary_token = Str::random(40);
                $user->save();
            }
            if (! isset($user->phone)) {
                return response()->json([
                    'token_type' => 'update phone number',
                    'temporary_token' => $user->temporary_token]);
            }

            $token = self::login_process_passport($user, $user['email'], $data['email']);
            if ($token != null) {

                CartManager::cartListSessionToDatabase($request);

                return response()->json(['token' => $token]);
            }

            return response()->json(['error_message' => translate('customer_not_found_or_account_has_been_suspended')]);

        } elseif (isset($data['email']) && strcasecmp($email, $data['email']) === 0) {
            $name = explode(' ', $data['name']);
            if (count($name) > 1) {
                $fast_name = implode(' ', array_slice($name, 0, -1));
                $last_name = end($name);
            } else {
                $fast_name = implode(' ', $name);
                $last_name = '';
            }
            $user = User::where('email', $email)->first();
            if (isset($user) == false) {
                $user = User::create([
                    'f_name' => $fast_name,
                    'l_name' => $last_name,
                    'email' => $email,
                    'phone' => '',
                    'password' => bcrypt($data['id']),
                    'is_active' => 1,
                    'login_medium' => $request['medium'],
                    'social_id' => $data['id'],
                    'is_phone_verified' => 0,
                    'is_email_verified' => 1,
                    'referral_code' => Helpers::generate_referer_code(),
                    'temporary_token' => Str::random(40),
                ]);
            } else {
                $user->temporary_token = Str::random(40);
                $user->save();
            }
            if (! isset($user->phone)) {
                return response()->json([
                    'token_type' => 'update phone number',
                    'temporary_token' => $user->temporary_token]);
            }

            $token = self::login_process_passport($user, $user['email'], $data['id']);
            if ($token != null) {

                CartManager::cartListSessionToDatabase($request);

                return response()->json(['token' => $token]);
            }

            return response()->json(['error_message' => translate('customer_not_found_or_account_has_been_suspended')]);
        }

        return response()->json(['error' => translate('email_does_not_match')]);
    }

    public static function login_process_passport($user, $email, $password): ?string
    {
        $token = null;
        if (isset($user)) {
            auth()->login($user);
            $token = auth()->user()->createToken('LaravelAuthApp')->accessToken;
        }

        return $token;
    }

    /**
     * Resolve Google user data from either an ID token (JWT) or an access token.
     * Tries ID token verification first, then falls back to the userinfo endpoint with the access token.
     *
     * @return array{id: string, email: string, name: ?string, picture: ?string}|null
     */
    private function resolveGoogleUserData(string $token): ?array
    {
        // Try ID token verification (JWT format: 3 parts separated by dots)
        if (substr_count($token, '.') === 2) {
            $data = $this->verifyGoogleIdToken($token);
            if ($data) {
                return $data;
            }
        }

        // Fallback: use as access token against userinfo endpoint (v1 returns `id`, v3 returns `sub`)
        try {
            $client = new Client;

            // Try v1 first (returns `id` field directly)
            $res = $client->request('GET', 'https://www.googleapis.com/oauth2/v1/userinfo?access_token='.$token);
            $data = json_decode($res->getBody()->getContents(), true);

            if (isset($data['error'])) {
                // If v1 fails, try v3 which uses `sub` for the user id
                $res = $client->request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo?access_token='.$token);
                $data = json_decode($res->getBody()->getContents(), true);
            }

            if (! $data || (isset($data['error']) && ! isset($data['email']))) {
                return null;
            }

            // Normalize: ensure `id` field is always set (v3 uses `sub`)
            if (! isset($data['id']) && isset($data['sub'])) {
                $data['id'] = $data['sub'];
            }

            return [
                'id' => $data['id'] ?? $data['sub'] ?? null,
                'email' => $data['email'] ?? null,
                'name' => $data['name'] ?? null,
                'picture' => $data['picture'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verify Google ID Token and return user information
     * This handles the newer google_sign_in package (v7+) which returns ID tokens
     *
     * @return array{id: string, email: string, name: ?string, picture: ?string}|null
     */
    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            $client = new Client;

            // Verify the ID token with Google's tokeninfo endpoint
            $res = $client->request('GET', 'https://oauth2.googleapis.com/tokeninfo?id_token='.$idToken);
            $tokenInfo = json_decode($res->getBody()->getContents(), true);

            if (isset($tokenInfo['error']) || ! isset($tokenInfo['sub'])) {
                return null;
            }

            return [
                'id' => $tokenInfo['sub'],
                'email' => $tokenInfo['email'] ?? null,
                'name' => $tokenInfo['name'] ?? null,
                'picture' => $tokenInfo['picture'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function update_phone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'temporary_token' => 'required',
            'phone' => 'required|min:11|max:14',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $user = User::where(['temporary_token' => $request->temporary_token])->first();
        $user->phone = $request->phone;
        $user->save();

        $phoneVerification = getLoginConfig(key: 'phone_verification');

        if ($phoneVerification == 1) {
            return response()->json([
                'token_type' => 'phone verification on',
                'temporary_token' => $request['temporary_token'],
            ]);

        } else {
            return response()->json(['message' => translate('phone_number_updated_successfully')]);
        }
    }

    public function customerSocialLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'unique_id' => 'required',
            'email' => 'required_if:medium,google,facebook',
            'medium' => 'required|in:google,facebook,apple',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $client = new Client;
        $token = $request['token'];
        $email = $request['email'];
        $uniqueId = $request['unique_id'];
        $socialResponse = [];
        $data = null;

        try {
            if ($request['medium'] == 'google') {
                $data = $this->resolveGoogleUserData($token);
            } elseif ($request['medium'] == 'facebook') {
                $res = $client->request('GET', 'https://graph.facebook.com/'.$uniqueId.'?access_token='.$token.'&&fields=name,email');
                $data = json_decode($res->getBody()->getContents(), true);
            } elseif ($request['medium'] == 'apple') {
                if (substr_count($token, '.') === 2) {
                    $data = $this->verifyAppleIdentityToken($token);
                } else {
                    $apple_login = BusinessSetting::where(['type' => 'apple_login'])->first();
                    if ($apple_login) {
                        $apple_login = json_decode($apple_login->value, true)[0];
                    }
                    $teamId = $apple_login['team_id'];
                    $keyId = $apple_login['key_id'];
                    $sub = $apple_login['client_id'];
                    $aud = 'https://appleid.apple.com';
                    $iat = strtotime('now');
                    $exp = strtotime('+60days');
                    $keyContent = file_get_contents(dynamicStorage(path: 'storage/app/public/apple-login/'.$apple_login['service_file']));
                    $token = JWT::encode([
                        'iss' => $teamId,
                        'iat' => $iat,
                        'exp' => $exp,
                        'aud' => $aud,
                        'sub' => $sub,
                    ], $keyContent, 'ES256', $keyId);

                    $redirect_uri = $apple_login['redirect_url'] ?? 'www.example.com/apple-callback';

                    $response = Http::asForm()->post('https://appleid.apple.com/auth/token', [
                        'grant_type' => 'authorization_code',
                        'code' => $uniqueId,
                        'redirect_uri' => $redirect_uri,
                        'client_id' => $sub,
                        'client_secret' => $token,
                    ]);
                    $socialResponse = $response;
                    $claims = explode('.', $response['id_token'])[1];
                    $data = json_decode(base64_decode($claims), true);
                }
            }
        } catch (Exception $exception) {
            $errors = [];
            $errors[] = ['code' => 'auth-001', 'message' => 'Invalid Token'];

            return response()->json([
                'errors' => $errors,
                'message' => $exception->getMessage(),
            ], 401);
        }

        if (! $data) {
            $errors = [];
            $errors[] = ['code' => 'auth-002', 'message' => 'Failed to retrieve Google user information'];

            return response()->json([
                'errors' => $errors,
            ], 401);
        }

        if (! isset($claims) && isset($data) && isset($data['email']) && $request['medium'] !== 'apple') {
            if (strcasecmp($email, $data['email']) != 0) {
                return response()->json(['error' => translate('email_does_not_match')], 403);
            }
        }

        if ($request['medium'] === 'apple' && empty($data['email']) && ! empty($email)) {
            $data['email'] = $email;
        }

        if ($request['medium'] === 'apple' && empty($data['email'])) {
            return response()->json(['error' => translate('email_does_not_match')], 403);
        }

        $existingUser = $this->customerRepo->getFirstWhere(params: ['email' => $data['email']]);
        $temporaryToken = Str::random(40);

        if (! $existingUser) {
            return response()->json(['temp_token' => $temporaryToken, 'status' => false, 'new_user' => 0, 'socialResponse' => $socialResponse]);
        }

        if (! $existingUser['is_active'] || (getLoginConfig(key: 'phone_verification') && ! $existingUser['is_phone_verified'])) {
            return response()->json(['user' => $existingUser, 'status' => false]);
        }

        $this->customerRepo->updateWhere(params: ['email' => $data['email']], data: [
            'is_email_verified' => 1,
            'email_verified_at' => now(),
        ]);
        $token = $existingUser->createToken('LaravelAuthApp')->accessToken;

        return response()->json(['token' => $token, 'status' => true]);
    }

    public function existingAccountCheck(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'user_response' => 'required|in:0,1',
            'medium' => 'required|in:google,facebook,apple',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $user = $this->customerRepo->getFirstWhere(params: ['email' => $request['email']]);

        $temporaryToken = Str::random(40);
        if (! $user) {
            return response()->json(['temp_token' => $temporaryToken, 'status' => false]);
        }

        if ($request['user_response'] == 1) {
            $this->customerRepo->updateWhere(params: ['id' => $user['id']], data: [
                'email_verified_at' => now(),
                'login_medium' => $request['medium'],
            ]);

            $token = $user->createToken('LaravelAuthApp')->accessToken;

            return response()->json(['token' => $token, 'status' => true]);
        }

        $this->customerRepo->updateWhere(params: ['id' => $user['id']], data: [
            'email' => null,
            'email_verified_at' => null,
        ]);

        return response()->json(['temp_token' => $temporaryToken, 'status' => false]);
    }

    public function registrationWithSocialMedia(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|min:6|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $isPhoneExist = $this->customerRepo->getFirstWhere(params: ['phone' => $request['phone']]);

        if ($isPhoneExist) {
            return response()->json(['errors' => [
                ['code' => 'email', 'message' => translate('This phone has already been used in another account!')],
            ]], 403);
        }

        $temporaryToken = Str::random(40);
        $phoneVerificationStatus = getLoginConfig(key: 'phone_verification') ?? 0;

        $regData = [
            'name' => $request['name'],
            'f_name' => $request['name'],
            'l_name' => '',
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => bcrypt(rand(11111111, 99999999)),
            'temporary_token' => $temporaryToken,
            'email_verified_at' => now(),
            'referral_code' => Helpers::generate_referer_code(),
            'login_medium' => 'social',
        ];

        if ($phoneVerificationStatus) {
            cache()->put('registration_data_'.$request['phone'], [
                'reg_data' => $regData,
                'refer_user' => null,
            ], now()->addMinutes(60));

            return response()->json(['temp_token' => $temporaryToken, 'status' => false]);
        }

        $user = $this->customerRepo->add(data: $regData);
        $token = $user->createToken('LaravelAuthApp')->accessToken;

        return response()->json(['token' => $token]);
    }

    /**
     * @return array{email?: string, sub?: string}|null
     */
    private function verifyAppleIdentityToken(string $identityToken): ?array
    {
        $parts = explode('.', $identityToken);
        if (count($parts) < 2) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (! is_array($payload)) {
            return null;
        }

        if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
            return null;
        }

        $expectedAudience = (string) (config('app.ios_bundle_id') ?: 'com.buyselles.app');
        $audience = $payload['aud'] ?? null;
        if ($audience !== null && $audience !== $expectedAudience) {
            return null;
        }

        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        return [
            'email' => $payload['email'] ?? null,
            'sub' => $payload['sub'] ?? null,
        ];
    }
}
