<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->header('X-Session-Token');

        if ($token) {
            $session = UserSession::findByToken($token);
            if ($session && $session->isActive() && $session->user) {
                Auth::login($session->user);
            }
        }

        return $next($request);
    }
}


