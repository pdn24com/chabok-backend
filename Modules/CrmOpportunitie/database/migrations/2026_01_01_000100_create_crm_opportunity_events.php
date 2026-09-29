<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunity_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('opportunity_id');
            $table->unsignedInteger('actor_id');
            $table->timestamp('occurred_at', 6);
            $table->text('reason')->nullable();
            // Acceptance or business evidence for the transition.
            $table->unsignedInteger('evidence_activity_id')->nullable();
            // The whole from_* group is null on the very first entry and complete on every later move.
            $table->unsignedInteger('from_funnel_id')->nullable();
            $table->unsignedInteger('from_step_id')->nullable();
            $table->string('from_funnel_code', 80)->nullable();
            $table->string('from_funnel_title', 200)->nullable();
            $table->string('from_step_code', 80)->nullable();
            $table->string('from_step_title', 200)->nullable();
            $table->enum('from_outcome_type', ['OPEN', 'WON', 'LOST'])->nullable();
            $table->unsignedInteger('to_funnel_id');
            $table->unsignedInteger('to_step_id');
            $table->string('to_funnel_code', 80);
            $table->string('to_funnel_title', 200);
            $table->string('to_step_code', 80);
            $table->string('to_step_title', 200);
            $table->enum('to_outcome_type', ['OPEN', 'WON', 'LOST']);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['hq_id', 'opportunity_id', 'occurred_at'], 'crm_opportunity_events_timeline_index');
            $table->index(['hq_id', 'from_funnel_id'], 'crm_opportunity_events_from_funnel_fk');
            $table->index(['hq_id', 'from_step_id'], 'crm_opportunity_events_from_step_fk');
            $table->index(['hq_id', 'to_funnel_id'], 'crm_opportunity_events_to_funnel_fk');
            $table->index(['hq_id', 'to_step_id'], 'crm_opportunity_events_to_step_fk');
            $table->index(['hq_id', 'evidence_activity_id'], 'crm_opportunity_events_evidence_fk');
            $table->index(['actor_id'], 'crm_opportunity_events_actor_fk');
            $table->index(['created_by'], 'crm_opportunity_events_created_by_fk');
            $table->foreign(['actor_id'], 'crm_opportunity_events_actor_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'crm_opportunity_events_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'evidence_activity_id'], 'crm_opportunity_events_evidence_fk')->references(['hq_id', 'id'])->on('crm_activities')->onDelete('restrict');
            $table->foreign(['hq_id', 'from_funnel_id'], 'crm_opportunity_events_from_funnel_fk')->references(['hq_id', 'id'])->on('crm_sales_funnel')->onDelete('restrict');
            $table->foreign(['hq_id', 'from_step_id'], 'crm_opportunity_events_from_step_fk')->references(['hq_id', 'id'])->on('crm_sales_funnel_steps')->onDelete('restrict');
            $table->foreign(['hq_id', 'opportunity_id'], 'crm_opportunity_events_opportunity_fk')->references(['hq_id', 'id'])->on('crm_opportunities')->onDelete('restrict');
            $table->foreign(['hq_id', 'to_funnel_id'], 'crm_opportunity_events_to_funnel_fk')->references(['hq_id', 'id'])->on('crm_sales_funnel')->onDelete('restrict');
            $table->foreign(['hq_id', 'to_step_id'], 'crm_opportunity_events_to_step_fk')->references(['hq_id', 'id'])->on('crm_sales_funnel_steps')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_opportunity_events_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunity_events');
    }
};
