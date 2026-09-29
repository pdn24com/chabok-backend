<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->string('parcel_number', 40);
            $table->string('current_status', 32);
            $table->unsignedInteger('current_node_id')->nullable();
            $table->enum('current_custody_type', ['NODE', 'PICKUP_DRIVER', 'LINEHAUL_DRIVER', 'DELIVERY_DRIVER', 'RECIPIENT'])->default('NODE');
            $table->unsignedInteger('current_custodian_id')->nullable();
            $table->unsignedInteger('active_route_plan_id')->nullable();
            $table->unsignedInteger('active_route_plan_leg_id')->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->string('content_description', 500)->nullable();
            $table->decimal('weight_kg', 12, 3)->nullable();
            $table->decimal('width_cm', 12, 3)->nullable();
            $table->decimal('length_cm', 12, 3)->nullable();
            $table->decimal('height_cm', 12, 3)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['parcel_number'], 'parcels_parcel_number_unique');
            $table->unique(['hq_id', 'id'], 'parcels_hq_id_unique');
            $table->index(['hq_id', 'consignment_id'], 'parcels_consignment_index');
            $table->index(['hq_id', 'current_status'], 'parcels_status_index');
            $table->index(['hq_id', 'current_node_id', 'current_status'], 'parcels_node_status_index');
            $table->index(['hq_id', 'active_route_plan_leg_id'], 'parcels_active_route_leg_fk');
            $table->index(['hq_id', 'active_route_plan_id', 'active_route_plan_leg_id'], 'parcels_active_route_index');
            $table->foreign(['hq_id', 'active_route_plan_id'], 'parcels_active_route_plan_fk')->references(['hq_id', 'id'])->on('route_plans')->onDelete('restrict');
            $table->foreign(['hq_id', 'active_route_plan_leg_id'], 'parcels_active_route_leg_fk')->references(['hq_id', 'id'])->on('route_plan_legs')->onDelete('restrict');
            $table->foreign(['hq_id', 'consignment_id'], 'parcels_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'current_node_id'], 'parcels_current_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id'], 'parcels_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
