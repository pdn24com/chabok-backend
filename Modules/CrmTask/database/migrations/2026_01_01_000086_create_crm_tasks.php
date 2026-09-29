<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('title', 200);
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'WAITING_CUSTOMER', 'COMPLETED', 'CANCELLED'])->default('OPEN');
            $table->enum('priority', ['VERY_HIGH', 'HIGH', 'MEDIUM', 'LOW', 'VERY_LOW'])->default('MEDIUM');
            $table->timestamp('due_at', 6)->nullable();
            // Current owner of the task; no implicit team selection stands in for an empty assignee.
            $table->unsignedInteger('assignee_id')->nullable();
            // null is permitted for internal work.
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('opportunity_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            // Required on completion, not on creation.
            $table->text('completion_result')->nullable();
            // A reminder time only, independent of due_at; delivery, retry and dedupe belong to Notification.
            $table->timestamp('remind_at', 6)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_tasks_hq_internal_id_unique');
            $table->index(['hq_id', 'assignee_id', 'status', 'due_at'], 'crm_tasks_assignee_queue_index');
            $table->index(['hq_id', 'status', 'due_at'], 'crm_tasks_hq_status_due_index');
            $table->index(['hq_id', 'customer_id'], 'crm_tasks_customer_fk');
            $table->index(['hq_id', 'opportunity_id'], 'crm_tasks_opportunity_fk');
            $table->index(['hq_id', 'remind_at'], 'crm_tasks_hq_remind_index');
            $table->index(['assignee_id'], 'crm_tasks_assignee_fk');
            $table->index(['created_by'], 'crm_tasks_created_by_fk');
            $table->foreign(['assignee_id'], 'crm_tasks_assignee_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'crm_tasks_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_tasks_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'opportunity_id'], 'crm_tasks_opportunity_fk')->references(['hq_id', 'id'])->on('crm_opportunities')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_tasks_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
