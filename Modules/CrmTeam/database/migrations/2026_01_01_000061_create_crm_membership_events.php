<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_membership_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('membership_id');
            $table->unsignedInteger('actor_id');
            // Correlation handle for a bulk add or transfer; not a foreign key to any table.
            $table->unsignedInteger('operation_id')->nullable();
            $table->enum('event_type', ['ADD', 'END', 'TRANSFER']);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['hq_id', 'membership_id', 'occurred_at'], 'crm_membership_events_timeline_index');
            $table->index(['hq_id', 'operation_id'], 'crm_membership_events_operation_index');
            $table->index(['actor_id'], 'crm_membership_events_actor_fk');
            $table->index(['created_by'], 'crm_membership_events_created_by_fk');
            $table->foreign(['actor_id'], 'crm_membership_events_actor_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'crm_membership_events_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'membership_id'], 'crm_membership_events_membership_fk')->references(['hq_id', 'id'])->on('crm_team_members')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_membership_events_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_membership_events');
    }
};
