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
        DB::statement("ALTER TABLE pricing_zone_members MODIFY member_type ENUM('EXPLICIT_OVERRIDE','POSTAL_RANGE','CITY','PROVINCE','POLYGON') NOT NULL");
        Schema::table('pricing_zone_members', function (Blueprint $table): void {
            $table->json('geometry')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('pricing_zone_members')->where('member_type', 'POLYGON')->exists()) {
            throw new RuntimeException('Polygon members exist; preserve historical geometry and use forward recovery.');
        }
        Schema::table('pricing_zone_members', fn (Blueprint $table) => $table->dropColumn('geometry'));
        // Expanded enum is harmless and is retained, as in the Geography migration.
    }
};
