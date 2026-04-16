<?php

use App\Models\Staff;
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

test('preserves native Laravel session when no OSTSESSID cookie is present', function () {
    $staff = Staff::where('isactive', 1)->first();
    if (! $staff) {
        $this->markTestSkipped('No active staff found.');
    }

    Auth::guard('staff')->loginUsingId($staff->staff_id);

    // No OSTSESSID cookie — middleware should NOT invalidate a native Laravel session
    // (staff authenticated via Laravel login+2FA flow won't have an OSTSESSID cookie)
    $response = $this->get('/scp/test-auth');

    $response->assertOk();
    $response->assertJson(['authenticated' => true]);
});

test('preserves native Laravel session when OSTSESSID belongs to another staff member', function () {
    DB::connection('legacy')->table('staff')->insert([
        [
            'staff_id' => 10,
            'dept_id' => 1,
            'username' => 'native-staff',
            'firstname' => 'Native',
            'lastname' => 'Staff',
            'email' => 'native@example.com',
            'passwd' => bcrypt('password'),
            'isactive' => 1,
            'isadmin' => 0,
            'created' => now(),
        ],
        [
            'staff_id' => 11,
            'dept_id' => 1,
            'username' => 'legacy-staff',
            'firstname' => 'Legacy',
            'lastname' => 'Staff',
            'email' => 'legacy@example.com',
            'passwd' => bcrypt('password'),
            'isactive' => 1,
            'isadmin' => 0,
            'created' => now(),
        ],
    ]);

    DB::connection('legacy')->table('session')->insert([
        'session_id' => 'legacy-session',
        'user_id' => 11,
        'session_expire' => now()->addHour(),
    ]);

    Auth::guard('staff')->loginUsingId(10);

    $response = $this->withUnencryptedCookie('OSTSESSID', 'legacy-session')
        ->get('/scp/test-auth');

    $response->assertOk();
    $response->assertJson([
        'authenticated' => true,
        'staff_id' => 10,
        'username' => 'native-staff',
    ]);
});
