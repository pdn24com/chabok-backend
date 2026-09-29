<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A contact point always belongs to a PERSON record; a company has no direct channel of its own here.
// Replaces crm_mobile_claims: the "one number, one person" rule survives as an atomic write-path check,
// because a plain UNIQUE on normalized_value does not cover every channel. The final lock and constraint
// are still an open physical-design decision, so no uniqueness on the number is declared yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contact_points', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('customer_id');
            // Optional organisational context, the same person and company as the relation itself.
            $table->unsignedInteger('relationship_id')->nullable();
            $table->enum('type', ['MOBILE', 'PHONE', 'INSTAGRAM', 'WHATSAPP', 'TELEGRAM', 'BALE', 'EMAIL', 'ADDRESS_REFERENCE']);
            $table->string('value', 320);
            // Normalised and validated per platform; the same number may repeat across channels of one person.
            $table->string('normalized_value', 320);
            $table->enum('scope', ['PERSONAL', 'WORK']);
            $table->boolean('is_default')->default('0');
            // A manual review note only; it implies no external verification and issues no OTP.
            $table->timestamp('verified_manually_at', 6)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('priority')->nullable();
            $table->string('subtype', 80)->nullable();
            $table->string('work_context', 200)->nullable();
            $table->enum('identifier_kind', ['PHONE', 'USERNAME', 'EMAIL', 'ADDRESS']);
            // A person address is a reference to crm_customer_address, never a parallel address payload.
            $table->unsignedInteger('address_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_contact_points_hq_internal_id_unique');
            $table->index(['hq_id', 'customer_id', 'type', 'status'], 'crm_contact_points_customer_type_index');
            $table->index(['hq_id', 'normalized_value', 'type'], 'crm_contact_points_normalized_value_index');
            $table->index(['hq_id', 'relationship_id'], 'crm_contact_points_relationship_fk');
            $table->index(['hq_id', 'address_id'], 'crm_contact_points_address_fk');
            $table->index(['created_by'], 'crm_contact_points_created_by_fk');
            $table->foreign(['created_by'], 'crm_contact_points_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'address_id'], 'crm_contact_points_address_fk')->references(['hq_id', 'id'])->on('crm_customer_address')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_contact_points_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id', 'relationship_id'], 'crm_contact_points_relationship_fk')->references(['hq_id', 'id'])->on('crm_relationships')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_contact_points_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_points');
    }
};
