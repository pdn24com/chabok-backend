<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_task_history', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('delivery_task_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('event_sequence');
            $table->enum('event_type', ['CREATED', 'ASSIGNED', 'REASSIGNED', 'ACTIVATED', 'COMPLETED', 'FAILED', 'RETRY_REQUESTED']);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->unsignedInteger('attempt_number');
            $table->unsignedInteger('assigned_driver_id')->nullable();
            $table->unsignedInteger('actor_id');
            $table->string('reason_code', 80)->nullable();
            $table->string('safe_note', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at', 6)->useCurrent();
            $table->unique(['delivery_task_id', 'event_sequence'], 'delivery_task_history_sequence_unique');
            $table->index(['hq_id', 'consignment_id'], 'delivery_task_history_consignment_fk');
            $table->index(['hq_id', 'assigned_driver_id'], 'delivery_task_history_driver_fk');
            $table->index(['actor_id'], 'delivery_task_history_actor_id_foreign');
            $table->index(['hq_id', 'delivery_task_id', 'occurred_at'], 'delivery_task_history_timeline_index');
            $table->foreign(['actor_id'], 'delivery_task_history_actor_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'assigned_driver_id'], 'delivery_task_history_driver_fk')->references(['hq_id', 'id'])->on('drivers')->onDelete('restrict');
            $table->foreign(['hq_id', 'consignment_id'], 'delivery_task_history_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'delivery_task_id'], 'delivery_task_history_task_fk')->references(['hq_id', 'id'])->on('delivery_tasks')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_task_history');
    }
};
