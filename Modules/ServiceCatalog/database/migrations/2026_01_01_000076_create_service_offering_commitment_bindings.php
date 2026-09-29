<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_offering_commitment_bindings', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('service_offering_version_id');
            $table->unsignedInteger('commitment_schedule_version_id');
            $table->enum('pickup_mode', ['NONE', 'SELECTABLE_WINDOW', 'COMPUTED']);
            $table->enum('delivery_mode', ['NONE', 'SELECTABLE_WINDOW', 'COMPUTED']);
            $table->unsignedInteger('duration_value')->nullable();
            $table->enum('duration_unit', ['MINUTE', 'HOUR', 'DAY'])->nullable();
            $table->enum('duration_anchor', ['CONSIGNMENT_CREATED', 'PICKUP_COMMITMENT_START', 'PICKUP_COMMITMENT_END', 'PICKUP_COMPLETED'])->nullable();
            $table->unique(['service_offering_version_id'], 'offering_commitment_binding_offering_unique');
            $table->index(['commitment_schedule_version_id', 'pickup_mode', 'delivery_mode'], 'offering_commitment_schedule_lookup_index');
            $table->foreign(['commitment_schedule_version_id'], 'offering_commitment_binding_schedule_fk')->references(['id'])->on('commitment_schedule_versions')->onDelete('restrict');
            $table->foreign(['service_offering_version_id'], 'offering_commitment_binding_offering_fk')->references(['id'])->on('service_offering_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_offering_commitment_bindings');
    }
};
