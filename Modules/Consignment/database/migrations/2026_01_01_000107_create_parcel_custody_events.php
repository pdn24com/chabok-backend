<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_custody_events', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('event_sequence')->nullable();
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('parcel_id');
            $table->unsignedInteger('from_node_id')->nullable();
            $table->unsignedInteger('to_node_id')->nullable();
            $table->string('from_custody_type', 40)->nullable();
            $table->string('to_custody_type', 40);
            $table->unsignedInteger('from_custodian_id')->nullable();
            $table->unsignedInteger('to_custodian_id')->nullable();
            $table->string('command_name', 100);
            $table->unsignedInteger('initiator_id');
            $table->unsignedInteger('manifest_id')->nullable();
            $table->unsignedInteger('route_plan_id')->nullable();
            $table->unsignedInteger('route_plan_leg_id')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['hq_id', 'parcel_id'], 'custody_events_parcel_fk');
            $table->index(['initiator_id'], 'parcel_custody_events_initiator_id_foreign');
            $table->index(['hq_id', 'consignment_id', 'created_at'], 'custody_events_timeline_index');
            $table->index(['hq_id', 'consignment_id', 'event_sequence'], 'custody_events_sequence_index');
            $table->foreign(['hq_id', 'consignment_id'], 'custody_events_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'parcel_id'], 'custody_events_parcel_fk')->references(['hq_id', 'id'])->on('parcels')->onDelete('restrict');
            $table->foreign(['initiator_id'], 'parcel_custody_events_initiator_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_custody_events');
    }
};
