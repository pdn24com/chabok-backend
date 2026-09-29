<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_eligibility_rules', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('service_offering_version_id');
            $table->enum('dimension', ['GEOGRAPHY', 'PHYSICAL', 'CONTENT', 'VALUE', 'COMMERCIAL', 'OPERATIONAL', 'TEMPORAL', 'OPTION', 'CHANNEL']);
            $table->string('fact_key', 120);
            $table->enum('operator', ['EQ', 'NEQ', 'IN', 'NOT_IN', 'MIN', 'MAX', 'BETWEEN', 'EXISTS', 'NOT_EXISTS']);
            $table->json('expected_value');
            $table->string('reason_code', 120);
            $table->unsignedSmallInteger('priority')->default('100');
            $table->index(['service_offering_version_id', 'priority'], 'service_eligibility_order_index');
            $table->foreign(['service_offering_version_id'], 'service_eligibility_rules_service_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_eligibility_rules');
    }
};
