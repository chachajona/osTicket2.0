<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'action_log';

    private const CREATED_MARKER = 'created_by_migration_2026_05_03_000100';

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
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('ticket_id')->nullable();
            $table->unsignedInteger('thread_id')->nullable();
            $table->unsignedInteger('queue_id')->nullable();
            $table->string('action', 64);
            $table->string('outcome', 16);
            $table->unsignedSmallInteger('http_status');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->unsignedInteger('lock_id')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean(self::CREATED_MARKER)->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['staff_id', 'created_at']);
            $table->index(['ticket_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    private function guardRequiredColumns(Builder $schema): void
    {
        $existingColumns = $schema->getColumnListing(self::TABLE);
        $missingColumns = array_values(array_diff([
            'id',
            'staff_id',
            'ticket_id',
            'thread_id',
            'queue_id',
            'action',
            'outcome',
            'http_status',
            'before_state',
            'after_state',
            'lock_id',
            'request_id',
            'ip_address',
            'user_agent',
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
