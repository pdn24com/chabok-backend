<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_tasks', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('node_id');
            $table->unsignedInteger('assigned_driver_id')->nullable();
            $table->unsignedInteger('manifest_id')->nullable();
            $table->unsignedInteger('last_mile_resolution_id')->nullable();
            $table->enum('status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED', 'FAILED']);
            $table->unsignedInteger('attempt_number')->default('1');
            $table->string('recipient_name', 200)->nullable();
            $table->enum('proof_type', ['MANUAL_CONFIRMATION'])->nullable();
            $table->string('proof_note', 500)->nullable();
            $table->string('failure_reason_code', 80)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->timestamp('delivered_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'consignment_id'], 'delivery_tasks_consignment_unique');
            $table->unique(['hq_id', 'id'], 'delivery_tasks_hq_id_unique');
            $table->index(['hq_id', 'assigned_driver_id'], 'delivery_tasks_driver_fk');
            $table->index(['hq_id', 'manifest_id'], 'delivery_tasks_manifest_fk');
            $table->index(['hq_id', 'node_id', 'status', 'created_at'], 'delivery_tasks_queue_index');
            $table->index(['last_mile_resolution_id'], 'delivery_tasks_last_mile_evidence_fk');
            $table->foreign(['hq_id', 'assigned_driver_id'], 'delivery_tasks_driver_fk')->references(['hq_id', 'id'])->on('drivers')->onDelete('restrict');
            $table->foreign(['hq_id', 'consignment_id'], 'delivery_tasks_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'manifest_id'], 'delivery_tasks_manifest_fk')->references(['hq_id', 'id'])->on('manifests')->onDelete('restrict');
            $table->foreign(['hq_id', 'node_id'], 'delivery_tasks_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['last_mile_resolution_id'], 'delivery_tasks_last_mile_evidence_fk')->references(['id'])->on('last_mile_resolution_evidence')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tasks');
    }
};
