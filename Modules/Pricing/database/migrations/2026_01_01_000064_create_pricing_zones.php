<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_zones', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('zone_set_version_id');
            $table->string('code', 80);
            $table->string('title', 200);
            $table->boolean('remote_area')->default('0');
            $table->unsignedInteger('rank')->nullable();
            $table->unique(['zone_set_version_id', 'code'], 'pricing_zone_code_unique');
            $table->unique(['zone_set_version_id', 'rank'], 'pricing_zone_version_rank_unique');
            $table->foreign(['zone_set_version_id'], 'pricing_zones_zone_set_version_id_foreign')->references(['id'])->on('pricing_zone_set_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_zones');
    }
};
