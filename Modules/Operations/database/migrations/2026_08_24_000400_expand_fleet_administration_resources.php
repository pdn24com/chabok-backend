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
        Schema::table('drivers', function (Blueprint $table): void {
            $table->string('mobile', 32)->nullable()->after('display_name');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('plate_number', 40)->nullable()->after('registration_number');
            $table->unsignedBigInteger('capacity_weight_grams')->nullable()->after('home_node_id');
            $table->unsignedBigInteger('capacity_volume_cm3')->nullable()->after('capacity_weight_grams');
        });

        DB::table('vehicles')->whereNull('plate_number')->update([
            'plate_number' => DB::raw('registration_number'),
        ]);

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('plate_number', 40)->nullable(false)->change();
            $table->unique(['hq_id', 'plate_number'], 'vehicles_hq_plate_unique');
        });

        $this->expandEnums();
    }

    public function down(): void
    {
        $this->restoreEnums();

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropUnique('vehicles_hq_plate_unique');
            $table->dropColumn(['plate_number', 'capacity_weight_grams', 'capacity_volume_cm3']);
        });
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropColumn('mobile');
        });
    }

    private function expandEnums(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE drivers MODIFY availability_status ENUM('AVAILABLE','ON_MISSION','TEMPORARILY_INACTIVE','MAINTENANCE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE'");
            DB::statement("ALTER TABLE vehicles MODIFY vehicle_type ENUM('MOTORCYCLE','CAR','VAN','LIGHT_TRUCK','TRUCK','TRAILER','OTHER') NOT NULL");
            DB::statement("ALTER TABLE vehicles MODIFY availability_status ENUM('AVAILABLE','ON_MISSION','TEMPORARILY_INACTIVE','MAINTENANCE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE'");

            return;
        }

        Schema::table('drivers', function (Blueprint $table): void {
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'MAINTENANCE', 'INACTIVE'])
                ->default('AVAILABLE')->change();
        });
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->enum('vehicle_type', ['MOTORCYCLE', 'CAR', 'VAN', 'LIGHT_TRUCK', 'TRUCK', 'TRAILER', 'OTHER'])->change();
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'MAINTENANCE', 'INACTIVE'])
                ->default('AVAILABLE')->change();
        });
    }

    private function restoreEnums(): void
    {
        DB::table('drivers')->where('availability_status', 'MAINTENANCE')->update(['availability_status' => 'TEMPORARILY_INACTIVE']);
        DB::table('vehicles')->where('availability_status', 'TEMPORARILY_INACTIVE')->update(['availability_status' => 'INACTIVE']);
        DB::table('vehicles')->whereNotIn('vehicle_type', ['VAN', 'TRUCK'])->update(['vehicle_type' => 'VAN']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE drivers MODIFY availability_status ENUM('AVAILABLE','ON_MISSION','TEMPORARILY_INACTIVE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE'");
            DB::statement("ALTER TABLE vehicles MODIFY vehicle_type ENUM('VAN','TRUCK') NOT NULL");
            DB::statement("ALTER TABLE vehicles MODIFY availability_status ENUM('AVAILABLE','ON_MISSION','MAINTENANCE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE'");

            return;
        }

        Schema::table('drivers', function (Blueprint $table): void {
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'INACTIVE'])
                ->default('AVAILABLE')->change();
        });
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->enum('vehicle_type', ['VAN', 'TRUCK'])->change();
            $table->enum('availability_status', ['AVAILABLE', 'ON_MISSION', 'MAINTENANCE', 'INACTIVE'])
                ->default('AVAILABLE')->change();
        });
    }
};
