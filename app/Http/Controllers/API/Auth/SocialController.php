<?php

namespace App\Http\Controllers\API\Auth;

use App\Enums\HttpCode;
use App\Enums\UserRole;
use App\Helpers\UserUtils;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Timezone;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\UserEmailToken;
use App\Models\UsersRole;
use App\Mail\Account\VerifyEmail;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function redirectToGoogle()
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();
        $response = (object)['url' => $url];
        return $this->sendResponse($response);
    }

    private function getResponseData(int $userId)
    {
        $user = User::find($userId);
        Auth::login($user);
        // $user = Auth::user();
        // delete all previous tokens
        $user->tokens()->delete();
        $data = [];

        // if user doesn't have the email verified, return a key value
        if (!$user->email_verified_at) {
            $data['verify_email_required'] = true;
        }

        $userRole = UsersRole::where('user_id', $user->id)->select('role_id')->first();
        $appName = Config::get('app.name');
        // create a new token
        $data['token'] = $user->createToken($appName)->plainTextToken;
        $data['first_name'] = $user->first_name;
        $data['last_name'] = $user->last_name;
        $data['role_id'] = $userRole ? $userRole->role_id : null;

        return $data;
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function handleGoogleCallback(Request $request)
    {
        $frontAppRedirectUrl = Config::get('app.front_app_url') . '/auth/social/exchange';
        try {

            $user = Socialite::driver('google')->stateless()->user();
            $finduser = User::where('google_id', $user->id)->first();

            if ($finduser) {
                // Auth::login($finduser);
                // $data = $this->getResponseData();
                // return $this->sendResponse($data);
                $exchangeCode = UserUtils::createSocialAuthExchangeCode('google', $finduser->id);
                return redirect($frontAppRedirectUrl . '?exc=' . $exchangeCode . '&uid=' . $finduser->id);
            } else {
                // check for existing user, maybe it logged in with facebook account before
                $userId = 0;
                $userModel = User::where('email', $user->email)->first();
                if ($userModel) {
                    $userId = $userModel->id;
                    $userModel->google_id = $user->id;
                    $userModel->save();
                    // Auth::login($userModel);
                } else {
                    $name = $this->extractNameFromEmail($user->email);
                    $password = Hash::make(Str::random(8));

                    $createUser = User::create([
                        'first_name' => $name['firstName'],
                        'last_name' => $name['lastName'],
                        'email' => $user->email,
                        'password' => $password,
                    ]);
                    $createUser->google_id = $user->id;
                    $createUser->email_verified_at = \Carbon\Carbon::now();
                    $createUser->save();

                    $userId = $createUser->id;
                    $this->saveSessionTimezone($createUser->id);
                    // set user role to User
                    $createUser->roles()->attach(UserRole::User);

                    // Auth::login($createUser);
                }
                $exchangeCode = UserUtils::createSocialAuthExchangeCode('google', $userId);
                return redirect($frontAppRedirectUrl . '?exc=' . $exchangeCode . '&uid=' . $userId);

                // $data = $this->getResponseData();
                // return $this->sendResponse($data);
            }
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), HttpCode::BadRequest);
        }
    }

    public function handleTokenExchange(Request $request)
    {
        if (!isset($request['uid']) && !isset($request['token'])) {
            return $this->sendError(['Invalid token'], HttpCode::Unauthorized);
        }

        $userId = (int)$request['uid'];
        $response = UserUtils::verifySocialAuthExchangeCode('google', $userId, $request['token']);
        if ($response) {
            $response = $this->getResponseData($userId);
            UserUtils::deleteSocialAuthExchangeCode('google', $userId);
            return $this->sendResponse($response);
        } else {
            return $this->sendError(['Invalid token'], HttpCode::Unauthorized);
        }
    }

    public function redirectToFacebook()
    {
        $url = Socialite::driver('facebook')->stateless()->redirect()->getTargetUrl();
        $response = (object)['url' => $url];
        return $this->sendResponse($response);
    }

    public function handleFacebookCallback(Request $request)
    {
        $frontAppRedirectUrl = Config::get('app.front_app_url') . '/auth/social/exchange';
        try {
            $user = Socialite::driver('facebook')->with(['code' => $request['code']])->stateless()->user();
            $finduser = User::where('fb_id', $user->id)->first();

            if ($finduser) {
                // Auth::login($finduser);
                // $data = $this->getResponseData();
                // return $this->sendResponse($data);
                $exchangeCode = UserUtils::createSocialAuthExchangeCode('facebook', $finduser->id);
                return redirect($frontAppRedirectUrl . '?exc=' . $exchangeCode . '&uid=' . $finduser->id);
            } else {
                // check for existing user, maybe it logged in with facebook account before
                $userId = 0;
                $userModel = User::where('email', $user->email)->first();
                if ($userModel) {
                    $userId = $userModel->id;
                    $userModel->fb_id = $user->id;
                    $userModel->save();
                    // Auth::login($userModel);
                } else {
                    $name = $this->extractNameFromFacebook($user->name);
                    $password = Hash::make(Str::random(8));
                    // make use of $user->avatar if needed
                    $createUser = User::create([
                        'first_name' => $name['firstName'],
                        'last_name' => $name['lastName'],
                        'email' => $user->email,
                        'password' => $password,
                    ]);
                    $createUser->fb_id = $user->id;
                    $createUser->email_verified_at = \Carbon\Carbon::now();
                    $createUser->save();

                    $userId = $createUser->id;
                    $this->saveSessionTimezone($createUser->id);
                    // set user role to User
                    $createUser->roles()->attach(UserRole::User);
                    // Auth::login($createUser);
                }

                $exchangeCode = UserUtils::createSocialAuthExchangeCode('facebook', $userId);
                return redirect($frontAppRedirectUrl . '?exc=' . $exchangeCode . '&uid=' . $userId);

                // $data = $this->getResponseData();
                // return $this->sendResponse($data);
            }
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), HttpCode::BadRequest);
        }
    }

    private function extractNameFromEmail(string $email)
    {
        $userName = substr($email, 0, strpos($email, '@'));
        $symbols = ['.', '_', '-'];
        $firstName = $userName;
        $lastName = $userName;
        foreach ($symbols as $symbol) {
            $symbolPos = strpos($userName, $symbol);
            if ($symbolPos > 0) {
                $firstName = substr($userName, 0, $symbolPos);
                $lastName = substr($userName, $symbolPos + 1);
                break;
            }
        }
        return ['firstName' => $firstName, 'lastName' => $lastName];
    }

    private function extractNameFromFacebook(string $userName)
    {
        $symbols = [' ', '.', '_', '-'];
        $firstName = $userName;
        $lastName = $userName;
        foreach ($symbols as $symbol) {
            $symbolPos = strpos($userName, $symbol);
            if ($symbolPos > 0) {
                $firstName = substr($userName, 0, $symbolPos);
                $lastName = substr($userName, $symbolPos + 1);
                break;
            }
        }
        return ['firstName' => $firstName, 'lastName' => $lastName];
    }

    private function saveSessionTimezone(int $userId)
    {
        $timezoneOffset = Session::get('timezone-offset');
        if ($timezoneOffset) {
            $timeZone = Timezone::where('offset_hours', $timezoneOffset)->select('id')->first();
            if ($timeZone) {
                $userInfo = new UserInfo();
                $userInfo->user_id = $userId;
                $userInfo->timezone = $timeZone->id;
                $userInfo->save();
            }
        }
    }

    private function addUserRole($userId) {}
}
