<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_capabilities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('driver_id');
            $table->enum('capability', ['PICKUP', 'LINEHAUL', 'DELIVERY']);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['driver_id', 'capability'], 'driver_capabilities_unique');
            $table->index(['hq_id', 'driver_id'], 'driver_capabilities_driver_fk');
            $table->index(['hq_id', 'capability'], 'driver_capabilities_lookup_index');
            $table->foreign(['hq_id', 'driver_id'], 'driver_capabilities_driver_fk')->references(['hq_id', 'id'])->on('drivers')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_capabilities');
    }
};
