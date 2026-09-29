<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Metadata of a shared document, independent of the bytes. Storage location, file name, MIME, size and
// hash live in document_versions, which is still an open decision, so this table does not imply upload support.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('title', 200);
            $table->unsignedInteger('category_id')->nullable();
            // Access classification; the allowed values are still undecided, so no enum is fabricated here.
            $table->string('classification', 40);
            $table->string('reference_no', 120)->nullable();
            $table->date('expires_on')->nullable();
            $table->enum('status', ['ACTIVE', 'ARCHIVED'])->default('ACTIVE');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'documents_hq_internal_id_unique');
            $table->index(['hq_id', 'status', 'classification'], 'documents_status_classification_index');
            $table->index(['hq_id', 'category_id'], 'documents_category_fk');
            $table->index(['hq_id', 'expires_on'], 'documents_hq_expires_index');
            $table->index(['created_by'], 'documents_created_by_fk');
            $table->foreign(['created_by'], 'documents_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'category_id'], 'documents_category_fk')->references(['hq_id', 'id'])->on('document_categories')->onDelete('restrict');
            $table->foreign(['hq_id'], 'documents_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
