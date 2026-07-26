<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->char('delivery_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->char('event_id', 36)->unique();
            $table->enum('channel', ['SMS', 'EMAIL']);
            $table->char('recipient_fingerprint', 64);
            $table->string('template_code', 120);
            $table->string('provider_code', 80);
            $table->char('provider_message_id', 64)->unique();
            $table->enum('status', ['SENT', 'FAILED']);
            $table->char('correlation_id', 36);
            $table->timestamp('sent_at', 6)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('event_id')->references('event_id')->on('outbox_events')->restrictOnDelete();
            $table->index(['hq_id', 'channel', 'created_at'], 'notification_tenant_channel_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
