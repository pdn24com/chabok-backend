<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignments', function (Blueprint $table): void {
            $table->char('service_offering_id', 36)->nullable()->after('shipping_method_id');
            $table->char('service_offering_version_id', 36)->nullable()->after('service_offering_id');
            $table->json('selected_service_option_versions')->nullable()->after('service_offering_version_id');
            $table->enum('commercial_pricing_state', ['UNPRICED', 'QUOTED', 'LOCKED', 'STALE', 'ADJUSTED', 'VOID'])
                ->default('UNPRICED')->after('payment_method');
            $table->char('active_pricing_snapshot_id', 36)->nullable()->after('commercial_pricing_state');
            $table->char('pricing_relevant_fingerprint', 64)->nullable()->after('active_pricing_snapshot_id');
            $table->foreign('service_offering_id')->references('service_offering_id')->on('service_offerings')->restrictOnDelete();
            $table->foreign('service_offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->restrictOnDelete();
            $table->foreign('active_pricing_snapshot_id')->references('pricing_snapshot_id')->on('pricing_snapshots')->restrictOnDelete();
            $table->index(['hq_id', 'service_offering_id'], 'consignments_offering_index');
            $table->index(['hq_id', 'commercial_pricing_state'], 'consignments_pricing_state_index');
        });
        Schema::table('consignment_pricing_versions', function (Blueprint $table): void {
            $table->char('pricing_snapshot_id', 36)->nullable()->after('pricing_version_id');
            $table->char('service_offering_version_id', 36)->nullable()->after('pricing_snapshot_id');
            $table->char('result_fingerprint', 64)->nullable()->after('input_fingerprint');
            $table->foreign('pricing_snapshot_id')->references('pricing_snapshot_id')->on('pricing_snapshots')->restrictOnDelete();
            $table->foreign('service_offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->restrictOnDelete();
        });
        DB::statement("UPDATE consignments c SET c.commercial_pricing_state = 'LOCKED', c.pricing_relevant_fingerprint = (SELECT pv.input_fingerprint FROM consignment_pricing_versions pv WHERE pv.hq_id = c.hq_id AND pv.consignment_id = c.consignment_id ORDER BY pv.version_number DESC LIMIT 1) WHERE EXISTS (SELECT 1 FROM consignment_pricing_versions pv WHERE pv.hq_id = c.hq_id AND pv.consignment_id = c.consignment_id)");
    }

    public function down(): void
    {
        Schema::table('consignment_pricing_versions', function (Blueprint $table): void {
            $table->dropForeign(['pricing_snapshot_id']);
            $table->dropForeign(['service_offering_version_id']);
            $table->dropColumn(['pricing_snapshot_id', 'service_offering_version_id', 'result_fingerprint']);
        });
        Schema::table('consignments', function (Blueprint $table): void {
            $table->dropForeign(['service_offering_id']);
            $table->dropForeign(['service_offering_version_id']);
            $table->dropForeign(['active_pricing_snapshot_id']);
            $table->dropIndex('consignments_offering_index');
            $table->dropIndex('consignments_pricing_state_index');
            $table->dropColumn(['service_offering_id', 'service_offering_version_id', 'selected_service_option_versions', 'commercial_pricing_state', 'active_pricing_snapshot_id', 'pricing_relevant_fingerprint']);
        });
    }
};
