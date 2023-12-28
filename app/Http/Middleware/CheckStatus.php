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
                return $this->sendError(['Invalid user status'], HttpCode::Unauthorized);
            }
            if ($user->email_verified_at === null) {
                // $user->tokens()->delete();
                return $this->sendError(['Email not verified'], HttpCode::Unauthorized);
            }
            return $response;
        }
        else {
            return $this->sendError(['Session not found'], HttpCode::Unauthorized);
        }
    }

    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    private function sendError($errors = [], $status = 404)
    {
    	$response = [
            'errors' => $errors,
        ];

        return response()->json($response, $status);
    }
}
