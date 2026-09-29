<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_extended_details', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            $table->string('salutation', 80)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('trade_name', 200)->nullable();
            $table->string('legal_form', 120)->nullable();
            $table->string('legal_name', 200)->nullable();
            $table->string('registration_no', 80)->nullable();
            $table->date('registration_date')->nullable();
            $table->string('registration_place', 200)->nullable();
            $table->text('need_summary')->nullable();
            // Qualification moved here from the dropped crm_qualifications table. These columns hold the
            // latest evaluation of the customer record only; per-opportunity evaluation is a separate concern
            // and no independent qualification history is kept, corrections live in audit.
            $table->bigInteger('budget')->nullable();
            // Null until the evaluation happens; unknown is not the same as a budget of zero.
            $table->boolean('budget_known')->nullable();
            $table->text('authority_note')->nullable();
            $table->boolean('need_confirmed')->nullable();
            $table->text('timeframe')->nullable();
            // Free string, not an enum: the allowed results are still an open decision.
            $table->string('qualification_result', 40)->nullable();
            $table->unsignedInteger('evaluated_by')->nullable();
            $table->timestamp('evaluated_at', 6)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'customer_id'], 'crm_customer_extended_details_customer_unique');
            $table->index(['hq_id', 'qualification_result'], 'crm_customer_extended_details_result_index');
            $table->index(['created_by'], 'crm_customer_extended_details_created_by_fk');
            $table->index(['evaluated_by'], 'crm_customer_extended_details_evaluated_by_fk');
            $table->foreign(['created_by'], 'crm_customer_extended_details_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['evaluated_by'], 'crm_customer_extended_details_evaluated_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_customer_extended_details_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_customer_extended_details_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_extended_details');
    }
};
