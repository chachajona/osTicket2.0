<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('osticket2')->create('staff_auth_migrations', function (Blueprint $table): void {
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
        Schema::connection('osticket2')->dropIfExists('staff_auth_migrations');
    }
};
