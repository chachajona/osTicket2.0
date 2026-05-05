<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scp_action_log', function (Blueprint $table): void {
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
            $table->timestamp('created_at')->useCurrent();

            $table->index(['staff_id', 'created_at']);
            $table->index(['ticket_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scp_action_log');
    }
};
