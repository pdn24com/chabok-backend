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
        Schema::table('areas', function (Blueprint $table): void {
            $table->string('area_code', 80)->nullable()->after('hq_id');
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->unique(['hq_id', 'area_code'], 'areas_hq_code_unique');
        });
        DB::table('areas')->whereNull('area_code')->orderBy('area_id')->each(function ($area): void {
            DB::table('areas')->where('area_id', $area->area_id)->update([
                'area_code' => 'AREA-'.strtoupper(substr(str_replace('-', '', (string) $area->area_id), 0, 12)),
            ]);
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE areas MODIFY area_code VARCHAR(80) NOT NULL DEFAULT ''");
            DB::unprepared(<<<'SQL'
            CREATE TRIGGER areas_assign_code_before_insert
            BEFORE INSERT ON areas FOR EACH ROW
            BEGIN
                IF NEW.area_code IS NULL OR NEW.area_code = '' THEN
                    SET NEW.area_code = CONCAT('AREA-', UPPER(LEFT(REPLACE(NEW.area_id, '-', ''), 12)));
                END IF;
            END
            SQL);
        }

        Schema::table('area_hierarchies', function (Blueprint $table): void {
            $table->unique(['hq_id', 'child_area_id'], 'area_hierarchy_child_single_parent_unique');
        });

        Schema::table('nodes', function (Blueprint $table): void {
            $table->json('capabilities')->nullable()->after('node_type');
            $table->char('province_id', 36)->nullable()->after('address_snapshot');
            $table->char('city_id', 36)->nullable()->after('province_id');
            $table->string('country_code', 2)->default('IR')->after('city_id');
            $table->string('postal_code', 10)->nullable()->after('country_code');
            $table->string('address_line', 1000)->nullable()->after('postal_code');
            $table->decimal('latitude', 10, 7)->nullable()->after('address_line');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->foreign('province_id', 'nodes_province_fk')->references('province_id')->on('provinces')->restrictOnDelete();
            $table->foreign('city_id', 'nodes_city_fk')->references('city_id')->on('cities')->restrictOnDelete();
            $table->index(['hq_id', 'node_type', 'status'], 'nodes_hq_type_status_index');
            $table->index(['hq_id', 'city_id', 'status'], 'nodes_hq_city_status_index');
        });
        DB::table('nodes')->whereNull('capabilities')->update(['capabilities' => json_encode([], JSON_THROW_ON_ERROR)]);
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE nodes MODIFY capabilities JSON NOT NULL DEFAULT (JSON_ARRAY())');
            DB::statement("ALTER TABLE nodes ADD CONSTRAINT nodes_type_check CHECK (node_type IN ('BRANCH','HUB','GATEWAY'))");
            DB::statement("ALTER TABLE nodes ADD CONSTRAINT nodes_country_check CHECK (country_code = 'IR')");
            DB::statement('ALTER TABLE nodes ADD CONSTRAINT nodes_latitude_check CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90))');
            DB::statement('ALTER TABLE nodes ADD CONSTRAINT nodes_longitude_check CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))');
            DB::statement('ALTER TABLE nodes ADD CONSTRAINT nodes_location_pair_check CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL))');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS areas_assign_code_before_insert');
            DB::statement('ALTER TABLE nodes DROP CHECK nodes_location_pair_check');
            DB::statement('ALTER TABLE nodes DROP CHECK nodes_longitude_check');
            DB::statement('ALTER TABLE nodes DROP CHECK nodes_latitude_check');
            DB::statement('ALTER TABLE nodes DROP CHECK nodes_country_check');
            DB::statement('ALTER TABLE nodes DROP CHECK nodes_type_check');
        }
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropForeign('nodes_province_fk');
            $table->dropForeign('nodes_city_fk');
            $table->dropIndex('nodes_hq_type_status_index');
            $table->dropIndex('nodes_hq_city_status_index');
            $table->dropColumn(['capabilities', 'province_id', 'city_id', 'country_code', 'postal_code', 'address_line', 'latitude', 'longitude', 'version']);
        });
        Schema::table('area_hierarchies', function (Blueprint $table): void {
            $table->dropUnique('area_hierarchy_child_single_parent_unique');
        });
        Schema::table('areas', function (Blueprint $table): void {
            $table->dropUnique('areas_hq_code_unique');
            $table->dropColumn(['area_code', 'version']);
        });
    }
};
