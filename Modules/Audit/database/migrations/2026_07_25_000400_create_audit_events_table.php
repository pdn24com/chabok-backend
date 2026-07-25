<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->char('audit_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->char('initiator_id', 36)->nullable();
            $table->string('action_key', 160);
            $table->string('target_type', 120);
            $table->char('target_id', 36)->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('safe_note', 500)->nullable();
            $table->char('ip_address_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->string('source_client', 80)->nullable();
            $table->char('correlation_id', 36);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('initiator_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(
                ['hq_id', 'target_type', 'target_id', 'created_at'],
                'audit_target_timeline_index',
            );
            $table->index(['initiator_id', 'created_at'], 'audit_initiator_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
