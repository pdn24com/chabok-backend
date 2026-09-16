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
        Schema::table('tariff_versions', function (Blueprint $table): void {
            $table->enum('zone_policy', ['DIRECTIONAL', 'HIGHER_ZONE_RANK'])->default('DIRECTIONAL');
            $table->json('freight_matrices')->nullable();
        });
        Schema::table('pricing_zones', function (Blueprint $table): void {
            $table->unsignedInteger('rank')->nullable();
            $table->unique(['zone_set_version_id', 'rank'], 'pricing_zone_version_rank_unique');
        });
        Schema::table('tariff_rate_rules', function (Blueprint $table): void {
            $table->char('matrix_cell_id', 36)->nullable();
            $table->boolean('taxable')->nullable();
            $table->unique(['tariff_version_id', 'matrix_cell_id'], 'tariff_matrix_cell_unique');
        });
        DB::unprepared("CREATE TRIGGER tariff_v2_published_update BEFORE UPDATE ON tariff_versions FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (NEW.zone_policy <=> OLD.zone_policy AND NEW.freight_matrices <=> OLD.freight_matrices) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing V2 content'; END IF; END");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS tariff_v2_published_update');
        Schema::table('tariff_rate_rules', function (Blueprint $table): void { $table->dropUnique('tariff_matrix_cell_unique'); $table->dropColumn(['matrix_cell_id', 'taxable']); });
        Schema::table('pricing_zones', function (Blueprint $table): void { $table->dropUnique('pricing_zone_version_rank_unique'); $table->dropColumn('rank'); });
        Schema::table('tariff_versions', function (Blueprint $table): void { $table->dropColumn(['zone_policy', 'freight_matrices']); });
    }
};
