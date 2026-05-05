<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Admin\AdminAuditLog;
use App\Models\Staff;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\SkippedWithMessageException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Characterization', 'Feature', 'Unit');

function inertiaHeaders(): array
{
    $headers = [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ];

    $version = app(HandleInertiaRequests::class)->version(request());

    if ($version !== null) {
        $headers['X-Inertia-Version'] = $version;
    }

    return $headers;
}

function skipIfLegacyTablesMissing(array $tables): void
{
    $schema = Schema::connection('legacy');
    $missing = array_values(array_filter(
        array_unique($tables),
        fn (string $table): bool => ! $schema->hasTable($table)
    ));

    if ($missing !== []) {
        throw new SkippedWithMessageException(
            'Legacy SQLite fixture is missing required tables: '.implode(', ', $missing)
        );
    }
}

function skipIfLegacyColumnsMissing(string $table, array $columns): void
{
    skipIfLegacyTablesMissing([$table]);

    $schema = Schema::connection('legacy');
    $missing = array_values(array_filter(
        array_unique($columns),
        fn (string $column): bool => ! $schema->hasColumn($table, $column)
    ));

    if ($missing !== []) {
        throw new SkippedWithMessageException(
            "Legacy SQLite fixture is missing required columns on {$table}: ".implode(', ', $missing)
        );
    }
}

function seedPermissions(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(PermissionCatalogSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function actingAsAdmin(?Staff $staff = null): Staff
{
    seedPermissions();

    $staff ??= Staff::factory()->create([
        'isactive' => 1,
        'isadmin' => 1,
    ]);

    if (! $staff->exists) {
        $staff->isactive = 1;
        $staff->isadmin = 1;
        $staff->save();
    } else {
        $staff->forceFill([
            'isactive' => 1,
            'isadmin' => 1,
        ])->save();
    }

    $staff->givePermissionTo('admin.access');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->actingAs($staff->fresh(), 'staff');

    return $staff->fresh();
}

function actingAsAgent(?Staff $staff = null): Staff
{
    seedPermissions();

    $staff ??= Staff::factory()->create([
        'isactive' => 1,
        'isadmin' => 0,
    ]);

    if (! $staff->exists) {
        $staff->isactive = 1;
        $staff->isadmin = 0;
        $staff->save();
    } else {
        $staff->forceFill([
            'isactive' => 1,
            'isadmin' => 0,
        ])->save();
    }

    $staff->syncRoles([]);
    $staff->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    test()->actingAs($staff->fresh(), 'staff');

    return $staff->fresh();
}

function assertAuditLogged(string $action, Model $subject, ?array $before = null, ?array $after = null): AdminAuditLog
{
    $log = AdminAuditLog::query()
        ->where('action', $action)
        ->where('subject_type', class_basename($subject))
        ->where('subject_id', (int) $subject->getKey())
        ->latest('id')
        ->first();

    test()->assertNotNull($log, sprintf(
        'Expected an admin audit log for action [%s] and subject [%s:%s].',
        $action,
        class_basename($subject),
        (string) $subject->getKey(),
    ));

    test()->assertSame($before, $log->before);
    test()->assertSame($after, $log->after);

    return $log;
}
