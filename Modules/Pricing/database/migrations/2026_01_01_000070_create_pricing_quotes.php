<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_quotes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('requested_by');
            $table->enum('purpose', ['SALES', 'PURCHASE', 'COMMISSION', 'INTERNAL_TRANSFER']);
            $table->unsignedInteger('tariff_version_id');
            $table->unsignedInteger('zone_set_version_id');
            $table->unsignedInteger('service_offering_id');
            $table->unsignedInteger('service_offering_version_id');
            $table->unsignedInteger('origin_zone_id');
            $table->unsignedInteger('destination_zone_id');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total_amount');
            $table->json('normalized_input');
            $table->json('resolution_evidence');
            $table->json('warnings');
            $table->char('input_fingerprint', 64);
            $table->char('result_fingerprint', 64);
            $table->string('idempotency_key', 120);
            $table->enum('status', ['OFFERED', 'ACCEPTED', 'EXPIRED', 'SUPERSEDED', 'REJECTED', 'VOID'])->default('OFFERED');
            $table->timestamp('calculated_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('accepted_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'requested_by', 'idempotency_key'], 'pricing_quote_idempotency_unique');
            $table->index(['requested_by'], 'pricing_quotes_requested_by_foreign');
            $table->index(['tariff_version_id'], 'pricing_quotes_tariff_version_id_foreign');
            $table->index(['zone_set_version_id'], 'pricing_quotes_zone_set_version_id_foreign');
            $table->index(['service_offering_id'], 'pricing_quotes_service_offering_id_foreign');
            $table->index(['service_offering_version_id'], 'pricing_quotes_service_offering_version_id_foreign');
            $table->index(['origin_zone_id'], 'pricing_quotes_origin_zone_id_foreign');
            $table->index(['destination_zone_id'], 'pricing_quotes_destination_zone_id_foreign');
            $table->index(['hq_id', 'status', 'expires_at'], 'pricing_quote_status_index');
            $table->foreign(['destination_zone_id'], 'pricing_quotes_destination_zone_id_foreign')->references(['id'])->on('pricing_zones')->onDelete('restrict');
            $table->foreign(['hq_id'], 'pricing_quotes_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['origin_zone_id'], 'pricing_quotes_origin_zone_id_foreign')->references(['id'])->on('pricing_zones')->onDelete('restrict');
            $table->foreign(['requested_by'], 'pricing_quotes_requested_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['service_offering_id'], 'pricing_quotes_service_offering_id_foreign')->references(['id'])->on('service_offerings')->onDelete('restrict');
            $table->foreign(['service_offering_version_id'], 'pricing_quotes_service_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
            $table->foreign(['tariff_version_id'], 'pricing_quotes_tariff_version_id_foreign')->references(['id'])->on('tariff_versions')->onDelete('restrict');
            $table->foreign(['zone_set_version_id'], 'pricing_quotes_zone_set_version_id_foreign')->references(['id'])->on('pricing_zone_set_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_quotes');
    }
};
