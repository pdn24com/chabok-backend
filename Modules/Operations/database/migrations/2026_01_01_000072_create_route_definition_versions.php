<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_definition_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('route_definition_id');
            $table->unsignedInteger('version_number');
            $table->enum('status', ['DRAFT', 'VALIDATED', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED']);
            $table->enum('purpose', ['TRUNK', 'LAST_MILE']);
            $table->unsignedInteger('origin_node_id');
            $table->unsignedInteger('destination_node_id');
            $table->integer('priority')->default('0');
            $table->unsignedInteger('offering_version_id')->nullable();
            $table->timestamp('effective_from', 6)->nullable();
            $table->timestamp('effective_to', 6)->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('validated_by')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamp('validated_at', 6)->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->string('content_digest', 64)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['route_definition_id', 'version_number'], 'route_versions_number_unique');
            $table->unique(['hq_id', 'id'], 'route_versions_hq_id_unique');
            $table->index(['hq_id', 'route_definition_id'], 'route_versions_definition_fk');
            $table->index(['hq_id', 'origin_node_id'], 'route_versions_origin_fk');
            $table->index(['hq_id', 'destination_node_id'], 'route_versions_destination_fk');
            $table->index(['offering_version_id'], 'route_definition_versions_offering_version_id_foreign');
            $table->index(['created_by'], 'route_definition_versions_created_by_foreign');
            $table->index(['validated_by'], 'route_definition_versions_validated_by_foreign');
            $table->index(['approved_by'], 'route_definition_versions_approved_by_foreign');
            $table->index(['published_by'], 'route_definition_versions_published_by_foreign');
            $table->index(['hq_id', 'status', 'purpose', 'origin_node_id', 'destination_node_id', 'priority'], 'route_versions_resolution_index');
            $table->foreign(['approved_by'], 'route_definition_versions_approved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'route_definition_versions_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'destination_node_id'], 'route_versions_destination_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'origin_node_id'], 'route_versions_origin_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['offering_version_id'], 'route_definition_versions_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
            $table->foreign(['published_by'], 'route_definition_versions_published_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['validated_by'], 'route_definition_versions_validated_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_definition_versions');
    }
};
