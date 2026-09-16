<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariff_families', function (Blueprint $table): void {
            $table->string('title', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tariff_families', function (Blueprint $table): void {
            $table->dropColumn('title');
        });
    }
};
