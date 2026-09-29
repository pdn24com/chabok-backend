<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Polymorphic link. resource_id carries no SQL foreign key on purpose; the allowed resource_type registry
// and the per-target existence and tenant check belong to the write path, and are still an open decision.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_links', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('document_id');
            $table->string('resource_type', 80);
            $table->unsignedInteger('resource_id');
            $table->string('purpose', 80)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'document_id', 'resource_type', 'resource_id'], 'document_links_document_resource_unique');
            $table->unique(['hq_id', 'id'], 'document_links_hq_internal_id_unique');
            // A document reachable from several resources needs the intersection of their permissions.
            $table->index(['hq_id', 'resource_type', 'resource_id'], 'document_links_resource_index');
            $table->index(['created_by'], 'document_links_created_by_fk');
            $table->foreign(['created_by'], 'document_links_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'document_id'], 'document_links_document_fk')->references(['hq_id', 'id'])->on('documents')->onDelete('restrict');
            $table->foreign(['hq_id'], 'document_links_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};
