<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_catalog_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('code', 80);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'code'], 'crm_catalog_categories_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'crm_catalog_categories_hq_internal_id_unique');
            $table->index(['hq_id', 'is_active', 'sort_order'], 'crm_catalog_categories_hq_active_order_index');
            $table->index(['hq_id', 'parent_id'], 'crm_catalog_categories_parent_fk');
            $table->index(['created_by'], 'crm_catalog_categories_created_by_fk');
            $table->foreign(['created_by'], 'crm_catalog_categories_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'parent_id'], 'crm_catalog_categories_parent_fk')->references(['hq_id', 'id'])->on('crm_catalog_categories')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_catalog_categories_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_catalog_categories');
    }
};
