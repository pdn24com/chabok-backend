<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// In-app notification inbox. For a task reminder the recipient is the assignee current at creation time and
// is frozen here, so a later reassignment never rewrites the historical recipient; other notifications may
// carry a recipient of their own. The time source stays crm_tasks.remind_at; crm_reminders does not return.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notification_inbox', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('recipient_id');
            $table->string('event_key', 200);
            // Permission on the resource is checked when the notification is opened, not when it is stored,
            // and the title must stay free of sensitive detail.
            $table->string('resource_type', 80);
            $table->unsignedInteger('resource_id');
            $table->timestamp('read_at', 6)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'recipient_id', 'event_key'], 'crm_notification_inbox_recipient_event_unique');
            $table->index(['hq_id', 'recipient_id', 'read_at'], 'crm_notification_inbox_recipient_unread_index');
            $table->index(['hq_id', 'resource_type', 'resource_id'], 'crm_notification_inbox_resource_index');
            $table->index(['recipient_id'], 'crm_notification_inbox_recipient_fk');
            $table->index(['created_by'], 'crm_notification_inbox_created_by_fk');
            $table->foreign(['created_by'], 'crm_notification_inbox_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['recipient_id'], 'crm_notification_inbox_recipient_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_notification_inbox_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notification_inbox');
    }
};
