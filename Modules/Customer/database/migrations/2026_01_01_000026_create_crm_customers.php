<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customers', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->enum('kind', ['PERSON', 'COMPANY']);
            $table->enum('phase', ['LEAD', 'CUSTOMER']);
            $table->enum('lifecycle', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])->default('ACTIVE');
            // Operator-entered code, independent of id; empty is allowed while the record is still a lead.
            $table->string('customer_code', 80)->nullable();
            $table->string('display_name', 200)->nullable();
            $table->string('first_name', 120)->nullable();
            $table->string('family_name', 120)->nullable();
            $table->timestamp('converted_at', 6)->nullable();
            $table->unsignedInteger('assignee_id');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'customer_code'], 'crm_customers_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'crm_customers_hq_internal_id_unique');
            $table->index(['hq_id', 'phase', 'lifecycle'], 'crm_customers_hq_phase_lifecycle_index');
            $table->index(['hq_id', 'assignee_id', 'phase'], 'crm_customers_hq_assignee_phase_index');
            $table->index(['hq_id', 'kind', 'lifecycle'], 'crm_customers_hq_kind_lifecycle_index');
            $table->index(['created_by'], 'crm_customers_created_by_fk');
            $table->foreign(['assignee_id'], 'crm_customers_assignee_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'crm_customers_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_customers_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customers');
    }
};
