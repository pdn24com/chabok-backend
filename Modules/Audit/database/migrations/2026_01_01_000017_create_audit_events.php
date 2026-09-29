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
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('initiator_id')->nullable();
            $table->string('action_key', 160);
            $table->string('target_type', 120);
            $table->unsignedInteger('target_id')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->string('safe_note', 500)->nullable();
            $table->char('ip_address_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->string('source_client', 80)->nullable();
            $table->string('correlation_id', 64);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['hq_id', 'target_type', 'target_id', 'created_at'], 'audit_target_timeline_index');
            $table->index(['initiator_id', 'created_at'], 'audit_initiator_index');
            $table->foreign(['hq_id'], 'audit_events_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['initiator_id'], 'audit_events_initiator_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
