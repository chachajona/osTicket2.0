<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('staff')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('scp.login'));
        }

        Auth::shouldUse('staff');

        return $next($request);
    }
}
