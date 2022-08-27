<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Guard;

class AuthCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

    protected $auth;

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }


    public function handle(Request $request, Closure $next, ...$auths)
    {
        if (Auth::check()) {
            if (Auth::user()->is_blocked) {
                return response()->json([
                    'status' => false,
                    'message' => 'User Has been Blocked !',
                    'errorCode' => '403',
                ], 409);
            } else {
            }
            if ($auths) {
                // print_r(Auth::user());
                // echo Auth::user()->id;
                // print_r(getUserAuth(Auth::user()->id));
                // exit;
                if (empty(array_intersect(getUserAuth(Auth::user()->id), $auths))) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unauthorize Access !',
                        'errorCode' => '402',
                    ], 409);
                }
            }else{
              return response()->json([
                  'status' => false,
                  'message' => 'Unauthorize Not Given to any user !',
                  'errorCode' => '403',
              ], 410);
            }
        }

        return $next($request);
    }
}
