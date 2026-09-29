<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_charge_lines', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pricing_snapshot_id');
            $table->unsignedSmallInteger('line_number');
            $table->unsignedInteger('charge_type_id');
            $table->unsignedInteger('rate_rule_id');
            $table->string('charge_type_code', 80);
            $table->string('title', 200);
            $table->string('calculation_method', 30);
            $table->string('basis', 80);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_rate', 18, 6)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('accounting_mapping_key', 120);
            $table->json('explanation');
            $table->unique(['pricing_snapshot_id', 'line_number'], 'pricing_charge_lines_line_number_unique');
            $table->index(['charge_type_id'], 'pricing_charge_lines_charge_type_id_foreign');
            $table->index(['rate_rule_id'], 'pricing_charge_lines_rate_rule_id_foreign');
            $table->foreign(['charge_type_id'], 'pricing_charge_lines_charge_type_id_foreign')->references(['id'])->on('pricing_charge_types')->onDelete('restrict');
            $table->foreign(['pricing_snapshot_id'], 'pricing_charge_lines_pricing_snapshot_id_foreign')->references(['id'])->on('pricing_snapshots')->onDelete('cascade');
            $table->foreign(['rate_rule_id'], 'pricing_charge_lines_rate_rule_id_foreign')->references(['id'])->on('tariff_rate_rules')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_charge_lines');
    }
};
