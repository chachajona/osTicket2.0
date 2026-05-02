<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
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

        if (! $staff instanceof Staff) {
            abort(403, 'Invalid staff session.');
        }

        if (! (bool) $staff->isactive) {
            Auth::guard('staff')->logout();

            abort(403, 'Your staff account is inactive.');
        }

        if (! $staff->canAccessAdminPanel()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            abort(403, 'You do not have permission to access the admin area.');
        }

        return $next($request);
    }
}
