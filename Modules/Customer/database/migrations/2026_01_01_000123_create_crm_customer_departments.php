<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Department chart of a customer company, not of Chabok staff. A PERSON record has no chart; the relations a
// person holds with companies take that role instead.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_departments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('company_customer_id');
            // Must belong to the same company; cycles are forbidden and depth is a UX limit, not a schema one.
            $table->unsignedInteger('parent_department_id')->nullable();
            $table->string('title', 200);
            $table->string('cost_center_code', 80)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_customer_departments_hq_internal_id_unique');
            $table->index(['hq_id', 'company_customer_id'], 'crm_customer_departments_company_fk');
            $table->index(['hq_id', 'parent_department_id'], 'crm_customer_departments_parent_fk');
            $table->index(['created_by'], 'crm_customer_departments_created_by_fk');
            $table->foreign(['created_by'], 'crm_customer_departments_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'company_customer_id'], 'crm_customer_departments_company_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'parent_department_id'], 'crm_customer_departments_parent_fk')->references(['hq_id', 'id'])->on('crm_customer_departments')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_customer_departments_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_departments');
    }
};
