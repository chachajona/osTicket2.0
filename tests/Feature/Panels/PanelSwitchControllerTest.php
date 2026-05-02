<?php

declare(strict_types=1);

use App\Models\Scp\StaffPreference;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    config()->set('panel_landing', require base_path('config/panel_landing.php'));

    $schema = Schema::connection('osticket2');

    if (! $schema->hasColumn('staff_preferences', 'last_active_panel')) {
        $schema->table('staff_preferences', function (Blueprint $table): void {
            $table->string('last_active_panel', 16)->default('scp');
        });
    }

    if (! $schema->hasColumn('staff_preferences', 'default_scp_tab')) {
        $schema->table('staff_preferences', function (Blueprint $table): void {
            $table->string('default_scp_tab', 64)->nullable();
        });
    }

    if (! $schema->hasColumn('staff_preferences', 'default_admin_tab')) {
        $schema->table('staff_preferences', function (Blueprint $table): void {
            $table->string('default_admin_tab', 64)->nullable();
        });
    }
});

function makePanelSwitchStaff(array $attributes = []): Staff
{
    return Staff::factory()->create(array_merge([
        'isactive' => 1,
        'isadmin' => 0,
    ], $attributes));
}

test('admin switches to admin panel, redirects to the default admin tab, and persists the choice', function (): void {
    $staff = makePanelSwitchStaff(['isadmin' => 1]);

    actingAs($staff, 'staff');

    post(route('panel.switch'), ['panel' => 'admin'])
        ->assertRedirect('/admin/help-topics');

    expect(StaffPreference::forStaff($staff->staff_id)->last_active_panel)->toBe('admin');
});

test('admin honors the preferred default admin tab', function (): void {
    $staff = makePanelSwitchStaff(['isadmin' => 1]);

    StaffPreference::forStaff($staff->staff_id)->update([
        'default_admin_tab' => 'staff',
    ]);

    actingAs($staff, 'staff');

    post(route('panel.switch'), ['panel' => 'admin'])
        ->assertRedirect('/admin/staff');
});

test('non admin staff receive forbidden when switching to admin panel', function (): void {
    $staff = makePanelSwitchStaff();

    actingAs($staff, 'staff');

    $response = post(route('panel.switch'), ['panel' => 'admin']);

    $response->assertForbidden();
    expect(StaffPreference::forStaff($staff->staff_id)->last_active_panel)->toBe('scp');
});

test('authenticated staff can switch to the scp panel and are redirected by the resolver', function (): void {
    $staff = makePanelSwitchStaff();

    StaffPreference::forStaff($staff->staff_id)->update([
        'default_scp_tab' => 'queues',
    ]);

    actingAs($staff, 'staff');

    post(route('panel.switch'), ['panel' => 'scp'])
        ->assertRedirect('/scp/queues');

    expect(StaffPreference::forStaff($staff->staff_id)->last_active_panel)->toBe('scp');
});

test('bogus panel requests are rejected with validation errors', function (): void {
    $staff = makePanelSwitchStaff();

    actingAs($staff, 'staff');

    postJson(route('panel.switch'), ['panel' => 'bogus'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['panel']);
});

test('guests are redirected to the login page', function (): void {
    post(route('panel.switch'), ['panel' => 'scp'])
        ->assertRedirect(route('scp.login'));
});
