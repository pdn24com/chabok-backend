<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Delivery result of a general service or product only; freight execution stays in Operations and carries no stock.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_deliveries', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('opportunity_id');
            $table->unsignedInteger('invoice_id')->nullable();
            // Free string until the delivery result list is decided; no enum is assumed here.
            $table->string('result', 40);
            $table->timestamp('occurred_at', 6);
            $table->string('reference_no', 120)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_deliveries_hq_internal_id_unique');
            $table->index(['hq_id', 'opportunity_id', 'occurred_at'], 'crm_deliveries_opportunity_timeline_index');
            $table->index(['hq_id', 'invoice_id'], 'crm_deliveries_invoice_fk');
            $table->index(['created_by'], 'crm_deliveries_created_by_fk');
            $table->foreign(['created_by'], 'crm_deliveries_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'invoice_id'], 'crm_deliveries_invoice_fk')->references(['hq_id', 'id'])->on('crm_external_invoices')->onDelete('restrict');
            $table->foreign(['hq_id', 'opportunity_id'], 'crm_deliveries_opportunity_fk')->references(['hq_id', 'id'])->on('crm_opportunities')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_deliveries_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deliveries');
    }
};
