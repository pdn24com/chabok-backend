<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tariff_rate_rules', fn (Blueprint $table) => $table->decimal('incremental_step_kg', 12, 4)->nullable());
    }
    public function down(): void {
        if (DB::table('tariff_rate_rules')->whereNotNull('incremental_step_kg')->exists()) throw new RuntimeException('Incremental rules exist; use forward recovery to preserve pricing evidence.');
        Schema::table('tariff_rate_rules', fn (Blueprint $table) => $table->dropColumn('incremental_step_kg'));
    }
};
