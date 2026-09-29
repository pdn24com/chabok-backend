<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_financial_details', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            $table->bigInteger('credit_limit')->nullable();
            // null means not assessed; there is no lookup table behind this grade.
            $table->enum('credit_rating', ['LOW_RISK', 'MEDIUM_RISK', 'HIGH_RISK'])->nullable();
            $table->text('settlement_terms')->nullable();
            // Reference date of the figures, not the row write time.
            $table->date('financial_reference_date');
            $table->text('source_note');
            $table->string('accounting_code', 80)->nullable();
            $table->string('accounting_title', 200)->nullable();
            $table->bigInteger('revenue')->nullable();
            $table->bigInteger('receipts')->nullable();
            $table->bigInteger('direct_cost')->nullable();
            $table->bigInteger('balance')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'customer_id'], 'crm_customer_financial_details_customer_unique');
            $table->index(['hq_id', 'credit_rating'], 'crm_customer_financial_details_hq_rating_index');
            $table->index(['created_by'], 'crm_customer_financial_details_created_by_fk');
            $table->foreign(['created_by'], 'crm_customer_financial_details_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_customer_financial_details_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_customer_financial_details_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_financial_details');
    }
};
