<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if (! $schema->hasTable('staff_auth_migrations')) {
            $this->createTable($schema);

            return;
        }

        $this->guardRequiredColumns($schema, 'staff_auth_migrations', ['id', 'staff_id']);
        $this->backfillOptionalColumns($schema);
    }

    public function down(): void
    {
        // The osTicket-side table may already exist before this migration runs.
        // Rolling back should not drop shared legacy data.
    }

    private function createTable(Builder $schema): void
    {
        $schema->create('staff_auth_migrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->timestamp('migrated_at')->nullable();
            $table->timestamp('must_upgrade_after')->nullable();
            $table->string('upgrade_method', 32)->nullable();
            $table->timestamps();
        });
    }

    private function guardRequiredColumns(Builder $schema, string $table, array $requiredColumns): void
    {
        $existingColumns = $schema->getColumnListing($table);
        $missingColumns = array_values(array_diff($requiredColumns, $existingColumns));

        if ($missingColumns === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Existing %s table is missing required column(s): %s.',
            $schema->getConnection()->getTablePrefix().$table,
            implode(', ', $missingColumns),
        ));
    }

    private function backfillOptionalColumns(Builder $schema): void
    {
        $table = 'staff_auth_migrations';
        $existingColumns = $schema->getColumnListing($table);
        $needsUniqueIndex = ! $schema->hasIndex($table, ['staff_id'], 'unique');

        if (
            in_array('migrated_at', $existingColumns, true) &&
            in_array('must_upgrade_after', $existingColumns, true) &&
            in_array('upgrade_method', $existingColumns, true) &&
            in_array('created_at', $existingColumns, true) &&
            in_array('updated_at', $existingColumns, true) &&
            ! $needsUniqueIndex
        ) {
            return;
        }

        $schema->table($table, function (Blueprint $table) use ($existingColumns, $needsUniqueIndex): void {
            if (! in_array('migrated_at', $existingColumns, true)) {
                $table->timestamp('migrated_at')->nullable();
            }

            if (! in_array('must_upgrade_after', $existingColumns, true)) {
                $table->timestamp('must_upgrade_after')->nullable();
            }

            if (! in_array('upgrade_method', $existingColumns, true)) {
                $table->string('upgrade_method', 32)->nullable();
            }

            if (! in_array('created_at', $existingColumns, true)) {
                $table->timestamp('created_at')->nullable();
            }

            if (! in_array('updated_at', $existingColumns, true)) {
                $table->timestamp('updated_at')->nullable();
            }

            if ($needsUniqueIndex) {
                $table->unique('staff_id');
            }
        });
    }
};
