<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            // Accepts a record in either phase, LEAD or CUSTOMER.
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('funnel_id');
            $table->unsignedInteger('current_step_id');
            $table->unsignedInteger('assignee_id');
            $table->string('title', 200);
            $table->bigInteger('amount')->nullable();
            $table->decimal('probability', 5, 2)->nullable();
            $table->date('expected_close')->nullable();
            $table->text('close_reason')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_opportunities_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id'], 'crm_opportunities_customer_fk');
            $table->index(['hq_id', 'funnel_id', 'current_step_id'], 'crm_opportunities_funnel_step_index');
            $table->index(['hq_id', 'current_step_id'], 'crm_opportunities_current_step_fk');
            $table->index(['hq_id', 'assignee_id'], 'crm_opportunities_hq_assignee_index');
            $table->index(['hq_id', 'expected_close'], 'crm_opportunities_hq_expected_close_index');
            $table->index(['assignee_id'], 'crm_opportunities_assignee_fk');
            $table->index(['created_by'], 'crm_opportunities_created_by_fk');
            $table->foreign(['assignee_id'], 'crm_opportunities_assignee_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'crm_opportunities_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'current_step_id'], 'crm_opportunities_current_step_fk')->references(['hq_id', 'id'])->on('crm_sales_funnel_steps')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_opportunities_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'funnel_id'], 'crm_opportunities_funnel_fk')->references(['hq_id', 'id'])->on('crm_sales_funnel')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_opportunities_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
