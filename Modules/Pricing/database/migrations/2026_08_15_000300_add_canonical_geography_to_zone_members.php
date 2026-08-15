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
        DB::statement("ALTER TABLE pricing_zone_members MODIFY member_type ENUM('EXPLICIT_OVERRIDE','POSTAL_RANGE','CITY','PROVINCE') NOT NULL");
        Schema::table('pricing_zone_members', function (Blueprint $table): void {
            $table->char('city_id', 36)->nullable()->after('reference_value');
            $table->char('province_id', 36)->nullable()->after('city_id');
            $table->foreign('city_id')->references('city_id')->on('cities')->restrictOnDelete();
            $table->foreign('province_id')->references('province_id')->on('provinces')->restrictOnDelete();
            $table->index(['member_type', 'city_id', 'province_id'], 'pricing_zone_member_geography_index');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_zone_members', function (Blueprint $table): void {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['province_id']);
            $table->dropIndex('pricing_zone_member_geography_index');
            $table->dropColumn(['city_id', 'province_id']);
        });
        // Keep the expanded enum on rollback so existing PROVINCE rows cannot make rollback fail.
    }
};
