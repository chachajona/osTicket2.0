<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if ($schema->hasTable('_migration_progress')) {
            $schema->table('_migration_progress', function (Blueprint $table) use ($schema): void {
                if (! $schema->hasColumn('_migration_progress', 'lock_owner')) {
                    $table->string('lock_owner', 128)->nullable()->index();
                }

                if (! $schema->hasColumn('_migration_progress', 'lock_expires_at')) {
                    $table->timestamp('lock_expires_at')->nullable()->index();
                }

                if (! $schema->hasColumn('_migration_progress', 'version')) {
                    $table->unsignedBigInteger('version')->default(0);
                }
            });

            return;
        }

        $schema->create('_migration_progress', function (Blueprint $table): void {
            $table->string('table_name', 120)->primary();
            $table->string('last_id', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('lock_owner', 128)->nullable()->index();
            $table->timestamp('lock_expires_at')->nullable()->index();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('osticket2')->dropIfExists('_migration_progress');
    }
};
