<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_offering_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('service_offering_id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->unsignedInteger('previous_version_id')->nullable();
            $table->unsignedInteger('service_type_version_id');
            $table->unsignedInteger('shipping_method_version_id');
            $table->json('labels');
            $table->text('description')->nullable();
            $table->json('sla_policy');
            $table->json('availability_summary')->nullable();
            $table->enum('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED', 'CANCELLED'])->default('DRAFT');
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_to', 6)->nullable();
            $table->unsignedInteger('lock_version')->default('1');
            $table->char('content_digest', 64)->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['service_offering_id', 'version_number'], 'service_offering_version_number_unique');
            $table->index(['previous_version_id'], 'service_offering_versions_previous_version_id_foreign');
            $table->index(['service_type_version_id'], 'service_offering_versions_service_type_version_id_foreign');
            $table->index(['shipping_method_version_id'], 'service_offering_versions_shipping_method_version_id_foreign');
            $table->index(['created_by'], 'service_offering_versions_created_by_foreign');
            $table->index(['approved_by'], 'service_offering_versions_approved_by_foreign');
            $table->index(['published_by'], 'service_offering_versions_published_by_foreign');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'service_offering_effective_index');
            $table->foreign(['approved_by'], 'service_offering_versions_approved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'service_offering_versions_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['previous_version_id'], 'service_offering_versions_previous_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
            $table->foreign(['published_by'], 'service_offering_versions_published_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['service_offering_id'], 'service_offering_versions_service_offering_id_foreign')->references(['id'])->on('service_offerings')->onDelete('restrict');
            $table->foreign(['service_type_version_id'], 'service_offering_versions_service_type_version_id_foreign')->references(['id'])->on('service_type_versions')->onDelete('restrict');
            $table->foreign(['shipping_method_version_id'], 'service_offering_versions_shipping_method_version_id_foreign')->references(['id'])->on('shipping_method_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_offering_versions');
    }
};
