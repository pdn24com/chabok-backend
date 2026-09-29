<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sales_documents', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('opportunity_id');
            // The document customer must be the opportunity customer and already in phase CUSTOMER.
            $table->unsignedInteger('customer_id');
            // CRM numbering, deliberately separate from the consignment number ranges.
            $table->string('document_no', 80);
            $table->enum('document_type', ['ESTIMATE', 'PROPOSAL', 'PROFORMA']);
            // Points back at a row of crm_sales_document_versions; the cycle is closed in a later migration.
            $table->unsignedInteger('current_version_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'document_no'], 'crm_sales_documents_hq_document_no_unique');
            $table->unique(['hq_id', 'id'], 'crm_sales_documents_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id', 'document_type'], 'crm_sales_documents_customer_type_index');
            $table->index(['hq_id', 'opportunity_id'], 'crm_sales_documents_opportunity_fk');
            $table->index(['hq_id', 'current_version_id'], 'crm_sales_documents_current_version_fk');
            $table->index(['created_by'], 'crm_sales_documents_created_by_fk');
            $table->foreign(['created_by'], 'crm_sales_documents_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_sales_documents_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'opportunity_id'], 'crm_sales_documents_opportunity_fk')->references(['hq_id', 'id'])->on('crm_opportunities')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_sales_documents_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_documents');
    }
};
