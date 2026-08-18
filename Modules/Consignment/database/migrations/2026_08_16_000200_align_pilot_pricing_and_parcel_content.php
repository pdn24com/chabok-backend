<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignments', function (Blueprint $table): void {
            $table->char('commitment_schedule_version_id', 36)->nullable()->after('selected_service_option_versions');
            $table->date('pickup_service_date')->nullable()->after('commitment_schedule_version_id');
            $table->string('pickup_window_code', 80)->nullable()->after('pickup_service_date');
            $table->string('delivery_window_code', 80)->nullable()->after('pickup_window_code');
            $table->timestamp('pickup_commitment_start_at', 6)->nullable()->after('pickup_commitment_at');
            $table->timestamp('pickup_commitment_end_at', 6)->nullable()->after('pickup_commitment_start_at');
            $table->timestamp('delivery_commitment_start_at', 6)->nullable()->after('delivery_commitment_at');
            $table->timestamp('delivery_commitment_end_at', 6)->nullable()->after('delivery_commitment_start_at');
            $table->json('commitment_snapshot')->nullable()->after('delivery_commitment_end_at');
            $table->foreign('commitment_schedule_version_id', 'consignments_commitment_schedule_fk')
                ->references('commitment_schedule_version_id')->on('commitment_schedule_versions')->restrictOnDelete();
            $table->index(['hq_id', 'commitment_schedule_version_id'], 'consignments_commitment_schedule_index');
        });

        Schema::table('parcels', function (Blueprint $table): void {
            // Nullable preserves historical rows; all new pilot create requests require a value.
            $table->string('content_description', 500)->nullable()->after('current_status');
        });

        Schema::table('consignment_pricing_charge_lines', function (Blueprint $table): void {
            // Existing provider lines stay readable; new internal lines fill the exact evidence.
            $table->string('category', 30)->nullable()->after('title');
            $table->string('calculation_method', 30)->nullable()->after('category');
            $table->string('basis', 80)->nullable()->after('calculation_method');
            $table->decimal('quantity', 18, 4)->nullable()->after('basis');
            $table->decimal('unit_rate', 18, 6)->nullable()->after('quantity');
            $table->json('explanation')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_pricing_charge_lines', function (Blueprint $table): void {
            $table->dropColumn(['category', 'calculation_method', 'basis', 'quantity', 'unit_rate', 'explanation']);
        });
        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropColumn('content_description');
        });
        Schema::table('consignments', function (Blueprint $table): void {
            $table->dropForeign('consignments_commitment_schedule_fk');
            $table->dropIndex('consignments_commitment_schedule_index');
            $table->dropColumn([
                'commitment_schedule_version_id', 'pickup_service_date', 'pickup_window_code',
                'delivery_window_code', 'pickup_commitment_start_at', 'pickup_commitment_end_at',
                'delivery_commitment_start_at', 'delivery_commitment_end_at', 'commitment_snapshot',
            ]);
        });
    }
};
