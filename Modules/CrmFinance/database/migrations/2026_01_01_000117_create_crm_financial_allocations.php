<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_financial_allocations', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            // Must point at an entry of kind RECEIPT; receipt and invoice share the tenant and the customer.
            $table->unsignedInteger('receipt_entry_id');
            $table->unsignedInteger('invoice_id');
            $table->bigInteger('amount');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_financial_allocations_hq_internal_id_unique');
            $table->index(['hq_id', 'receipt_entry_id'], 'crm_financial_allocations_receipt_fk');
            $table->index(['hq_id', 'invoice_id'], 'crm_financial_allocations_invoice_fk');
            $table->index(['created_by'], 'crm_financial_allocations_created_by_fk');
            $table->foreign(['created_by'], 'crm_financial_allocations_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'invoice_id'], 'crm_financial_allocations_invoice_fk')->references(['hq_id', 'id'])->on('crm_external_invoices')->onDelete('restrict');
            $table->foreign(['hq_id', 'receipt_entry_id'], 'crm_financial_allocations_receipt_fk')->references(['hq_id', 'id'])->on('crm_financial_entries')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_financial_allocations_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_financial_allocations');
    }
};
