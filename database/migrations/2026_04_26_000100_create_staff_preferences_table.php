<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'staff_preferences';

    private const CREATED_MARKER = 'created_by_migration_2026_04_26_000100';

    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if (! $schema->hasTable(self::TABLE)) {
            $this->createTable($schema);

            return;
        }

        $this->guardRequiredColumns($schema);
    }

    public function down(): void
    {
        $schema = Schema::connection('osticket2');

        if ($schema->hasColumn(self::TABLE, self::CREATED_MARKER)) {
            $schema->dropIfExists(self::TABLE);
        }
    }

    private function createTable(Builder $schema): void
    {
        $schema->create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->string('theme', 16)->default('system');
            $table->string('language', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('notifications')->nullable();
            $table->boolean(self::CREATED_MARKER)->default(true);
            $table->timestamps();
        });
    }

    private function guardRequiredColumns(Builder $schema): void
    {
        $existingColumns = $schema->getColumnListing(self::TABLE);
        $missingColumns = array_values(array_diff(['id', 'staff_id'], $existingColumns));

        if ($missingColumns === []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Existing %s table is missing required column(s): %s.',
            $schema->getConnection()->getTablePrefix().self::TABLE,
            implode(', ', $missingColumns),
        ));
    }
};
