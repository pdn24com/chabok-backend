<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_definitions', function (Blueprint $table): void {
            $table->char('route_definition_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('route_code', 80);
            $table->string('route_title', 200);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->unique(['hq_id', 'route_code'], 'route_definitions_hq_code_unique');
            $table->unique(['hq_id', 'route_definition_id'], 'route_definitions_hq_id_unique');
            $table->index(['hq_id', 'status'], 'route_definitions_hq_status_index');
        });

        Schema::create('route_definition_legs', function (Blueprint $table): void {
            $table->char('route_definition_leg_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('route_definition_id', 36);
            $table->unsignedSmallInteger('leg_order');
            $table->char('origin_node_id', 36);
            $table->char('destination_node_id', 36);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps(6);
            $table->foreign(['hq_id', 'route_definition_id'], 'route_definition_legs_route_fk')
                ->references(['hq_id', 'route_definition_id'])->on('route_definitions')->restrictOnDelete();
            $table->foreign(['hq_id', 'origin_node_id'], 'route_definition_legs_origin_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_node_id'], 'route_definition_legs_destination_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->unique(['route_definition_id', 'leg_order'], 'route_definition_legs_order_unique');
            $table->unique(['hq_id', 'route_definition_leg_id'], 'route_definition_legs_hq_id_unique');
            $table->index(['hq_id', 'origin_node_id', 'destination_node_id', 'status'], 'route_definition_legs_lookup_index');
        });
        DB::statement('ALTER TABLE route_definition_legs ADD CONSTRAINT route_definition_legs_not_self CHECK (origin_node_id <> destination_node_id)');

        Schema::create('drivers', function (Blueprint $table): void {
            $table->char('driver_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('user_id', 36)->nullable();
            $table->string('driver_code', 80);
            $table->string('display_name', 200);
            $table->char('home_node_id', 36);
            $table->enum('operational_type', ['PICKUP', 'LINEHAUL', 'DELIVERY', 'MULTI']);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'INACTIVE'])->default('AVAILABLE');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign(['hq_id', 'home_node_id'], 'drivers_home_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->unique(['hq_id', 'driver_code'], 'drivers_hq_code_unique');
            $table->unique(['hq_id', 'driver_id'], 'drivers_hq_id_unique');
            $table->unique('user_id', 'drivers_user_unique');
            $table->index(['hq_id', 'home_node_id', 'status', 'availability_status'], 'drivers_assignment_lookup_index');
        });

        Schema::create('driver_capabilities', function (Blueprint $table): void {
            $table->char('driver_capability_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('driver_id', 36);
            $table->enum('capability', ['PICKUP', 'LINEHAUL', 'DELIVERY']);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign(['hq_id', 'driver_id'], 'driver_capabilities_driver_fk')
                ->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->unique(['driver_id', 'capability'], 'driver_capabilities_unique');
            $table->index(['hq_id', 'capability'], 'driver_capabilities_lookup_index');
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->char('vehicle_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('vehicle_code', 80);
            $table->string('registration_number', 80);
            $table->enum('vehicle_type', ['VAN', 'TRUCK']);
            $table->char('home_node_id', 36);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'MAINTENANCE', 'INACTIVE'])->default('AVAILABLE');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign(['hq_id', 'home_node_id'], 'vehicles_home_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->unique(['hq_id', 'vehicle_code'], 'vehicles_hq_code_unique');
            $table->unique(['hq_id', 'registration_number'], 'vehicles_hq_registration_unique');
            $table->unique(['hq_id', 'vehicle_id'], 'vehicles_hq_id_unique');
            $table->index(['hq_id', 'home_node_id', 'status', 'availability_status'], 'vehicles_assignment_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('driver_capabilities');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('route_definition_legs');
        Schema::dropIfExists('route_definitions');
    }
};
