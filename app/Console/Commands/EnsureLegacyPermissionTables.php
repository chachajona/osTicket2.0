<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

final class EnsureLegacyPermissionTables extends Command
{
    protected $signature = 'legacy:ensure-permission-tables {--seed : Seed the application permission catalog}';

    protected $description = 'Create missing Spatie permission tables on the configured legacy permission connection.';

    public function handle(): int
    {
        $this->prepareSqliteDatabase();

        $schema = Schema::connection(config('permission.connection', 'legacy'));
        $tables = config('permission.table_names', []);
        $columns = config('permission.column_names', []);

        $this->ensureTableConfig($tables);

        $pivotRole = $columns['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columns['permission_pivot_key'] ?? 'permission_id';
        $modelMorphKey = $columns['model_morph_key'] ?? 'model_id';

        $this->ensurePermissions($schema, $tables['permissions']);
        $this->ensureRoles($schema, $tables['roles']);
        $this->ensureModelHasPermissions($schema, $tables['model_has_permissions'], $pivotPermission, $modelMorphKey);
        $this->ensureModelHasRoles($schema, $tables['model_has_roles'], $pivotRole, $modelMorphKey);
        $this->ensureRoleHasPermissions($schema, $tables['role_has_permissions'], $pivotPermission, $pivotRole);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($this->option('seed')) {
            $this->call('db:seed', ['--class' => PermissionCatalogSeeder::class, '--force' => true]);
        }

        $this->info('Legacy permission tables are ready.');

        return self::SUCCESS;
    }

    private function prepareSqliteDatabase(): void
    {
        $connection = (string) config('permission.connection', 'legacy');

        $this->prepareSqliteConnection(config('database.default'));
        $this->prepareSqliteConnection($connection);
    }

    private function prepareSqliteConnection(?string $connection): void
    {
        if (! is_string($connection) || $connection === '') {
            return;
        }

        if (config("database.connections.{$connection}.driver") !== 'sqlite') {
            return;
        }

        $database = config("database.connections.{$connection}.database");

        if (! is_string($database) || $database === '' || $database === ':memory:' || file_exists($database)) {
            return;
        }

        $directory = dirname($database);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        touch($database);
    }

    /**
     * @param  array<string, string>  $tables
     */
    private function ensureTableConfig(array $tables): void
    {
        $required = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];
        $missing = array_values(array_filter($required, fn (string $key): bool => empty($tables[$key])));

        if ($missing !== []) {
            throw new \RuntimeException('Missing permission table config keys: '.implode(', ', $missing));
        }
    }

    private function ensurePermissions(Builder $schema, string $tableName): void
    {
        if ($schema->hasTable($tableName)) {
            $this->guardRequiredColumns($schema, $tableName, ['id', 'name', 'guard_name']);

            return;
        }

        $schema->create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    private function ensureRoles(Builder $schema, string $tableName): void
    {
        if ($schema->hasTable($tableName)) {
            $this->guardRequiredColumns($schema, $tableName, ['id', 'name', 'guard_name']);

            return;
        }

        $schema->create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    private function ensureModelHasPermissions(Builder $schema, string $tableName, string $pivotPermission, string $modelMorphKey): void
    {
        if ($schema->hasTable($tableName)) {
            $this->guardRequiredColumns($schema, $tableName, [$pivotPermission, $modelMorphKey, 'model_type']);

            return;
        }

        $schema->create($tableName, function (Blueprint $table) use ($pivotPermission, $modelMorphKey): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary([$pivotPermission, $modelMorphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
    }

    private function ensureModelHasRoles(Builder $schema, string $tableName, string $pivotRole, string $modelMorphKey): void
    {
        if ($schema->hasTable($tableName)) {
            $this->guardRequiredColumns($schema, $tableName, [$pivotRole, $modelMorphKey, 'model_type']);

            return;
        }

        $schema->create($tableName, function (Blueprint $table) use ($pivotRole, $modelMorphKey): void {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary([$pivotRole, $modelMorphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }

    private function ensureRoleHasPermissions(Builder $schema, string $tableName, string $pivotPermission, string $pivotRole): void
    {
        if ($schema->hasTable($tableName)) {
            $this->guardRequiredColumns($schema, $tableName, [$pivotPermission, $pivotRole]);

            return;
        }

        $schema->create($tableName, function (Blueprint $table) use ($pivotPermission, $pivotRole): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    /**
     * @param  list<string>  $requiredColumns
     */
    private function guardRequiredColumns(Builder $schema, string $tableName, array $requiredColumns): void
    {
        $existingColumns = $schema->getColumnListing($tableName);
        $missingColumns = array_values(array_diff($requiredColumns, $existingColumns));

        if ($missingColumns === []) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'Existing %s table is missing required column(s): %s.',
            $schema->getConnection()->getTablePrefix().$tableName,
            implode(', ', $missingColumns),
        ));
    }
}
