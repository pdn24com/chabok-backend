<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_external_invoices', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('contract_id')->nullable();
            $table->unsignedInteger('opportunity_id')->nullable();
            // Reference to a document issued outside CRM; recording it recognises no revenue by itself.
            $table->string('external_system', 80);
            $table->string('reference_no', 120);
            $table->bigInteger('amount');
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'external_system', 'reference_no'], 'crm_external_invoices_system_reference_unique');
            $table->unique(['hq_id', 'id'], 'crm_external_invoices_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id', 'issued_on'], 'crm_external_invoices_customer_issued_index');
            $table->index(['hq_id', 'due_on'], 'crm_external_invoices_hq_due_index');
            $table->index(['hq_id', 'contract_id'], 'crm_external_invoices_contract_fk');
            $table->index(['hq_id', 'opportunity_id'], 'crm_external_invoices_opportunity_fk');
            $table->index(['created_by'], 'crm_external_invoices_created_by_fk');
            $table->foreign(['created_by'], 'crm_external_invoices_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'contract_id'], 'crm_external_invoices_contract_fk')->references(['hq_id', 'id'])->on('crm_contracts')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_external_invoices_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'opportunity_id'], 'crm_external_invoices_opportunity_fk')->references(['hq_id', 'id'])->on('crm_opportunities')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_external_invoices_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_external_invoices');
    }
};
