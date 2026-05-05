<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

final class EnsureOsticket2SupportTables extends Command
{
    protected $signature = 'osticket2:ensure-support-tables';

    protected $description = 'Create or backfill app support tables on the configured osticket2 connection.';

    public function handle(): int
    {
        $this->prepareSqliteDatabase();

        $schema = Schema::connection('osticket2');

        $this->ensureStaffTwoFactor($schema);
        $this->ensureStaffAuthMigrations($schema);
        $this->ensureStaffPreferences($schema);
        $this->ensureAccessLog($schema);
        $this->ensureAdminAuditLog($schema);
        $this->ensureMigrationProgress($schema);

        $this->info('osticket2 support tables are ready.');

        return self::SUCCESS;
    }

    private function prepareSqliteDatabase(): void
    {
        if (config('database.connections.osticket2.driver') !== 'sqlite') {
            return;
        }

        $database = config('database.connections.osticket2.database');

        if (! is_string($database) || $database === '' || $database === ':memory:' || file_exists($database)) {
            return;
        }

        $directory = dirname($database);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        touch($database);
    }

    private function ensureStaffTwoFactor(Builder $schema): void
    {
        if (! $schema->hasTable('staff_two_factor')) {
            $schema->create('staff_two_factor', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        $this->guardRequiredColumns($schema, 'staff_two_factor', ['id', 'staff_id']);
        $this->addMissingColumns($schema, 'staff_two_factor', [
            'two_factor_secret' => fn (Blueprint $table) => $table->text('two_factor_secret')->nullable(),
            'two_factor_recovery_codes' => fn (Blueprint $table) => $table->text('two_factor_recovery_codes')->nullable(),
            'two_factor_confirmed_at' => fn (Blueprint $table) => $table->timestamp('two_factor_confirmed_at')->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ]);
    }

    private function ensureStaffAuthMigrations(Builder $schema): void
    {
        if (! $schema->hasTable('staff_auth_migrations')) {
            $schema->create('staff_auth_migrations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->timestamp('migrated_at')->nullable();
                $table->timestamp('must_upgrade_after')->nullable();
                $table->string('upgrade_method', 32)->nullable();
                $table->timestamp('dismissed_migration_banner_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        $this->guardRequiredColumns($schema, 'staff_auth_migrations', ['id', 'staff_id']);
        $this->addMissingColumns($schema, 'staff_auth_migrations', [
            'migrated_at' => fn (Blueprint $table) => $table->timestamp('migrated_at')->nullable(),
            'must_upgrade_after' => fn (Blueprint $table) => $table->timestamp('must_upgrade_after')->nullable(),
            'upgrade_method' => fn (Blueprint $table) => $table->string('upgrade_method', 32)->nullable(),
            'dismissed_migration_banner_at' => fn (Blueprint $table) => $table->timestamp('dismissed_migration_banner_at')->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ]);
    }

    private function ensureStaffPreferences(Builder $schema): void
    {
        if (! $schema->hasTable('staff_preferences')) {
            $schema->create('staff_preferences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('staff_id')->unique();
                $table->string('theme', 16)->default('system');
                $table->string('language', 16)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->json('notifications')->nullable();
                $table->string('last_active_panel', 16)->default('scp');
                $table->string('default_scp_tab', 64)->nullable();
                $table->string('default_admin_tab', 64)->nullable();
                $table->boolean('created_by_migration_2026_04_26_000100')->default(true);
                $table->boolean('created_by_migration_2026_05_02_000100')->default(true);
                $table->timestamps();
            });

            return;
        }

        $this->guardRequiredColumns($schema, 'staff_preferences', ['id', 'staff_id']);
        $this->addMissingColumns($schema, 'staff_preferences', [
            'theme' => fn (Blueprint $table) => $table->string('theme', 16)->default('system'),
            'language' => fn (Blueprint $table) => $table->string('language', 16)->nullable(),
            'timezone' => fn (Blueprint $table) => $table->string('timezone', 64)->nullable(),
            'notifications' => fn (Blueprint $table) => $table->json('notifications')->nullable(),
            'last_active_panel' => fn (Blueprint $table) => $table->string('last_active_panel', 16)->default('scp'),
            'default_scp_tab' => fn (Blueprint $table) => $table->string('default_scp_tab', 64)->nullable(),
            'default_admin_tab' => fn (Blueprint $table) => $table->string('default_admin_tab', 64)->nullable(),
            'created_at' => fn (Blueprint $table) => $table->timestamp('created_at')->nullable(),
            'updated_at' => fn (Blueprint $table) => $table->timestamp('updated_at')->nullable(),
        ]);
    }

    private function ensureAccessLog(Builder $schema): void
    {
        if (! $schema->hasTable('access_log')) {
            $schema->create('access_log', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('staff_id')->index();
                $table->string('action', 128);
                $table->string('subject_type', 64)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('created_by_migration_2026_04_26_000200')->default(true);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['subject_type', 'subject_id']);
                $table->index('created_at');
            });

            return;
        }

        $this->guardRequiredColumns($schema, 'access_log', ['id', 'staff_id', 'action', 'created_at']);
        $this->addMissingColumns($schema, 'access_log', [
            'subject_type' => fn (Blueprint $table) => $table->string('subject_type', 64)->nullable(),
            'subject_id' => fn (Blueprint $table) => $table->unsignedBigInteger('subject_id')->nullable(),
            'metadata' => fn (Blueprint $table) => $table->json('metadata')->nullable(),
            'ip_address' => fn (Blueprint $table) => $table->string('ip_address', 45)->nullable(),
            'user_agent' => fn (Blueprint $table) => $table->text('user_agent')->nullable(),
        ]);
    }

    private function ensureAdminAuditLog(Builder $schema): void
    {
        if (! $schema->hasTable('admin_audit_log')) {
            $schema->create('admin_audit_log', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('actor_id');
                $table->string('action', 64);
                $table->string('subject_type', 64);
                $table->unsignedBigInteger('subject_id');
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['subject_type', 'subject_id', 'created_at'], 'admin_audit_log_subject_created_idx');
                $table->index(['actor_id', 'created_at'], 'admin_audit_log_actor_created_idx');
                $table->index(['action', 'created_at'], 'admin_audit_log_action_created_idx');
            });

            return;
        }

        $this->guardRequiredColumns($schema, 'admin_audit_log', ['id', 'actor_id', 'action', 'subject_type', 'subject_id', 'created_at']);
    }

    private function ensureMigrationProgress(Builder $schema): void
    {
        if ($schema->hasTable('_migration_progress')) {
            $this->guardRequiredColumns($schema, '_migration_progress', ['table_name', 'status']);

            return;
        }

        $schema->create('_migration_progress', function (Blueprint $table): void {
            $table->string('table_name', 120)->primary();
            $table->string('last_id', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param  array<string, callable(Blueprint): mixed>  $columns
     */
    private function addMissingColumns(Builder $schema, string $tableName, array $columns): void
    {
        $existingColumns = $schema->getColumnListing($tableName);
        $missingColumns = array_diff(array_keys($columns), $existingColumns);

        if ($missingColumns === []) {
            return;
        }

        $schema->table($tableName, function (Blueprint $table) use ($columns, $missingColumns): void {
            foreach ($missingColumns as $column) {
                $columns[$column]($table);
            }
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
