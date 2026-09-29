<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contracts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('opportunity_id')->nullable();
            // The proforma version the contract was built on; the customer of every reference must match.
            $table->unsignedInteger('proforma_version_id')->nullable();
            $table->string('reference_no', 120);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->text('commitments')->nullable();
            // Free string until the contract status list is decided; no enum is assumed here.
            $table->string('status', 40);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_contracts_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id', 'status'], 'crm_contracts_customer_status_index');
            $table->index(['hq_id', 'reference_no'], 'crm_contracts_hq_reference_index');
            $table->index(['hq_id', 'end_date'], 'crm_contracts_hq_end_date_index');
            $table->index(['hq_id', 'opportunity_id'], 'crm_contracts_opportunity_fk');
            $table->index(['hq_id', 'proforma_version_id'], 'crm_contracts_proforma_version_fk');
            $table->index(['created_by'], 'crm_contracts_created_by_fk');
            $table->foreign(['created_by'], 'crm_contracts_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_contracts_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'opportunity_id'], 'crm_contracts_opportunity_fk')->references(['hq_id', 'id'])->on('crm_opportunities')->onDelete('restrict');
            $table->foreign(['hq_id', 'proforma_version_id'], 'crm_contracts_proforma_version_fk')->references(['hq_id', 'id'])->on('crm_sales_document_versions')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_contracts_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contracts');
    }
};
