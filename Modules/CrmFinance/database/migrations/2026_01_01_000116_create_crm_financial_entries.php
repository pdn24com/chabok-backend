<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_financial_entries', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('invoice_id')->nullable();
            // Not a general ledger; a BALANCE_SNAPSHOT must never be summed with the revenue or receipt flow.
            $table->enum('kind', ['REVENUE', 'RECEIPT', 'DIRECT_COST', 'OPENING_BALANCE', 'BALANCE_SNAPSHOT', 'ADJUSTMENT']);
            $table->bigInteger('amount');
            $table->date('effective_on');
            $table->string('source_ref', 120);
            // A correction reverses an earlier entry instead of editing it.
            $table->unsignedInteger('reverses_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_financial_entries_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id', 'kind', 'effective_on'], 'crm_financial_entries_customer_kind_index');
            $table->index(['hq_id', 'invoice_id'], 'crm_financial_entries_invoice_fk');
            $table->index(['hq_id', 'reverses_id'], 'crm_financial_entries_reverses_fk');
            $table->index(['created_by'], 'crm_financial_entries_created_by_fk');
            $table->foreign(['created_by'], 'crm_financial_entries_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_financial_entries_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'invoice_id'], 'crm_financial_entries_invoice_fk')->references(['hq_id', 'id'])->on('crm_external_invoices')->onDelete('restrict');
            $table->foreign(['hq_id', 'reverses_id'], 'crm_financial_entries_reverses_fk')->references(['hq_id', 'id'])->on('crm_financial_entries')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_financial_entries_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_financial_entries');
    }
};
