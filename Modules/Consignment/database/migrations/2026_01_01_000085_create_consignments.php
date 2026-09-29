<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('consignment_number', 32);
            $table->unsignedInteger('initiator_id');
            $table->unsignedInteger('pickup_node_id');
            $table->unsignedInteger('delivery_node_id')->nullable();
            $table->unsignedInteger('pickup_man_id')->nullable();
            $table->unsignedInteger('delivery_man_id')->nullable();
            $table->unsignedInteger('sender_id')->nullable();
            $table->unsignedInteger('receiver_id')->nullable();
            $table->string('sender_contact_name', 200);
            $table->string('sender_mobile', 32);
            $table->string('sender_phone', 32)->nullable();
            $table->string('sender_address_text', 1000);
            $table->string('sender_country', 120)->nullable();
            $table->string('sender_state', 160);
            $table->string('sender_city', 160);
            $table->unsignedInteger('sender_city_id')->nullable();
            $table->string('sender_postal_code', 32)->nullable();
            $table->decimal('sender_latitude', 10, 7)->nullable();
            $table->decimal('sender_longitude', 10, 7)->nullable();
            $table->string('receiver_contact_name', 200);
            $table->string('receiver_mobile', 32);
            $table->string('receiver_phone', 32)->nullable();
            $table->string('receiver_address_text', 1000);
            $table->string('receiver_country', 120)->nullable();
            $table->string('receiver_state', 160);
            $table->string('receiver_city', 160);
            $table->unsignedInteger('receiver_city_id')->nullable();
            $table->string('receiver_postal_code', 32)->nullable();
            $table->decimal('receiver_latitude', 10, 7)->nullable();
            $table->decimal('receiver_longitude', 10, 7)->nullable();
            $table->unsignedInteger('service_type_id');
            $table->unsignedInteger('shipping_method_id');
            $table->unsignedInteger('service_offering_id')->nullable();
            $table->unsignedInteger('service_offering_version_id')->nullable();
            $table->json('selected_service_option_versions')->nullable();
            $table->unsignedInteger('commitment_schedule_version_id')->nullable();
            $table->date('pickup_service_date')->nullable();
            $table->string('pickup_window_code', 80)->nullable();
            $table->string('delivery_window_code', 80)->nullable();
            $table->timestamp('pickup_commitment_at', 6)->nullable();
            $table->timestamp('pickup_commitment_start_at', 6)->nullable();
            $table->timestamp('pickup_commitment_end_at', 6)->nullable();
            $table->timestamp('delivery_commitment_at', 6)->nullable();
            $table->timestamp('delivery_commitment_start_at', 6)->nullable();
            $table->timestamp('delivery_commitment_end_at', 6)->nullable();
            $table->json('commitment_snapshot')->nullable();
            $table->decimal('weight_kg', 12, 3);
            $table->decimal('width_cm', 12, 3)->nullable();
            $table->decimal('length_cm', 12, 3)->nullable();
            $table->decimal('height_cm', 12, 3)->nullable();
            $table->unsignedBigInteger('declared_value_amount');
            $table->boolean('insurance_enabled');
            $table->unsignedBigInteger('insurance_value_amount')->nullable();
            $table->boolean('cod_enabled');
            $table->unsignedBigInteger('cod_amount')->nullable();
            $table->enum('payer', ['SENDER', 'RECEIVER', 'VENDOR']);
            $table->enum('payment_method', ['CASH', 'CREDIT', 'COD']);
            $table->enum('commercial_pricing_state', ['UNPRICED', 'QUOTED', 'LOCKED', 'STALE', 'ADJUSTED', 'VOID'])->default('UNPRICED');
            $table->unsignedInteger('active_pricing_snapshot_id')->nullable();
            $table->char('pricing_relevant_fingerprint', 64)->nullable();
            $table->string('current_status', 32);
            $table->enum('aggregate_mode', ['FULL', 'PARTIAL'])->default('FULL');
            $table->json('parcel_status_counts')->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->json('catalog_snapshot')->nullable();
            $table->json('delivery_commitment_resolution')->nullable();
            $table->unique(['consignment_number'], 'consignments_consignment_number_unique');
            $table->unique(['hq_id', 'id'], 'consignments_hq_id_unique');
            $table->index(['initiator_id'], 'consignments_initiator_id_foreign');
            $table->index(['hq_id', 'pickup_node_id', 'created_at', 'id'], 'consignments_pickup_list_index');
            $table->index(['hq_id', 'current_status', 'created_at', 'id'], 'consignments_status_list_index');
            $table->index(['hq_id', 'delivery_node_id'], 'consignments_delivery_node_index');
            $table->index(['hq_id', 'service_type_id'], 'consignments_service_index');
            $table->index(['hq_id', 'shipping_method_id'], 'consignments_shipping_index');
            $table->index(['hq_id', 'receiver_mobile'], 'consignments_receiver_mobile_index');
            $table->index(['service_offering_id'], 'consignments_service_offering_id_foreign');
            $table->index(['service_offering_version_id'], 'consignments_service_offering_version_id_foreign');
            $table->index(['active_pricing_snapshot_id'], 'consignments_active_pricing_snapshot_id_foreign');
            $table->index(['hq_id', 'service_offering_id'], 'consignments_offering_index');
            $table->index(['hq_id', 'commercial_pricing_state'], 'consignments_pricing_state_index');
            $table->index(['sender_city_id'], 'consignments_sender_city_index');
            $table->index(['receiver_city_id'], 'consignments_receiver_city_index');
            $table->index(['commitment_schedule_version_id'], 'consignments_commitment_schedule_fk');
            $table->index(['hq_id', 'commitment_schedule_version_id'], 'consignments_commitment_schedule_index');
            $table->index(['hq_id', 'current_status', 'aggregate_mode', 'created_at', 'id'], 'consignments_aggregate_status_list_index');
            $table->foreign(['active_pricing_snapshot_id'], 'consignments_active_pricing_snapshot_id_foreign')->references(['id'])->on('pricing_snapshots')->onDelete('restrict');
            $table->foreign(['commitment_schedule_version_id'], 'consignments_commitment_schedule_fk')->references(['id'])->on('commitment_schedule_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'delivery_node_id'], 'consignments_delivery_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'pickup_node_id'], 'consignments_pickup_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id'], 'consignments_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['initiator_id'], 'consignments_initiator_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['receiver_city_id'], 'consignments_receiver_city_id_foreign')->references(['id'])->on('cities')->onDelete('restrict');
            $table->foreign(['sender_city_id'], 'consignments_sender_city_id_foreign')->references(['id'])->on('cities')->onDelete('restrict');
            $table->foreign(['service_offering_id'], 'consignments_service_offering_id_foreign')->references(['id'])->on('service_offerings')->onDelete('restrict');
            $table->foreign(['service_offering_version_id'], 'consignments_service_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignments');
    }
};
