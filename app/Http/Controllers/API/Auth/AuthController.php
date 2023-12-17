<?php
namespace App\Http\Controllers\API\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Enums\HttpCode;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseController
{
    /**
     * Login user
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        if(Auth::attempt(['email' => $request->email, 'password' => $request->password])){
            $user = Auth::user();
            $data = [];
            $data['token'] =  $user->createToken(env('APP_NAME'))->plainTextToken;
            $data['first_name'] =  $user->first_name;
            $data['last_name'] =  $user->last_name;
            if (!$user->email_verified_at) {
                $data['email_verified'] = false;
            }

            return $this->sendResponse($data, 'User login successfully.', HttpCode::Created);
        }
        else{
            return $this->sendError('Unauthorised', ['error'=>'Invalid username or password'], HttpCode::Unauthorized);
        }
    }

    /**
     * Logout user
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user) {
            $user->tokens()->delete();
            return $this->sendResponse(null, HttpCode::NoContent);
        }
        else {
            return $this->sendError('Unauthorised', ['error'=>'Session not found'], HttpCode::Unauthorized);
        }
    }
}
