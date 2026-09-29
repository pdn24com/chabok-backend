<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_legacy_mappings', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('legacy_system', 80);
            $table->string('legacy_type', 80);
            $table->string('legacy_code', 160);
            $table->string('target_type', 80);
            $table->unsignedInteger('target_identity_id');
            $table->unsignedInteger('target_version_id')->nullable();
            $table->json('source_evidence')->nullable();
            $table->unique(['legacy_system', 'legacy_type', 'legacy_code', 'hq_id'], 'service_legacy_mapping_unique');
            $table->index(['hq_id'], 'service_legacy_mappings_hq_id_foreign');
            $table->foreign(['hq_id'], 'service_legacy_mappings_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_legacy_mappings');
    }
};
