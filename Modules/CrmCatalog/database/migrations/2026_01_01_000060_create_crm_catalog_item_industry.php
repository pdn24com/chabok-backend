<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_catalog_item_industry', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('catalog_item_id');
            $table->unsignedInteger('industry_id');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'catalog_item_id', 'industry_id'], 'crm_catalog_item_industry_pair_unique');
            $table->index(['hq_id', 'industry_id'], 'crm_catalog_item_industry_industry_fk');
            $table->index(['created_by'], 'crm_catalog_item_industry_created_by_fk');
            $table->foreign(['created_by'], 'crm_catalog_item_industry_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'catalog_item_id'], 'crm_catalog_item_industry_item_fk')->references(['hq_id', 'id'])->on('crm_catalog_items')->onDelete('restrict');
            $table->foreign(['hq_id', 'industry_id'], 'crm_catalog_item_industry_industry_fk')->references(['hq_id', 'id'])->on('crm_industries')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_catalog_item_industry_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_catalog_item_industry');
    }
};
