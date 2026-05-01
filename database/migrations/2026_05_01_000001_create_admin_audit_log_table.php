<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('osticket2')->create('admin_audit_log', function (Blueprint $table): void {
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
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'admin_audit_log_subject_created_idx');
            $table->index(['actor_id', 'created_at'], 'admin_audit_log_actor_created_idx');
            $table->index(['action', 'created_at'], 'admin_audit_log_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('osticket2')->dropIfExists('admin_audit_log');
    }
};
