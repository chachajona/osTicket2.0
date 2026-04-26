<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if (! $schema->hasTable('staff_auth_migrations')) {
            return;
        }

        if ($schema->hasColumn('staff_auth_migrations', 'dismissed_migration_banner_at')) {
            return;
        }

        $schema->table('staff_auth_migrations', function (Blueprint $table): void {
            $table->timestamp('dismissed_migration_banner_at')->nullable()->after('upgrade_method');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('osticket2');

        if (! $schema->hasTable('staff_auth_migrations')) {
            return;
        }

        if (! $schema->hasColumn('staff_auth_migrations', 'dismissed_migration_banner_at')) {
            return;
        }

        $schema->table('staff_auth_migrations', function (Blueprint $table): void {
            $table->dropColumn('dismissed_migration_banner_at');
        });
    }
};
