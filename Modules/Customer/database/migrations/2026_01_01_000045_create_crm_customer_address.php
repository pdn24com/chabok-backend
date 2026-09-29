<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customer_address', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            $table->string('country_code', 2);
            $table->unsignedInteger('province_id')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->string('foreign_region', 200)->nullable();
            $table->string('foreign_city', 200)->nullable();
            // Required once the address form is complete; the column stays nullable for partial leads.
            $table->text('address_text')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('purpose', 60);
            $table->boolean('is_default')->default('0');
            // Text, so a leading zero or a letter in a plaque or unit number survives.
            $table->string('plaque', 40)->nullable();
            $table->string('unit', 40)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            // Needed so a contact point of kind ADDRESS_REFERENCE can carry a tenant-safe foreign key.
            $table->unique(['hq_id', 'id'], 'crm_customer_address_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id', 'is_default'], 'crm_customer_address_customer_default_index');
            $table->index(['hq_id', 'customer_id', 'purpose'], 'crm_customer_address_customer_purpose_index');
            $table->index(['province_id'], 'crm_customer_address_province_fk');
            $table->index(['city_id'], 'crm_customer_address_city_fk');
            $table->index(['created_by'], 'crm_customer_address_created_by_fk');
            $table->foreign(['city_id'], 'crm_customer_address_city_fk')->references(['id'])->on('cities')->onDelete('restrict');
            $table->foreign(['created_by'], 'crm_customer_address_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_customer_address_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_customer_address_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['province_id'], 'crm_customer_address_province_fk')->references(['id'])->on('provinces')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_customer_address');
    }
};
