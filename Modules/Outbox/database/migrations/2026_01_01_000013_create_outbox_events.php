<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('aggregate_type', 120);
            $table->unsignedInteger('aggregate_id');
            $table->string('event_type', 180);
            $table->unsignedSmallInteger('event_version')->default('1');
            $table->json('payload');
            $table->string('correlation_id', 64);
            $table->string('causation_id', 64)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->enum('publication_state', ['PENDING', 'PUBLISHED', 'FAILED', 'DEAD_LETTER'])->default('PENDING');
            $table->string('claim_token', 64)->nullable();
            $table->string('claimed_by', 120)->nullable();
            $table->timestamp('claimed_at', 6)->nullable();
            $table->unsignedInteger('attempts')->default('0');
            $table->timestamp('last_attempt_at', 6)->nullable();
            $table->timestamp('next_attempt_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamp('dead_lettered_at', 6)->nullable();
            $table->string('last_failure_code', 120)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['claim_token'], 'outbox_events_claim_token_unique');
            $table->index(['publication_state', 'next_attempt_at', 'occurred_at'], 'outbox_pending_index');
            $table->index(['hq_id', 'aggregate_type', 'aggregate_id'], 'outbox_aggregate_index');
            $table->index(['publication_state', 'claimed_at', 'next_attempt_at'], 'outbox_claimable_index');
            $table->foreign(['hq_id'], 'outbox_events_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
