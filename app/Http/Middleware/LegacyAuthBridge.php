<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that bridges legacy osTicket sessions to Laravel auth.
 *
 * Reads the OSTSESSID cookie, looks up the corresponding record in
 * the legacy ost_session table, and authenticates the staff member
 * in Laravel's 'staff' guard if the session is valid and not expired.
 *
 * This allows staff logged into the legacy SCP to be automatically
 * authenticated when accessing new Laravel-powered pages.
 *
 * @see osticket/include/class.session.php
 */
class LegacyAuthBridge
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('staff');
        $sessionId = $request->cookie('OSTSESSID');
        $legacyStaffId = $sessionId ? $this->resolveStaffId($sessionId) : null;

        if ($guard->check()) {
            if ($legacyStaffId && (int) $guard->id() !== $legacyStaffId) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $next($request);
        }

        if ($legacyStaffId) {
            $guard->loginUsingId($legacyStaffId);
        }

        return $next($request);
    }

    /**
     * Resolve a staff ID from a legacy osTicket session ID.
     *
     * Looks up the session in ost_session, verifies it hasn't expired,
     * and extracts the staff ID. First checks the user_id column directly,
     * then falls back to parsing the serialized session_data for the
     * _auth.staff.id value.
     */
    private function resolveStaffId(string $sessionId): ?int
    {
        $session = DB::connection('legacy')
            ->table('session')
            ->where('session_id', $sessionId)
            ->where('session_expire', '>', now())
            ->first(['user_id', 'session_data']);

        if (! $session) {
            return null;
        }

        if ($session->user_id && $session->user_id > 0) {
            return $this->verifyStaffExists((int) $session->user_id);
        }

        return $this->extractStaffIdFromSessionData($session->session_data);
    }

    /**
     * Parse osTicket's custom-serialized session data to extract the staff ID.
     *
     * osTicket uses PHP's session serialization format (pipe-delimited keys)
     * where the _auth section contains: _auth|a:1:{s:5:"staff";a:3:{s:2:"id";i:2;...}}
     */
    private function extractStaffIdFromSessionData(?string $data): ?int
    {
        if (! $data) {
            return null;
        }

        // Match _auth|a:..{...s:5:"staff";a:N:{s:2:"id";i:(\d+);...}...}
        if (preg_match('/_auth\|(.+?)(?=\w+\||$)/s', $data, $matches)) {
            $authData = @unserialize($matches[1], ['allowed_classes' => false]);

            if (! is_array($authData)) {
                return null;
            }

            $staffId = $authData['staff']['id'] ?? null;

            if ($staffId && $staffId > 0) {
                return $this->verifyStaffExists((int) $staffId);
            }
        }

        return null;
    }

    /**
     * Verify that a staff ID exists and the account is active.
     */
    private function verifyStaffExists(int $staffId): ?int
    {
        $exists = Staff::where('staff_id', $staffId)
            ->where('isactive', 1)
            ->exists();

        return $exists ? $staffId : null;
    }
}
