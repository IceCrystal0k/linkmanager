<?php
namespace App\Http\Controllers\API\Auth;

use App\Enums\HttpCode;
use App\Enums\UserRole;
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

    private function getResponseData() {
        $user = Auth::user();
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
        try {
            $user = Socialite::driver('google')->with(['code' => $request['code']])->stateless()->user();
            $finduser = User::where('google_id', $user->id)->first();

            if ($finduser) {
                Auth::login($finduser);
                $data = $this->getResponseData();
                return $this->sendResponse($data);
            } else {
                // check for existing user, maybe it logged in with facebook account before
                $userModel = User::where('email', $user->email)->first();
                if ($userModel) {
                    $userModel->google_id = $user->id;
                    $userModel->save();
                    Auth::login($userModel);
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

                    $this->saveSessionTimezone($createUser->id);
                    // set user role to User
                    $createUser->roles()->attach(UserRole::User);

                    Auth::login($createUser);
                }
                $data = $this->getResponseData();
                return $this->sendResponse($data);
            }

        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), HttpCode::BadRequest);
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
        try {
            $user = Socialite::driver('facebook')->with(['code' => $request['code']])->stateless()->user();
            $finduser = User::where('fb_id', $user->id)->first();

            if ($finduser) {
                Auth::login($finduser);
                $data = $this->getResponseData();
                return $this->sendResponse($data);
            } else {
                // check for existing user, maybe it logged in with google account before
                $userModel = User::where('email', $user->email)->first();
                if ($userModel) {
                    $userModel->fb_id = $user->id;
                    $userModel->save();
                    Auth::login($userModel);
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

                    $this->saveSessionTimezone($createUser->id);
                    // set user role to User
                    $createUser->roles()->attach(UserRole::User);
                    Auth::login($createUser);
                }

                $data = $this->getResponseData();
                return $this->sendResponse($data);
            }

        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), HttpCode::BadRequest);
        }
    }

    private function extractNameFromEmail($email)
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

    private function extractNameFromFacebook($userName)
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

    private function saveSessionTimezone($userId)
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

    private function addUserRole($userId)
    {

    }
}
