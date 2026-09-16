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
        Schema::table('consignments', function (Blueprint $table): void {
            $table->enum('aggregate_mode', ['FULL', 'PARTIAL'])->default('FULL')->after('current_status');
            $table->json('parcel_status_counts')->nullable()->after('aggregate_mode');
            $table->index(
                ['hq_id', 'current_status', 'aggregate_mode', 'created_at', 'consignment_id'],
                'consignments_aggregate_status_list_index',
            );
        });

        Schema::table('consignment_status_events', function (Blueprint $table): void {
            $table->enum('aggregate_mode', ['FULL', 'PARTIAL'])->nullable()->after('new_status');
            $table->json('parcel_status_counts')->nullable()->after('aggregate_mode');
        });

        DB::table('consignments')->orderBy('consignment_id')->chunkById(100, function ($consignments): void {
            foreach ($consignments as $consignment) {
                $counts = DB::table('parcels')
                    ->where([
                        'hq_id' => $consignment->hq_id,
                        'consignment_id' => $consignment->consignment_id,
                    ])
                    ->selectRaw('current_status, COUNT(*) AS aggregate_count')
                    ->groupBy('current_status')
                    ->pluck('aggregate_count', 'current_status')
                    ->map(static fn ($count): int => (int) $count)
                    ->all();
                ksort($counts);

                $lastManifestTarget = DB::table('consignment_status_events')
                    ->where([
                        'hq_id' => $consignment->hq_id,
                        'consignment_id' => $consignment->consignment_id,
                    ])
                    ->whereNotNull('parcel_id')
                    ->whereNotNull('manifest_id')
                    ->orderByDesc('event_sequence')
                    ->orderByDesc('created_at')
                    ->value('new_status');
                $target = (string) ($lastManifestTarget ?? $consignment->current_status);
                $total = array_sum($counts);
                $mode = $total > 0 && ($counts[$target] ?? 0) === $total ? 'FULL' : 'PARTIAL';

                DB::table('consignments')->where([
                    'hq_id' => $consignment->hq_id,
                    'consignment_id' => $consignment->consignment_id,
                ])->update([
                    'current_status' => $target,
                    'aggregate_mode' => $mode,
                    'parcel_status_counts' => json_encode($counts, JSON_THROW_ON_ERROR),
                ]);
            }
        }, 'consignment_id');

    }

    public function down(): void
    {
        Schema::table('consignment_status_events', function (Blueprint $table): void {
            $table->dropColumn(['aggregate_mode', 'parcel_status_counts']);
        });
        Schema::table('consignments', function (Blueprint $table): void {
            $table->dropIndex('consignments_aggregate_status_list_index');
            $table->dropColumn(['aggregate_mode', 'parcel_status_counts']);
        });
    }
};
