<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_tasks', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('node_id');
            $table->unsignedInteger('assigned_driver_id')->nullable();
            $table->enum('status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED', 'FAILED']);
            $table->string('failure_reason_code', 80)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->timestamp('assigned_at', 6)->nullable();
            $table->timestamp('started_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamp('failed_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'consignment_id'], 'pickup_tasks_consignment_unique');
            $table->unique(['hq_id', 'id'], 'pickup_tasks_hq_id_unique');
            $table->index(['hq_id', 'assigned_driver_id'], 'pickup_tasks_driver_fk');
            $table->index(['hq_id', 'node_id', 'status', 'created_at'], 'pickup_tasks_queue_index');
            $table->foreign(['hq_id', 'assigned_driver_id'], 'pickup_tasks_driver_fk')->references(['hq_id', 'id'])->on('drivers')->onDelete('restrict');
            $table->foreign(['hq_id', 'consignment_id'], 'pickup_tasks_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'node_id'], 'pickup_tasks_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_tasks');
    }
};
