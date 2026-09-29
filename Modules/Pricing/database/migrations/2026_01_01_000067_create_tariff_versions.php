<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('tariff_family_id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('zone_set_version_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->unsignedInteger('previous_version_id')->nullable();
            $table->enum('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED', 'CANCELLED'])->default('DRAFT');
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_to', 6)->nullable();
            $table->unsignedInteger('lock_version')->default('1');
            $table->decimal('volumetric_divisor', 12, 3)->default('5000.000');
            $table->decimal('weight_rounding_step_kg', 8, 3)->default('0.500');
            $table->enum('rounding_mode', ['HALF_UP', 'HALF_EVEN', 'CEILING', 'FLOOR', 'STEP_UP'])->default('STEP_UP');
            $table->char('content_digest', 64)->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->enum('zone_policy', ['DIRECTIONAL', 'HIGHER_ZONE_RANK'])->default('DIRECTIONAL');
            $table->json('freight_matrices')->nullable();
            $table->string('matrix_basis', 40)->default('BILLABLE_WEIGHT');
            $table->boolean('is_default')->default('0');
            $table->unique(['tariff_family_id', 'version_number'], 'tariff_version_number_unique');
            $table->index(['zone_set_version_id'], 'tariff_versions_zone_set_version_id_foreign');
            $table->index(['previous_version_id'], 'tariff_versions_previous_version_id_foreign');
            $table->index(['created_by'], 'tariff_versions_created_by_foreign');
            $table->index(['approved_by'], 'tariff_versions_approved_by_foreign');
            $table->index(['published_by'], 'tariff_versions_published_by_foreign');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'tariff_version_effective_index');
            $table->foreign(['approved_by'], 'tariff_versions_approved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'tariff_versions_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['previous_version_id'], 'tariff_versions_previous_version_id_foreign')->references(['id'])->on('tariff_versions')->onDelete('restrict');
            $table->foreign(['published_by'], 'tariff_versions_published_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['tariff_family_id'], 'tariff_versions_tariff_family_id_foreign')->references(['id'])->on('tariff_families')->onDelete('restrict');
            $table->foreign(['zone_set_version_id'], 'tariff_versions_zone_set_version_id_foreign')->references(['id'])->on('pricing_zone_set_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_versions');
    }
};
