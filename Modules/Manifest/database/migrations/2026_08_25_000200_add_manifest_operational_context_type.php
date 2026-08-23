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
        Schema::table('manifests', function (Blueprint $table): void {
            $table->enum('operational_context_type', [
                'PICKUP_RECEPTION',
                'TRANSPORT_RECEPTION',
                'ROUTE_OUTBOUND',
                'DELIVERY_ASSIGNMENT',
            ])->nullable()->after('manifest_type');
        });

        DB::table('manifests')->where('manifest_status', 'IR')->whereNull('transport_run_id')
            ->update(['operational_context_type' => 'PICKUP_RECEPTION']);
        DB::table('manifests')->where('manifest_status', 'IR')->whereNotNull('transport_run_id')
            ->update(['operational_context_type' => 'TRANSPORT_RECEPTION']);
        DB::table('manifests')->where('manifest_status', 'OF')
            ->update(['operational_context_type' => 'ROUTE_OUTBOUND']);
        DB::table('manifests')->where('manifest_status', 'OD')
            ->update(['operational_context_type' => 'DELIVERY_ASSIGNMENT']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE manifests MODIFY operational_context_type ENUM('PICKUP_RECEPTION','TRANSPORT_RECEPTION','ROUTE_OUTBOUND','DELIVERY_ASSIGNMENT') NOT NULL");
            DB::statement("ALTER TABLE manifests ADD CONSTRAINT manifests_operational_context_consistency CHECK ((manifest_status = 'IR' AND manifest_type = 'INBOUND_RECEPTION' AND operational_context_type IN ('PICKUP_RECEPTION','TRANSPORT_RECEPTION')) OR (manifest_status = 'OF' AND manifest_type = 'OUTBOUND_TRANSFER' AND operational_context_type = 'ROUTE_OUTBOUND') OR (manifest_status = 'OD' AND manifest_type = 'DELIVERY_ASSIGNMENT' AND operational_context_type = 'DELIVERY_ASSIGNMENT'))");
        } else {
            Schema::table('manifests', function (Blueprint $table): void {
                $table->enum('operational_context_type', [
                    'PICKUP_RECEPTION',
                    'TRANSPORT_RECEPTION',
                    'ROUTE_OUTBOUND',
                    'DELIVERY_ASSIGNMENT',
                ])->nullable(false)->change();
            });
        }

        Schema::table('manifests', function (Blueprint $table): void {
            $table->index(
                ['hq_id', 'node_id', 'operational_context_type', 'state'],
                'manifests_operational_context_index',
            );
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE manifests DROP CHECK manifests_operational_context_consistency');
        }

        Schema::table('manifests', function (Blueprint $table): void {
            $table->dropIndex('manifests_operational_context_index');
            $table->dropColumn('operational_context_type');
        });
    }
};
