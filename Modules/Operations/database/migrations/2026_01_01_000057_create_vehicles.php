<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('vehicle_code', 80);
            $table->string('registration_number', 80);
            $table->string('plate_number', 40);
            $table->enum('vehicle_type', ['MOTORCYCLE', 'CAR', 'VAN', 'LIGHT_TRUCK', 'TRUCK', 'TRAILER', 'OTHER']);
            $table->unsignedInteger('home_node_id');
            $table->unsignedBigInteger('capacity_weight_grams')->nullable();
            $table->unsignedBigInteger('capacity_volume_cm3')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'MAINTENANCE', 'INACTIVE'])->default('AVAILABLE');
            $table->unsignedInteger('version')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'vehicle_code'], 'vehicles_hq_code_unique');
            $table->unique(['hq_id', 'registration_number'], 'vehicles_hq_registration_unique');
            $table->unique(['hq_id', 'id'], 'vehicles_hq_id_unique');
            $table->unique(['hq_id', 'plate_number'], 'vehicles_hq_plate_unique');
            $table->index(['hq_id', 'home_node_id', 'status', 'availability_status'], 'vehicles_assignment_lookup_index');
            $table->foreign(['hq_id', 'home_node_id'], 'vehicles_home_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id'], 'vehicles_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
