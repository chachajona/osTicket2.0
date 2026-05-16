<?php

declare(strict_types=1);

use App\Models\Staff;
use Inertia\Testing\AssertableInertia;

it('exposes mail event ownership map to Inertia shared props', function (): void {
    config([
        'mail.event_class_owner.reply' => 'laravel',
        'mail.event_class_owner.close_notify' => 'legacy',
    ]);

    $staff = Staff::factory()->admin()->create();

    $this->actingAs($staff, 'staff')
        ->get(route('scp.preferences.show'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mail_event_owner.reply', 'laravel')
            ->where('mail_event_owner.close_notify', 'legacy'));
});
