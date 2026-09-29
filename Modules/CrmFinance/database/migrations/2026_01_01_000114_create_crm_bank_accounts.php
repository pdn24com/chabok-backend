<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_bank_accounts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            $table->string('bank_name', 120);
            // Sensitive values; the read side masks them and none of them identifies the person.
            $table->string('iban', 34)->nullable();
            $table->string('card_number', 24)->nullable();
            $table->string('account_no', 40)->nullable();
            $table->boolean('is_primary')->default('0');
            $table->string('status', 40);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_bank_accounts_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id', 'status'], 'crm_bank_accounts_customer_status_index');
            $table->index(['hq_id', 'customer_id', 'is_primary'], 'crm_bank_accounts_customer_primary_index');
            $table->index(['created_by'], 'crm_bank_accounts_created_by_fk');
            $table->foreign(['created_by'], 'crm_bank_accounts_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_bank_accounts_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_bank_accounts_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_bank_accounts');
    }
};
