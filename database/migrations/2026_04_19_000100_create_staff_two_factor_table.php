<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('osticket2');

        if ($schema->hasTable('staff_two_factor')) {
            return;
        }

        $schema->create('staff_two_factor', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('staff_id')->unique();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // The osTicket-side table may already exist before this migration runs.
        // Rolling back should not drop shared legacy data.
    }
};
