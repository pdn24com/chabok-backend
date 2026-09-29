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
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('event_id');
            $table->enum('channel', ['SMS', 'EMAIL']);
            $table->char('recipient_fingerprint', 64);
            $table->string('template_code', 120);
            $table->string('provider_code', 80);
            $table->char('provider_message_id', 64);
            $table->enum('status', ['SENT', 'FAILED']);
            $table->string('correlation_id', 64);
            $table->timestamp('sent_at', 6)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['event_id'], 'notification_deliveries_event_id_public_unique');
            $table->unique(['provider_message_id'], 'provider_message_id_public_unique');
            $table->index(['hq_id', 'channel', 'created_at'], 'notification_tenant_channel_index');
            $table->foreign(['event_id'], 'notification_deliveries_event_id_foreign')->references(['id'])->on('outbox_events')->onDelete('restrict');
            $table->foreign(['hq_id'], 'notification_deliveries_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
