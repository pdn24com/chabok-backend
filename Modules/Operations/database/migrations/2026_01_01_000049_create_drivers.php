<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('driver_code', 80);
            $table->string('display_name', 200);
            $table->string('mobile', 32)->nullable();
            $table->unsignedInteger('home_node_id');
            $table->enum('operational_type', ['PICKUP', 'LINEHAUL', 'DELIVERY', 'MULTI']);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'MAINTENANCE', 'INACTIVE'])->default('AVAILABLE');
            $table->unsignedInteger('version')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'driver_code'], 'drivers_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'drivers_hq_id_unique');
            $table->unique(['user_id'], 'drivers_user_unique');
            $table->index(['hq_id', 'home_node_id', 'status', 'availability_status'], 'drivers_assignment_lookup_index');
            $table->foreign(['hq_id', 'home_node_id'], 'drivers_home_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id'], 'drivers_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['user_id'], 'drivers_user_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
