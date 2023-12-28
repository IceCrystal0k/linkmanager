<?php
namespace App\Http\Controllers\API\Auth;

use App\Enums\HttpCode;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseController
{
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
            $data = [];
            $data['token'] = $user->createToken(env('APP_NAME'))->plainTextToken;
            $data['first_name'] = $user->first_name;
            $data['last_name'] = $user->last_name;
            if (!$user->email_verified_at) {
                $data['email_verified'] = false;
            }
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
