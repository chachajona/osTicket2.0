<?php

declare(strict_types=1);

use App\Models\Scp\StaffPreference;
use App\Models\Staff;
use App\Services\Panels\PanelLandingResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

function makePanelStaff(array $attributes = []): Staff
{
    return Staff::factory()->create(array_merge([
        'isactive' => 1,
        'isadmin' => 0,
    ], $attributes));
}

test('falls back to panel default when stored id unknown', function (): void {
    $staff = makePanelStaff(['isadmin' => 1]);

    StaffPreference::forStaff($staff->staff_id)->update([
        'default_admin_tab' => 'missing-tab',
    ]);

    $url = app(PanelLandingResolver::class)->resolve($staff->fresh(), 'admin');

    expect($url)->toBe('/admin/help-topics');
});

test('falls back to first enabled tab when default also missing', function (): void {
    $staff = makePanelStaff(['isadmin' => 1]);

    StaffPreference::forStaff($staff->staff_id)->update([
        'default_admin_tab' => 'missing-tab',
    ]);

    config()->set('panel_landing.admin.default', 'also-missing');

    $url = app(PanelLandingResolver::class)->resolve($staff->fresh(), 'admin');

    expect($url)->toBe('/admin/help-topics');
});

test('self heals corrupted last active panel for non admin staff', function (): void {
    $staff = makePanelStaff();

    StaffPreference::forStaff($staff->staff_id)->update([
        'last_active_panel' => 'admin',
        'default_scp_tab' => 'queues',
    ]);

    $url = app(PanelLandingResolver::class)->resolve($staff->fresh(), 'admin');

    expect($url)->toBe('/scp/queues')
        ->and(StaffPreference::forStaff($staff->staff_id)->last_active_panel)->toBe('scp');
});

test('admin config stays in sync with enabled admin tab ids from typescript', function (): void {
    $contents = file_get_contents(base_path('resources/js/components/admin/AdminTabs.ts'));

    expect($contents)->not->toBeFalse();

    preg_match_all("/id:\s*'([^']+)'[^\n]*enabled:\s*true/", (string) $contents, $matches);

    $enabledIds = array_values(array_unique($matches[1] ?? []));
    $configuredIds = array_keys(config('panel_landing.admin.tabs'));

    expect($enabledIds)->not->toBe([])
        ->and(array_diff($enabledIds, $configuredIds))->toBe([]);
});
