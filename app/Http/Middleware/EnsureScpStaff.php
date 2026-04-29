<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureScpStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = Auth::guard('staff')->user();

        if (! $staff) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('scp.login'));
        }

        if (! (bool) $staff->isactive) {
            Auth::guard('staff')->logout();

            abort(403, 'Your staff account is inactive.');
        }

        return $next($request);
    }
}
