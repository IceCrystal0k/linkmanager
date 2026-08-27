<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Arr;
use App\Enums\HttpCode;

class ThrottleSuccess extends ThrottleRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  array  $limits
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    protected function handleRequest($request, Closure $next, array $limits)
    {
        $response = $next($request); // call the controller first
        $responseData = $response->getData();

        if ($responseData->status === HttpCode::OK || $responseData->status === HttpCode::Created) { // only hit limiter on successful response
            foreach ($limits as $limit) {
                if ($this->limiter->tooManyAttempts($limit->key, $limit->maxAttempts)) {
                    throw $this->buildException($request, $limit->key, $limit->maxAttempts, $limit->responseCallback);
                }

                $this->limiter->hit($limit->key, $limit->decayMinutes * 60);
            }
        }

        foreach ($limits as $limit) {
            $response = $this->addHeaders(
                $response,
                $limit->maxAttempts,
                $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts)
            );
        }

        return $response;
    }
}
