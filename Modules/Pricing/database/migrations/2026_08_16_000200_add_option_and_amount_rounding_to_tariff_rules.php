<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariff_rate_rules', function (Blueprint $table): void {
            $table->char('service_option_version_id', 36)->nullable()->after('service_offering_version_id');
            $table->enum('amount_rounding_mode', ['NONE', 'CEIL', 'FLOOR', 'HALF_UP'])->default('NONE')->after('maximum_amount');
            $table->unsignedBigInteger('amount_rounding_step')->nullable()->after('amount_rounding_mode');
            $table->foreign('service_option_version_id', 'tariff_rule_service_option_fk')
                ->references('service_option_version_id')->on('service_option_versions')->restrictOnDelete();
            $table->index(['tariff_version_id', 'service_offering_version_id', 'service_option_version_id', 'priority'], 'tariff_rule_option_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('tariff_rate_rules', function (Blueprint $table): void {
            $table->dropForeign('tariff_rule_service_option_fk');
            $table->dropIndex('tariff_rule_option_lookup_index');
            $table->dropColumn(['service_option_version_id', 'amount_rounding_mode', 'amount_rounding_step']);
        });
    }
};
