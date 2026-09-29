<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Consignment\Application\Contracts\ConsignmentAggregateProjectorInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Services\ParcelLifecycleService;
use RuntimeException;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ParcelTransitionBatchTest extends TestCase
{
    public function test_history_writes_are_batched_with_distinct_sequences_and_original_custody(): void
    {
        $counts = [];
        foreach ([1, 40] as $count) {
            $id = \Tests\Support\FixtureId::from('c'.$count);
            $this->seedParcels($id, $count);
            RecordFixtureQuery::table('consignment_status_events')->insert(['status_event_id' => \Tests\Support\FixtureId::from('before-'.$id), 'hq_id' => '245213294', 'consignment_id' => $id, 'event_sequence' => 7, 'new_status' => 'CFM', 'initiator_id' => '84712523']);
            RecordFixtureQuery::table('parcel_custody_events')->insert(['custody_event_id' => \Tests\Support\FixtureId::from('before-'.$id), 'hq_id' => '245213294', 'consignment_id' => $id, 'parcel_id' => \Tests\Support\FixtureId::from($id.'-1'), 'event_sequence' => 11, 'to_custody_type' => 'NODE', 'command_name' => 'CREATE', 'initiator_id' => '84712523']);
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $ids = DB::transaction(fn () => $this->transition($id));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($count, $ids);
            self::assertSame(range(8, 7 + $count), RecordFixtureQuery::table('consignment_status_events')->where('consignment_id', $id)->where('event_sequence', '>', 7)->orderBy('event_sequence')->pluck('event_sequence')->all());
            self::assertSame(range(12, 11 + $count), RecordFixtureQuery::table('parcel_custody_events')->where('consignment_id', $id)->where('event_sequence', '>', 11)->orderBy('event_sequence')->pluck('event_sequence')->all());
            foreach (RecordFixtureQuery::table('parcel_custody_events')->where('consignment_id', $id)->where('event_sequence', '>', 11)->get() as $event) {
                $i = array_search($event->parcel_id, array_combine(range(1, $count), array_map(fn ($number) => \Tests\Support\FixtureId::from($id.'-'.$number), range(1, $count))), true);
                self::assertNotFalse($i);
                self::assertSame(\Tests\Support\FixtureId::from('old-'.$i), $event->from_node_id);
                self::assertSame(\Tests\Support\FixtureId::from('old-'.$i), $event->from_custodian_id);
                self::assertSame('189656963', $event->to_custodian_id);
                self::assertSame('105413112', $event->route_plan_id);
                self::assertSame('209489866', $event->route_plan_leg_id);
                $parcel = RecordFixtureQuery::table('parcels')->where('parcel_id', $event->parcel_id)->first();
                self::assertSame($i + 1, $parcel->version);
                self::assertSame('PD', $parcel->current_status);
            }
        }
        self::assertSame([7, 7], $counts);
    }

    public function test_outbox_failure_rolls_back_all_parcels_and_immutable_history(): void
    {
        $this->seedParcels('228742273', 4);
        $outbox = Mockery::mock(OutboxWriterInterface::class);
        $outbox->shouldReceive('write')->once()->andThrow(new RuntimeException('outbox unavailable'));
        $this->app->instance(OutboxWriterInterface::class, $outbox);
        try {
            DB::transaction(fn () => $this->transition('228742273'));
            self::fail('The transaction must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('outbox unavailable', $exception->getMessage());
        }
        self::assertSame(4, RecordFixtureQuery::table('parcels')->where('current_status', 'CFM')->count());
        self::assertEqualsCanonicalizing([1, 2, 3, 4], RecordFixtureQuery::table('parcels')->orderBy('id')->pluck('version')->all());
        self::assertSame(0, RecordFixtureQuery::table('consignment_status_events')->count());
        self::assertSame(0, RecordFixtureQuery::table('parcel_custody_events')->count());
    }

    public function test_mixed_state_is_rejected_before_any_parcel_is_changed(): void
    {
        $this->seedParcels('66649831', 2);
        RecordFixtureQuery::table('parcels')->where('parcel_id', \Tests\Support\FixtureId::from('mixed-2'))->update(['current_status' => 'PD']);
        try {
            DB::transaction(fn () => $this->transition('66649831'));
            self::fail('Mixed state must be rejected.');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->httpStatus);
        }
        self::assertSame('CFM', RecordFixtureQuery::table('parcels')->where('parcel_id', \Tests\Support\FixtureId::from('mixed-1'))->value('current_status'));
        self::assertSame(0, RecordFixtureQuery::table('consignment_status_events')->count());
        self::assertSame(0, RecordFixtureQuery::table('parcel_custody_events')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.parcel_batch_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('parcel_batch_test');
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
        });
        foreach (['parcels', 'consignment_status_events', 'parcel_custody_events'] as $table) {
            (require glob(base_path('Modules/Consignment/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $aggregate = Mockery::mock(ConsignmentAggregateProjectorInterface::class);
        $aggregate->shouldReceive('project')->andReturnNull();
        $this->app->instance(ConsignmentAggregateProjectorInterface::class, $aggregate);
        $audit = Mockery::mock(AuditWriterInterface::class);
        $audit->shouldReceive('write')->andReturnNull();
        $this->app->instance(AuditWriterInterface::class, $audit);
        $outbox = Mockery::mock(OutboxWriterInterface::class);
        $outbox->shouldReceive('write')->andReturnNull();
        $this->app->instance(OutboxWriterInterface::class, $outbox);
    }

    private function seedParcels(string $consignment, int $count): void
    {
        RecordFixtureQuery::table('consignments')->insert(['hq_id' => '245213294', 'consignment_id' => $consignment]);
        for ($i = 1; $i <= $count; $i++) {
            RecordFixtureQuery::table('parcels')->insert(['hq_id' => '245213294', 'consignment_id' => $consignment, 'parcel_id' => \Tests\Support\FixtureId::from($consignment.'-'.$i),
                'parcel_number' => \Tests\Support\FixtureId::from($consignment.'-'.$i), 'current_status' => 'CFM', 'current_node_id' => \Tests\Support\FixtureId::from('old-'.$i),
                'current_custody_type' => 'NODE', 'current_custodian_id' => \Tests\Support\FixtureId::from('old-'.$i), 'version' => $i]);
        }
    }

    private function transition(string $consignment): array
    {
        return $this->app->make(ParcelLifecycleService::class)->transition(
            new AuthenticatedPrincipal('84712523', 'session', '245213294', false), $consignment,
            'CFM', 'PD', 'PICKUP_ASSIGNED', null, 'PICKUP_DRIVER', '189656963', '91733773', '189656963',
            routePlanId: '105413112', routePlanLegId: '209489866',
        );
    }
}
