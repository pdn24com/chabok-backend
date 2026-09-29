<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->enum('type', ['CALL', 'MESSAGE', 'MEETING', 'DOCUMENT_SENT', 'REFERRAL', 'NOTE']);
            $table->timestamp('occurred_at', 6);
            $table->unsignedInteger('opportunity_id')->nullable();
            $table->unsignedInteger('task_id')->nullable();
            // An internal task action need not carry a customer.
            $table->unsignedInteger('customer_id')->nullable();
            $table->text('body')->nullable();
            $table->text('result')->nullable();
            // Contacted person, PERSON only, independent of any relationship table.
            $table->unsignedInteger('contact_customer_id')->nullable();
            $table->enum('direction', ['INBOUND', 'OUTBOUND'])->nullable();
            // Snapshot of the phone, email or handle actually used.
            $table->string('contact_value', 320)->nullable();
            $table->enum('channel', ['PHONE', 'EMAIL', 'SMS', 'WHATSAPP', 'TELEGRAM', 'BALE', 'INSTAGRAM', 'IN_PERSON', 'POST'])->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->enum('call_outcome', ['ANSWERED', 'NO_ANSWER', 'BUSY', 'INVALID_NUMBER'])->nullable();
            $table->enum('meeting_mode', ['IN_PERSON', 'ONLINE'])->nullable();
            $table->string('location', 300)->nullable();
            $table->string('meeting_url', 500)->nullable();
            // Logical reference to a future document_versions table; ERD v14 leaves it without a physical key.
            $table->unsignedInteger('document_version_id')->nullable();
            // Real evidence behind a REFERRAL action.
            $table->unsignedInteger('assignment_event_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_activities_hq_internal_id_unique');
            $table->index(['hq_id', 'opportunity_id', 'occurred_at'], 'crm_activities_opportunity_timeline_index');
            $table->index(['hq_id', 'task_id', 'occurred_at'], 'crm_activities_task_timeline_index');
            $table->index(['hq_id', 'customer_id', 'occurred_at'], 'crm_activities_customer_timeline_index');
            $table->index(['hq_id', 'type', 'occurred_at'], 'crm_activities_type_timeline_index');
            $table->index(['hq_id', 'contact_customer_id'], 'crm_activities_contact_customer_fk');
            $table->index(['hq_id', 'assignment_event_id'], 'crm_activities_assignment_event_fk');
            $table->index(['created_by'], 'crm_activities_created_by_fk');
            $table->index(['updated_by'], 'crm_activities_updated_by_fk');
            $table->foreign(['created_by'], 'crm_activities_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'assignment_event_id'], 'crm_activities_assignment_event_fk')->references(['hq_id', 'id'])->on('crm_task_assignment_events')->onDelete('restrict');
            $table->foreign(['hq_id', 'contact_customer_id'], 'crm_activities_contact_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_activities_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'opportunity_id'], 'crm_activities_opportunity_fk')->references(['hq_id', 'id'])->on('crm_opportunities')->onDelete('restrict');
            $table->foreign(['hq_id', 'task_id'], 'crm_activities_task_fk')->references(['hq_id', 'id'])->on('crm_tasks')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_activities_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['updated_by'], 'crm_activities_updated_by_fk')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
