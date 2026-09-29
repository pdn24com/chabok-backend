<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_zone_members', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pricing_zone_id');
            $table->enum('member_type', ['EXPLICIT_OVERRIDE', 'POSTAL_RANGE', 'CITY', 'PROVINCE', 'POLYGON']);
            $table->string('reference_value', 200);
            $table->unsignedInteger('city_id')->nullable();
            $table->unsignedInteger('province_id')->nullable();
            $table->string('range_end', 200)->nullable();
            $table->unsignedSmallInteger('precedence');
            $table->json('geometry')->nullable();
            $table->index(['pricing_zone_id'], 'pricing_zone_members_pricing_zone_id_foreign');
            $table->index(['member_type', 'reference_value', 'range_end', 'precedence'], 'pricing_zone_member_resolution_index');
            $table->index(['city_id'], 'pricing_zone_members_city_id_foreign');
            $table->index(['province_id'], 'pricing_zone_members_province_id_foreign');
            $table->index(['member_type', 'city_id', 'province_id'], 'pricing_zone_member_geography_index');
            $table->foreign(['city_id'], 'pricing_zone_members_city_id_foreign')->references(['id'])->on('cities')->onDelete('restrict');
            $table->foreign(['pricing_zone_id'], 'pricing_zone_members_pricing_zone_id_foreign')->references(['id'])->on('pricing_zones')->onDelete('cascade');
            $table->foreign(['province_id'], 'pricing_zone_members_province_id_foreign')->references(['id'])->on('provinces')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_zone_members');
    }
};
