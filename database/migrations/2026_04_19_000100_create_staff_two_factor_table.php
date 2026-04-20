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

        if (! $schema->hasTable('staff_two_factor')) {
            $this->createTable($schema);

            return;
        }

        $this->guardRequiredColumns($schema, 'staff_two_factor', ['id', 'staff_id']);
        $this->backfillOptionalColumns($schema);
    }

    public function down(): void
    {
        // The osTicket-side table may already exist before this migration runs.
        // Rolling back should not drop shared legacy data.
    }

    private function createTable(Builder $schema): void
    {
        $schema->create('staff_two_factor', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
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
        $table = 'staff_two_factor';
        $existingColumns = $schema->getColumnListing($table);
        $needsUniqueIndex = ! $schema->hasIndex($table, ['staff_id'], 'unique');

        if (
            in_array('two_factor_secret', $existingColumns, true) &&
            in_array('two_factor_recovery_codes', $existingColumns, true) &&
            in_array('two_factor_confirmed_at', $existingColumns, true) &&
            in_array('created_at', $existingColumns, true) &&
            in_array('updated_at', $existingColumns, true) &&
            ! $needsUniqueIndex
        ) {
            return;
        }

        $schema->table($table, function (Blueprint $table) use ($existingColumns, $needsUniqueIndex): void {
            if (! in_array('two_factor_secret', $existingColumns, true)) {
                $table->text('two_factor_secret')->nullable();
            }

            if (! in_array('two_factor_recovery_codes', $existingColumns, true)) {
                $table->text('two_factor_recovery_codes')->nullable();
            }

            if (! in_array('two_factor_confirmed_at', $existingColumns, true)) {
                $table->timestamp('two_factor_confirmed_at')->nullable();
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
