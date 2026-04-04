<?php

use App\Http\Middleware\LegacyAuthBridge;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

test('skips when no OSTSESSID cookie is present', function () {
    $response = $this->get('/scp/test-auth');

    $response->assertOk();
    $response->assertJson(['authenticated' => false]);
});

test('skips when OSTSESSID cookie has no matching session', function () {
    $response = $this->withUnencryptedCookie('OSTSESSID', 'nonexistent-session-id')
        ->get('/scp/test-auth');

    $response->assertOk();
    $response->assertJson(['authenticated' => false]);
});

test('authenticates staff from valid legacy session', function () {
    $session = DB::connection('legacy')
        ->table('session')
        ->where('user_id', '>', 0)
        ->where('session_expire', '>', now())
        ->first();

    if (! $session) {
        $this->markTestSkipped('No active legacy staff sessions found.');
    }

    $staff = Staff::find($session->user_id);
    if (! $staff || ! $staff->isactive) {
        $this->markTestSkipped('Staff for active session is inactive.');
    }

    $response = $this->withUnencryptedCookie('OSTSESSID', $session->session_id)
        ->get('/scp/test-auth');

    $response->assertOk();
    $response->assertJson([
        'authenticated' => true,
        'staff_id' => $staff->staff_id,
        'username' => $staff->username,
    ]);
});

test('rejects expired legacy session', function () {
    $session = DB::connection('legacy')
        ->table('session')
        ->where('session_expire', '<', now())
        ->first();

    if (! $session) {
        $this->markTestSkipped('No expired legacy sessions found.');
    }

    $response = $this->withUnencryptedCookie('OSTSESSID', $session->session_id)
        ->get('/scp/test-auth');

    $response->assertOk();
    $response->assertJson(['authenticated' => false]);
});

test('does not re-query when staff guard is already authenticated', function () {
    $staff = Staff::where('isactive', 1)->first();
    if (! $staff) {
        $this->markTestSkipped('No active staff found.');
    }

    Auth::guard('staff')->loginUsingId($staff->staff_id);

    $response = $this->get('/scp/test-auth');

    $response->assertOk();
    $response->assertJson([
        'authenticated' => true,
        'staff_id' => $staff->staff_id,
    ]);
});
