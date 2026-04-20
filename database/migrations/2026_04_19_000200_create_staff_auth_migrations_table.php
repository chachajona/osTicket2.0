<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if ($schema->hasTable('staff_auth_migrations')) {
            return;
        }

        $schema->create('staff_auth_migrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->timestamp('migrated_at')->nullable();
            $table->timestamp('must_upgrade_after')->nullable();
            $table->string('upgrade_method', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // The osTicket-side table may already exist before this migration runs.
        // Rolling back should not drop shared legacy data.
    }
};
