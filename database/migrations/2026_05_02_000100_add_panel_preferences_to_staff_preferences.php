<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'staff_preferences';

    private const CREATED_MARKER = 'created_by_migration_2026_05_02_000100';

    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if (! $schema->hasTable(self::TABLE)) {
            return;
        }

        $this->guardRequiredColumns($schema);

        $schema->table(self::TABLE, function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn(self::TABLE, 'last_active_panel')) {
                $table->string('last_active_panel', 16)->default('scp');
            }
            if (! $schema->hasColumn(self::TABLE, 'default_scp_tab')) {
                $table->string('default_scp_tab', 64)->nullable();
            }
            if (! $schema->hasColumn(self::TABLE, 'default_admin_tab')) {
                $table->string('default_admin_tab', 64)->nullable();
            }
            if (! $schema->hasColumn(self::TABLE, self::CREATED_MARKER)) {
                $table->boolean(self::CREATED_MARKER)->default(true);
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('osticket2');

        if ($schema->hasColumn(self::TABLE, self::CREATED_MARKER)) {
            $schema->table(self::TABLE, function (Blueprint $table): void {
                $table->dropColumn(['last_active_panel', 'default_scp_tab', 'default_admin_tab', self::CREATED_MARKER]);
            });
        }
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
