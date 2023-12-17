<?php

namespace App\Http\Middleware;

use Closure;
use App\Enums\HttpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // If the status is not approved redirect to login
        $user = auth('sanctum')->user();
        if ($user) {
            if ($user->status !== 1) {
                // $user->tokens()->delete();
                return $this->sendError('Invalid user status', null, HttpCode::Unauthorized);
            }
            if ($user->email_verified_at === null) {
                // $user->tokens()->delete();
                return $this->sendError('Email not verified', null, HttpCode::Unauthorized);
            }
            return $response;
        }
        else {
            return $this->sendError('Unauthorised', ['error'=>'Session not found'], HttpCode::Unauthorized);
        }
    }

    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    private function sendError($error, $errorMessages = [], $code = 404)
    {
    	$response = [
            'success' => false,
            'message' => $error,
        ];

        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
