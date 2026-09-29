<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sales_document_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('document_id');
            $table->unsignedInteger('previous_version_id')->nullable();
            // Content revision number of the sales document, not an optimistic-locking counter.
            $table->unsignedInteger('version_no');
            // REVIEW is carried over from the previous model; its executable meaning is still undecided.
            $table->enum('status', ['DRAFT', 'REVIEW', 'ISSUED', 'ACCEPTED', 'CANCELLED'])->default('DRAFT');
            // Required before the version is issued; a draft may still be open-ended.
            $table->timestamp('expires_at', 6)->nullable();
            // Currency code kept apart from total; IRR is the current proposal, not a multi-currency guarantee.
            $table->string('currency', 3);
            $table->bigInteger('total');
            // Required before issue, so the issued content stays readable without the live customer record.
            $table->json('customer_snapshot')->nullable();
            $table->text('terms')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('issued_at', 6)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'document_id', 'version_no'], 'crm_sales_document_versions_document_no_unique');
            $table->unique(['hq_id', 'id'], 'crm_sales_document_versions_hq_internal_id_unique');
            $table->index(['hq_id', 'document_id', 'status'], 'crm_sales_document_versions_document_status_index');
            $table->index(['hq_id', 'previous_version_id'], 'crm_sales_document_versions_previous_fk');
            $table->index(['hq_id', 'status', 'expires_at'], 'crm_sales_document_versions_expiry_index');
            $table->index(['created_by'], 'crm_sales_document_versions_created_by_fk');
            $table->foreign(['created_by'], 'crm_sales_document_versions_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'document_id'], 'crm_sales_document_versions_document_fk')->references(['hq_id', 'id'])->on('crm_sales_documents')->onDelete('restrict');
            $table->foreign(['hq_id', 'previous_version_id'], 'crm_sales_document_versions_previous_fk')->references(['hq_id', 'id'])->on('crm_sales_document_versions')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_sales_document_versions_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_document_versions');
    }
};
