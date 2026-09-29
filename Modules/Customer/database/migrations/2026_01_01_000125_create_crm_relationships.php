<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Both ends reach crm_customers; the PERSON and COMPANY kinds are checked. A person may hold relations
// with several companies, and ending a relation removes neither the identity nor the contact points.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_relationships', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('person_customer_id');
            $table->unsignedInteger('company_customer_id');
            // Optional; when chosen it must belong to the same company. The textual role title stays required.
            $table->unsignedInteger('position_id')->nullable();
            $table->string('role_title', 200);
            $table->string('decision_level', 80)->nullable();
            $table->string('signing_authority', 200)->nullable();
            // An unknown date stays null and is never fabricated; created_at is not the start of the job.
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            // At most one active primary contact per company; zero is allowed, it is not a completion rule.
            $table->boolean('is_primary')->default('0');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_relationships_hq_internal_id_unique');
            $table->index(['hq_id', 'company_customer_id', 'is_primary'], 'crm_relationships_company_primary_index');
            $table->index(['hq_id', 'person_customer_id'], 'crm_relationships_person_fk');
            $table->index(['hq_id', 'position_id'], 'crm_relationships_position_fk');
            $table->index(['created_by'], 'crm_relationships_created_by_fk');
            $table->foreign(['created_by'], 'crm_relationships_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'company_customer_id'], 'crm_relationships_company_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'person_customer_id'], 'crm_relationships_person_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'position_id'], 'crm_relationships_position_fk')->references(['hq_id', 'id'])->on('crm_positions')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_relationships_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_relationships');
    }
};
