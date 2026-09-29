<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_pricing_charge_lines', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('pricing_version_id');
            $table->unsignedTinyInteger('line_number');
            $table->string('charge_code', 80);
            $table->string('title', 200);
            $table->string('category', 30)->nullable();
            $table->string('calculation_method', 30)->nullable();
            $table->string('basis', 80)->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->decimal('unit_rate', 18, 6)->nullable();
            $table->unsignedBigInteger('amount');
            $table->json('explanation')->nullable();
            $table->unique(['pricing_version_id', 'charge_code'], 'pricing_lines_code_unique');
            $table->unique(['pricing_version_id', 'line_number'], 'pricing_lines_order_unique');
            $table->index(['hq_id', 'pricing_version_id'], 'pricing_lines_version_fk');
            $table->foreign(['hq_id', 'pricing_version_id'], 'pricing_lines_version_fk')->references(['hq_id', 'id'])->on('consignment_pricing_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_pricing_charge_lines');
    }
};
