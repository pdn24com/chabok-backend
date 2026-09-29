<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_plan_legs', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('route_plan_id');
            $table->unsignedInteger('source_route_definition_leg_id');
            $table->unsignedInteger('source_route_definition_version_leg_id')->nullable();
            $table->unsignedSmallInteger('leg_order');
            $table->unsignedInteger('origin_node_id');
            $table->unsignedInteger('destination_node_id');
            $table->enum('status', ['PENDING', 'ROUTED', 'OUTBOUND_CONFIRMED', 'IN_TRANSIT', 'ARRIVED', 'RECEIVED']);
            $table->timestamp('routed_at', 6)->nullable();
            $table->timestamp('received_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['route_plan_id', 'leg_order'], 'route_plan_legs_order_unique');
            $table->unique(['hq_id', 'id'], 'route_plan_legs_hq_id_unique');
            $table->index(['hq_id', 'origin_node_id'], 'route_plan_legs_origin_fk');
            $table->index(['hq_id', 'destination_node_id'], 'route_plan_legs_destination_fk');
            $table->index(['hq_id', 'route_plan_id', 'status', 'leg_order'], 'route_plan_legs_progress_index');
            $table->index(['hq_id', 'source_route_definition_version_leg_id'], 'route_plan_legs_source_version_fk');
            $table->foreign(['hq_id', 'destination_node_id'], 'route_plan_legs_destination_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'origin_node_id'], 'route_plan_legs_origin_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_plan_id'], 'route_plan_legs_plan_fk')->references(['hq_id', 'id'])->on('route_plans')->onDelete('restrict');
            $table->foreign(['hq_id', 'source_route_definition_version_leg_id'], 'route_plan_legs_source_version_fk')->references(['hq_id', 'id'])->on('route_definition_version_legs')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_plan_legs');
    }
};
