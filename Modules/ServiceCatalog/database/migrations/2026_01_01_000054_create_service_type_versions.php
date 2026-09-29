<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_type_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('service_type_id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('version_number');
            $table->unsignedInteger('previous_version_id')->nullable();
            $table->json('labels');
            $table->text('description')->nullable();
            $table->json('definition');
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
            $table->unique(['service_type_id', 'version_number'], 'service_type_versions_number_unique');
            $table->index(['previous_version_id'], 'service_type_versions_previous_version_id_foreign');
            $table->index(['created_by'], 'service_type_versions_created_by_foreign');
            $table->index(['approved_by'], 'service_type_versions_approved_by_foreign');
            $table->index(['published_by'], 'service_type_versions_published_by_foreign');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'service_type_versions_effective_index');
            $table->foreign(['approved_by'], 'service_type_versions_approved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'service_type_versions_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['previous_version_id'], 'service_type_versions_previous_version_id_foreign')->references(['id'])->on('service_type_versions')->onDelete('restrict');
            $table->foreign(['published_by'], 'service_type_versions_published_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['service_type_id'], 'service_type_versions_service_type_id_foreign')->references(['id'])->on('service_types')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_type_versions');
    }
};
