<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sales_funnel_steps', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('funnel_id');
            $table->string('code', 80);
            $table->string('title', 200);
            $table->unsignedInteger('sort_order');
            // Business result stays readable independently of the step title.
            $table->enum('outcome_type', ['OPEN', 'WON', 'LOST'])->default('OPEN');
            // A consumed step is deactivated instead of deleted.
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'funnel_id', 'code'], 'crm_sales_funnel_steps_code_unique');
            $table->unique(['hq_id', 'funnel_id', 'sort_order'], 'crm_sales_funnel_steps_order_unique');
            $table->unique(['hq_id', 'id'], 'crm_sales_funnel_steps_hq_internal_id_unique');
            $table->index(['hq_id', 'funnel_id', 'is_active'], 'crm_sales_funnel_steps_funnel_active_index');
            $table->index(['created_by'], 'crm_sales_funnel_steps_created_by_fk');
            $table->foreign(['created_by'], 'crm_sales_funnel_steps_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'funnel_id'], 'crm_sales_funnel_steps_funnel_fk')->references(['hq_id', 'id'])->on('crm_sales_funnel')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_sales_funnel_steps_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_funnel_steps');
    }
};
