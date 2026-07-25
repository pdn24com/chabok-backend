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
            $table->char('event_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->string('aggregate_type', 120);
            $table->char('aggregate_id', 36);
            $table->string('event_type', 180);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->json('payload');
            $table->char('correlation_id', 36);
            $table->char('causation_id', 36)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->enum('publication_state', ['PENDING', 'PUBLISHED', 'FAILED'])
                ->default('PENDING');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->string('last_failure_code', 120)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->index(
                ['publication_state', 'next_attempt_at', 'occurred_at'],
                'outbox_pending_index',
            );
            $table->index(['hq_id', 'aggregate_type', 'aggregate_id'], 'outbox_aggregate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
