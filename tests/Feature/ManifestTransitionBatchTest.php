<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestParcelTransitionDto;
use Modules\Manifest\Application\Dto\ManifestRouteEvidenceDto;
use Modules\Manifest\Application\Services\ManifestTransitionRecorder;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ManifestTransitionBatchTest extends TestCase
{
    public function test_status_and_custody_history_preserve_per_consignment_sequences_and_original_evidence(): void
    {
        config(['database.connections.manifest_ledger_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('manifest_ledger_test');
        foreach (['consignment_status_events', 'parcel_custody_events'] as $table) {
            (require glob(base_path('Modules/Consignment/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $recorder = $this->app->make(ManifestTransitionRecorder::class);
        $actor = new AuthenticatedPrincipal('84712523', 'session', '245213294', false);
        $counts = [];
        foreach ([1, 40, 101] as $count) {
            $manifest = (new ManifestRecord)->forceFill(['manifest_id' => \Tests\Support\FixtureId::from('m'.$count), 'hq_id' => '245213294', 'manifest_status' => 'OS', 'assigned_driver_id' => '189656963']);
            $transitions = [];
            for ($group = 0; $group <= 1; $group++) {
                $consignment = \Tests\Support\FixtureId::from(\Tests\Support\FixtureId::from('c'.$count).'-'.$group);
                RecordFixtureQuery::table('consignment_status_events')->insert(['status_event_id' => $consignment, 'hq_id' => '245213294', 'consignment_id' => $consignment, 'event_sequence' => 7 + $group, 'new_status' => 'OF', 'initiator_id' => '84712523']);
                RecordFixtureQuery::table('parcel_custody_events')->insert(['custody_event_id' => $consignment, 'hq_id' => '245213294', 'consignment_id' => $consignment, 'parcel_id' => '184220439', 'event_sequence' => 11 + $group, 'to_custody_type' => 'NODE', 'command_name' => 'CREATE', 'initiator_id' => '84712523']);
            }
            for ($index = 0; $index < $count; $index++) {
                $parcel = (new ParcelRecord)->forceFill(['parcel_id' => \Tests\Support\FixtureId::from('p'.$count.'-'.$index), 'consignment_id' => \Tests\Support\FixtureId::from('c'.$count.'-'.($index % 2)), 'current_status' => 'OF', 'current_node_id' => \Tests\Support\FixtureId::from('old-'.$index), 'current_custody_type' => 'NODE', 'current_custodian_id' => \Tests\Support\FixtureId::from('custodian-'.$index)]);
                $evidence = new ManifestRouteEvidenceDto('25296341', '190608731', \Tests\Support\FixtureId::from('plan-'.$index), '97144633', \Tests\Support\FixtureId::from('leg-'.$index), 'version-leg', '189656963', '188763860');
                $transitions[] = new ManifestParcelTransitionDto($parcel, $evidence);
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $recorder->recordTransitions($actor, '88468052', $manifest, $transitions, '91733773'));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $statuses = RecordFixtureQuery::table('consignment_status_events')->where('manifest_id', $manifest->manifest_id)->get()->keyBy('parcel_id');
            $custodies = RecordFixtureQuery::table('parcel_custody_events')->where('manifest_id', $manifest->manifest_id)->get()->keyBy('parcel_id');
            self::assertCount($count, $statuses);
            self::assertCount($count, $custodies);
            $statusHeads = [7, 8];
            $custodyHeads = [11, 12];
            foreach ($transitions as $index => $transition) {
                $id = $transition->previousParcel->parcel_id;
                self::assertSame(++$statusHeads[$index % 2], $statuses[$id]->event_sequence);
                self::assertSame(++$custodyHeads[$index % 2], $custodies[$id]->event_sequence);
                self::assertSame('OF', $statuses[$id]->previous_status);
                self::assertSame('OS', $statuses[$id]->new_status);
                self::assertSame(\Tests\Support\FixtureId::from('old-'.$index), $custodies[$id]->from_node_id);
                self::assertSame(\Tests\Support\FixtureId::from('custodian-'.$index), $custodies[$id]->from_custodian_id);
                self::assertSame('LINEHAUL_DRIVER', $custodies[$id]->to_custody_type);
                self::assertSame('189656963', $custodies[$id]->to_custodian_id);
                self::assertSame(\Tests\Support\FixtureId::from('plan-'.$index), $custodies[$id]->route_plan_id);
                self::assertSame(\Tests\Support\FixtureId::from('leg-'.$index), $custodies[$id]->route_plan_leg_id);
            }
        }
        self::assertSame([4, 4, 6], $counts);
    }
}
