<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_coverage_references', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('service_offering_version_id');
            $table->enum('direction', ['ORIGIN', 'DESTINATION', 'LANE', 'BOTH']);
            $table->enum('reference_type', ['COUNTRY', 'PROVINCE', 'CITY', 'POSTAL_RANGE', 'OPERATIONAL_AREA', 'PRICING_ZONE_SET']);
            $table->string('reference_value', 200);
            $table->string('secondary_reference_value', 200)->nullable();
            $table->unsignedSmallInteger('priority')->default('100');
            $table->index(['service_offering_version_id', 'direction', 'reference_type'], 'service_coverage_resolution_index');
            $table->foreign(['service_offering_version_id'], 'service_coverage_references_service_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_coverage_references');
    }
};
