<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function loadMigration(string $filename): object
{
    return require database_path("migrations/{$filename}");
}

function recreateOsticket2Table(string $table, Closure $definition): void
{
    $schema = Schema::connection('osticket2');

    $schema->dropIfExists($table);
    $schema->create($table, $definition);
}

test('staff two factor migration backfills missing optional columns on an existing shared table', function () {
    recreateOsticket2Table('staff_two_factor', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('staff_id');
    });

    loadMigration('2026_04_19_000100_create_staff_two_factor_table.php')->up();

    $schema = Schema::connection('osticket2');

    expect($schema->hasColumns('staff_two_factor', [
        'id',
        'staff_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and($schema->hasIndex('staff_two_factor', ['staff_id'], 'unique'))->toBeTrue();
});

test('staff auth migrations migration backfills missing optional columns on an existing shared table', function () {
    recreateOsticket2Table('staff_auth_migrations', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('staff_id');
    });

    loadMigration('2026_04_19_000200_create_staff_auth_migrations_table.php')->up();
    loadMigration('2026_04_22_140000_add_dismissed_migration_banner_at_to_staff_auth_migrations.php')->up();

    $schema = Schema::connection('osticket2');

    expect($schema->hasColumns('staff_auth_migrations', [
        'id',
        'staff_id',
        'migrated_at',
        'must_upgrade_after',
        'upgrade_method',
        'dismissed_migration_banner_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and($schema->hasIndex('staff_auth_migrations', ['staff_id'], 'unique'))->toBeTrue();
});

test('staff two factor migration fails loudly when the shared table is missing core columns', function () {
    recreateOsticket2Table('staff_two_factor', function (Blueprint $table): void {
        $table->unsignedBigInteger('staff_id');
    });

    try {
        expect(fn () => loadMigration('2026_04_19_000100_create_staff_two_factor_table.php')->up())
            ->toThrow(RuntimeException::class, 'Existing scp_staff_two_factor table is missing required column(s): id.');
    } finally {
        Schema::connection('osticket2')->dropIfExists('staff_two_factor');
        loadMigration('2026_04_19_000100_create_staff_two_factor_table.php')->up();
    }
});
