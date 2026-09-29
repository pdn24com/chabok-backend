<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_pricing_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pricing_snapshot_id')->nullable();
            $table->unsignedInteger('service_offering_version_id')->nullable();
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('version_number');
            $table->string('provider_code', 40);
            $table->string('quote_id', 64);
            $table->unsignedInteger('quote_version');
            $table->string('option_id', 64);
            $table->string('external_method_code', 120);
            $table->string('method_name', 240);
            $table->string('external_price_list_code', 120)->nullable();
            $table->string('zone', 120)->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('min_ins')->nullable();
            $table->json('delivery_windows');
            $table->char('input_fingerprint', 64);
            $table->char('result_fingerprint', 64)->nullable();
            $table->timestamp('provider_calculated_at', 6);
            $table->timestamp('accepted_at', 6);
            $table->unsignedInteger('accepted_by');
            $table->unique(['hq_id', 'consignment_id', 'version_number'], 'pricing_consignment_version_unique');
            $table->unique(['hq_id', 'quote_id', 'quote_version', 'option_id'], 'pricing_quote_option_unique');
            $table->unique(['hq_id', 'id'], 'pricing_hq_id_unique');
            $table->index(['accepted_by'], 'consignment_pricing_versions_accepted_by_foreign');
            $table->index(['hq_id', 'consignment_id', 'accepted_at'], 'pricing_timeline_index');
            $table->index(['pricing_snapshot_id'], 'consignment_pricing_versions_pricing_snapshot_id_foreign');
            $table->index(['service_offering_version_id'], 'consignment_pricing_versions_service_offering_version_id_foreign');
            $table->foreign(['accepted_by'], 'consignment_pricing_versions_accepted_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'consignment_id'], 'pricing_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['pricing_snapshot_id'], 'consignment_pricing_versions_pricing_snapshot_id_foreign')->references(['id'])->on('pricing_snapshots')->onDelete('restrict');
            $table->foreign(['service_offering_version_id'], 'consignment_pricing_versions_service_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_pricing_versions');
    }
};
