<?php

use App\Models\Staff;
use App\Services\StaffTwoFactorService;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

it('renders the wizard at step 1 by default', function () {
    $staff = Staff::factory()->create(['passwd' => Hash::make('hello'), 'isactive' => 1]);

    $this->actingAs($staff, 'staff');
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

    $response = $this->get('/scp/account/security/two-factor');

    $response->assertInertia(fn ($page) => $page
        ->component('Account/Security/TwoFactorWizard')
        ->where('step', 1)
        ->where('twoFactor.enabled', false)
        ->where('twoFactor.pending', false)
    );
});

it('jumps to step 3 when a secret exists but setup is still pending', function () {
    $staff = Staff::factory()->create(['passwd' => Hash::make('hello'), 'isactive' => 1]);
    app(StaffTwoFactorService::class)->enable($staff, true);

    $this->actingAs($staff, 'staff');
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

    $response = $this->get('/scp/account/security/two-factor?step=3');

    $response->assertInertia(fn ($page) => $page
        ->component('Account/Security/TwoFactorWizard')
        ->where('step', 3)
        ->where('twoFactor.pending', true)
        ->whereNotNull('twoFactor.qrCodeSvg')
        ->whereNotNull('twoFactor.setupKey')
    );
});

it('exposes flashed recovery codes on the wizard page', function () {
    $staff = Staff::factory()->create(['passwd' => Hash::make('hello'), 'isactive' => 1]);

    $this->actingAs($staff, 'staff');
    $this->withSession([
        'auth.password_confirmed_at' => now()->timestamp,
        'two_factor_recovery_codes' => ['alpha', 'beta'],
    ]);

    $response = $this->get('/scp/account/security/two-factor?step=4');

    $response->assertInertia(fn ($page) => $page
        ->where('step', 4)
        ->where('twoFactor.recoveryCodes', ['alpha', 'beta'])
    );
});

it('returns to the wizard after password confirmation', function () {
    $staff = Staff::factory()->create([
        'passwd' => Hash::make('hello'),
        'isactive' => 1,
    ]);

    $this->actingAs($staff, 'staff')
        ->get('/scp/account/security/two-factor?step=1')
        ->assertRedirect('/scp/account/security/confirm-password');

    $this->actingAs($staff, 'staff')
        ->post('/scp/account/security/confirm-password', [
            'password' => 'hello',
        ])
        ->assertRedirect('/scp/account/security/two-factor?step=1');
});

it('redirects wizard enable and confirm actions back into the wizard flow', function () {
    $this->travelTo(now());

    $staff = Staff::factory()->create([
        'passwd' => Hash::make('hello'),
        'isactive' => 1,
    ]);

    $this->actingAs($staff, 'staff')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post('/scp/account/security/two-factor/enable', [
            'force' => true,
            'return_to_wizard' => true,
        ])
        ->assertRedirect('/scp/account/security/two-factor?step=2');

    $otp = app(Google2FA::class)->getCurrentOtp((string) $staff->fresh()->two_factor_secret);

    $this->actingAs($staff->fresh(), 'staff')
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post('/scp/account/security/two-factor/confirm', [
            'code' => $otp,
            'return_to_wizard' => true,
        ])
        ->assertRedirect('/scp/account/security/two-factor?step=4');

    $this->travelBack();
});
