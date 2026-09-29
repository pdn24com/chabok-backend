<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_catalog_items', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('code', 80);
            $table->string('title', 200);
            $table->enum('kind', ['GOOD', 'SERVICE']);
            // Reserved logical references; crm_lookup_values is still an open owner decision, so no physical key yet.
            $table->unsignedInteger('unit_id')->nullable();
            $table->unsignedInteger('sales_commitment_id')->nullable();
            $table->unsignedInteger('after_sales_policy_id')->nullable();
            $table->unsignedInteger('sla_template_id')->nullable();
            $table->text('description')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('lead_time')->nullable();
            $table->text('after_sales_policy')->nullable();
            $table->text('sla_description')->nullable();
            $table->text('warranty_description')->nullable();
            $table->text('legal_notes')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])->default('ACTIVE');
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('buyer_persona_id')->nullable();
            $table->unsignedInteger('sales_model_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'code'], 'crm_catalog_items_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'crm_catalog_items_hq_internal_id_unique');
            $table->index(['hq_id', 'kind', 'status'], 'crm_catalog_items_hq_kind_status_index');
            $table->index(['hq_id', 'category_id'], 'crm_catalog_items_category_fk');
            $table->index(['hq_id', 'buyer_persona_id'], 'crm_catalog_items_buyer_persona_fk');
            $table->index(['hq_id', 'sales_model_id'], 'crm_catalog_items_sales_model_fk');
            $table->index(['created_by'], 'crm_catalog_items_created_by_fk');
            $table->foreign(['created_by'], 'crm_catalog_items_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'buyer_persona_id'], 'crm_catalog_items_buyer_persona_fk')->references(['hq_id', 'id'])->on('crm_catalog_personas')->onDelete('restrict');
            $table->foreign(['hq_id', 'category_id'], 'crm_catalog_items_category_fk')->references(['hq_id', 'id'])->on('crm_catalog_categories')->onDelete('restrict');
            $table->foreign(['hq_id', 'sales_model_id'], 'crm_catalog_items_sales_model_fk')->references(['hq_id', 'id'])->on('crm_catalog_sales_models')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_catalog_items_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_catalog_items');
    }
};
