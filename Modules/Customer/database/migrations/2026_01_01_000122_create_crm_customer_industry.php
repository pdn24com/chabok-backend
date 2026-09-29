<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Customer side of the shared industry knowledge base. Replaces crm_customer_classifications: only the
// industry relation survived, non-industry classifications are not converted into industries automatically.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_industry', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            // A lead or a customer, natural or legal person alike.
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('industry_id');
            // One customer may carry several industries, at most one of them primary.
            $table->boolean('is_primary')->default('0');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'customer_id', 'industry_id'], 'crm_customer_industry_pair_unique');
            $table->index(['hq_id', 'industry_id'], 'crm_customer_industry_industry_fk');
            $table->index(['created_by'], 'crm_customer_industry_created_by_fk');
            $table->foreign(['created_by'], 'crm_customer_industry_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_customer_industry_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'industry_id'], 'crm_customer_industry_industry_fk')->references(['hq_id', 'id'])->on('crm_industries')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_customer_industry_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_industry');
    }
};
