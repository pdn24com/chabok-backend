<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('province_id');
            $table->string('legacy_city_code', 20);
            $table->string('name_fa', 200);
            $table->string('normalized_name', 200);
            $table->boolean('is_active')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['legacy_city_code'], 'cities_legacy_city_code_unique');
            $table->index(['province_id', 'is_active', 'normalized_name'], 'cities_province_active_name_index');
            $table->index(['is_active', 'normalized_name'], 'cities_active_name_index');
            $table->foreign(['province_id'], 'cities_province_id_foreign')->references(['id'])->on('provinces')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
