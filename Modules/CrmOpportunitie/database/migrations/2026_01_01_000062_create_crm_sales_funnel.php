<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sales_funnel', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('code', 80);
            $table->string('title', 200);
            $table->text('description')->nullable();
            // Zero or one commercial catalog item per funnel, restricted to kind=SERVICE.
            $table->unsignedInteger('catalog_item_id')->nullable();
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'code'], 'crm_sales_funnel_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'crm_sales_funnel_hq_internal_id_unique');
            $table->index(['hq_id', 'is_active'], 'crm_sales_funnel_hq_active_index');
            $table->index(['hq_id', 'catalog_item_id'], 'crm_sales_funnel_catalog_item_fk');
            $table->index(['created_by'], 'crm_sales_funnel_created_by_fk');
            $table->foreign(['created_by'], 'crm_sales_funnel_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'catalog_item_id'], 'crm_sales_funnel_catalog_item_fk')->references(['hq_id', 'id'])->on('crm_catalog_items')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_sales_funnel_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_sales_funnel');
    }
};
