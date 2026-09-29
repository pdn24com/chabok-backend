<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_task_assignment_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('task_id');
            $table->enum('event_type', ['CREATE', 'REFER', 'CLAIM', 'REASSIGN', 'MEMBERSHIP_CHANGE']);
            $table->unsignedInteger('from_team_id')->nullable();
            // Explicit historical context only; never inferred from a multi-team assignee.
            $table->unsignedInteger('to_team_id')->nullable();
            $table->unsignedInteger('from_user_id')->nullable();
            // null means no owner, not a team queue.
            $table->unsignedInteger('to_user_id')->nullable();
            $table->unsignedInteger('actor_id');
            // Required for REFER, REASSIGN and MEMBERSHIP_CHANGE.
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->string('operation_key', 120);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['hq_id', 'task_id', 'operation_key'], 'crm_task_assignment_events_operation_unique');
            $table->unique(['hq_id', 'id'], 'crm_task_assignment_events_hq_internal_id_unique');
            $table->index(['hq_id', 'task_id', 'occurred_at'], 'crm_task_assignment_events_timeline_index');
            $table->index(['hq_id', 'from_team_id'], 'crm_task_assignment_events_from_team_fk');
            $table->index(['hq_id', 'to_team_id'], 'crm_task_assignment_events_to_team_fk');
            $table->index(['from_user_id'], 'crm_task_assignment_events_from_user_fk');
            $table->index(['to_user_id'], 'crm_task_assignment_events_to_user_fk');
            $table->index(['actor_id'], 'crm_task_assignment_events_actor_fk');
            $table->index(['created_by'], 'crm_task_assignment_events_created_by_fk');
            $table->foreign(['actor_id'], 'crm_task_assignment_events_actor_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'crm_task_assignment_events_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['from_user_id'], 'crm_task_assignment_events_from_user_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'from_team_id'], 'crm_task_assignment_events_from_team_fk')->references(['hq_id', 'id'])->on('crm_teams')->onDelete('restrict');
            $table->foreign(['hq_id', 'task_id'], 'crm_task_assignment_events_task_fk')->references(['hq_id', 'id'])->on('crm_tasks')->onDelete('restrict');
            $table->foreign(['hq_id', 'to_team_id'], 'crm_task_assignment_events_to_team_fk')->references(['hq_id', 'id'])->on('crm_teams')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_task_assignment_events_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['to_user_id'], 'crm_task_assignment_events_to_user_fk')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_task_assignment_events');
    }
};
