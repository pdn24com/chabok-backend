<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table): void {
            $table->char('province_id', 36)->primary();
            $table->string('legacy_province_code', 4)->unique();
            $table->string('name_fa', 160);
            $table->string('normalized_name', 160);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->index(['is_active', 'normalized_name'], 'provinces_active_name_index');
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->char('city_id', 36)->primary();
            $table->char('province_id', 36);
            $table->string('legacy_city_code', 20)->unique();
            $table->string('name_fa', 200);
            $table->string('normalized_name', 200);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->foreign('province_id')->references('province_id')->on('provinces')->restrictOnDelete();
            $table->index(['province_id', 'is_active', 'normalized_name'], 'cities_province_active_name_index');
            $table->index(['is_active', 'normalized_name'], 'cities_active_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
