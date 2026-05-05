<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'admin_audit_log';

    private const CREATED_MARKER = 'created_by_migration_2026_05_01_000001';

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
            $table->boolean(self::CREATED_MARKER)->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'admin_audit_log_subject_created_idx');
            $table->index(['actor_id', 'created_at'], 'admin_audit_log_actor_created_idx');
            $table->index(['action', 'created_at'], 'admin_audit_log_action_created_idx');
        });
    }

    private function guardRequiredColumns(Builder $schema): void
    {
        $existingColumns = $schema->getColumnListing(self::TABLE);
        $missingColumns = array_values(array_diff([
            'id',
            'actor_id',
            'action',
            'subject_type',
            'subject_id',
            'created_at',
        ], $existingColumns));

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
