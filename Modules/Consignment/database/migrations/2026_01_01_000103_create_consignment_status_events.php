<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_status_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('event_sequence')->nullable();
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('parcel_id')->nullable();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->enum('aggregate_mode', ['FULL', 'PARTIAL'])->nullable();
            $table->json('parcel_status_counts')->nullable();
            $table->unsignedInteger('initiator_id');
            $table->unsignedInteger('node_id')->nullable();
            $table->unsignedInteger('driver_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('manifest_id')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['hq_id', 'parcel_id'], 'status_events_parcel_fk');
            $table->index(['initiator_id'], 'consignment_status_events_initiator_id_foreign');
            $table->index(['hq_id', 'node_id'], 'status_events_node_fk');
            $table->index(['hq_id', 'consignment_id', 'created_at'], 'status_events_timeline_index');
            $table->index(['hq_id', 'manifest_id'], 'status_events_manifest_fk');
            $table->index(['hq_id', 'consignment_id', 'event_sequence'], 'status_events_sequence_index');
            $table->index(['hq_id', 'consignment_id', 'correlation_id'], 'status_events_correlation_index');
            $table->foreign(['hq_id', 'consignment_id'], 'status_events_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'manifest_id'], 'status_events_manifest_fk')->references(['hq_id', 'id'])->on('manifests')->onDelete('restrict');
            $table->foreign(['hq_id', 'node_id'], 'status_events_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'parcel_id'], 'status_events_parcel_fk')->references(['hq_id', 'id'])->on('parcels')->onDelete('restrict');
            $table->foreign(['initiator_id'], 'consignment_status_events_initiator_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_status_events');
    }
};
