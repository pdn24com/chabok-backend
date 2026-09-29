<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Consignment\Application\Dto\AggregateProjectionDto;
use Modules\Consignment\Application\Services\ConsignmentAggregateProjector;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use RuntimeException;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ConsignmentAggregateNativeTest extends TestCase
{
    public function test_aggregate_uses_constant_queries_casts_counts_and_only_appends_changed_history(): void
    {
        $counts = [];
        foreach ([1, 40] as $count) {
            $id = \Tests\Support\FixtureId::from('shipment-'.$count);
            RecordFixtureQuery::table('consignments')->insert(['consignment_id' => $id, 'hq_id' => '245213294', 'current_status' => 'CFM']);
            for ($index = 1; $index <= $count; $index++) {
                RecordFixtureQuery::table('parcels')->insert(['parcel_id' => \Tests\Support\FixtureId::from($id.'-'.$index), 'hq_id' => '245213294', 'consignment_id' => $id,
                    'parcel_number' => \Tests\Support\FixtureId::from($id.'-'.$index), 'current_status' => 'PD']);
            }
            RecordFixtureQuery::table('parcels')->insert(['parcel_id' => \Tests\Support\FixtureId::from('foreign-'.$id), 'hq_id' => '106329882', 'consignment_id' => $id,
                'parcel_number' => \Tests\Support\FixtureId::from('foreign-'.$id), 'current_status' => 'PU']);
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $this->project($id));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $consignment = ConsignmentRecord::query()->where('consignment_id', $id)->firstOrFail();
            self::assertSame('PD', $consignment->current_status);
            self::assertSame('FULL', $consignment->aggregate_mode);
            self::assertSame(['PD' => $count], $consignment->parcel_status_counts);
            self::assertSame('2026-09-24 01:02:03', $consignment->updated_at);
            $event = StatusEventRecord::query()->where('consignment_id', $id)->firstOrFail();
            self::assertSame('CFM', $event->previous_status);
            self::assertSame('PD', $event->new_status);
            self::assertSame('FULL', $event->aggregate_mode);
            self::assertSame(['PD' => $count], $event->parcel_status_counts);
            self::assertSame(1, $event->event_sequence);
            self::assertSame($count.'/'.$count, $event->note);
            self::assertSame('2026-09-24 01:02:03', $event->created_at);
            DB::transaction(fn () => $this->project($id));
            self::assertSame(1, StatusEventRecord::query()->where('consignment_id', $id)->count());
            RecordFixtureQuery::table('parcels')->where('parcel_id', \Tests\Support\FixtureId::from($id.'-1'))->update(['current_status' => 'CFM']);
            DB::transaction(fn () => $this->project($id));
            $event = StatusEventRecord::query()->where('consignment_id', $id)->orderByDesc('event_sequence')->firstOrFail();
            self::assertSame(2, $event->event_sequence);
            self::assertSame('PD', $event->previous_status);
            self::assertSame('PD', $event->new_status);
            self::assertSame('PARTIAL', $event->aggregate_mode);
            self::assertSame(1, $event->parcel_status_counts['CFM']);
            self::assertSame(($count - 1).'/'.$count, $event->note);
            $before = $consignment->fresh()->getAttributes();
            try {
                DB::transaction(function () use ($id): void {
                    $this->project($id, target: 'PU');
                    throw new RuntimeException('Rollback');
                });
            } catch (RuntimeException $error) {
                self::assertSame('Rollback', $error->getMessage());
            }
            self::assertSame($before, $consignment->fresh()->getAttributes());
            self::assertSame(2, StatusEventRecord::query()->where('consignment_id', $id)->count());
            try {
                DB::transaction(fn () => $this->project($id, tenant: '227711138'));
                self::fail('Tenant-scoped parent lock is required.');
            } catch (ApiException $error) {
                self::assertSame(404, $error->httpStatus);
            }
        }
        self::assertSame([5, 5], $counts);
    }

    public function test_projection_of_many_parents_has_bounded_queries_and_independent_sequences(): void
    {
        $queryCounts = [];
        foreach ([1, 40, 101] as $count) {
            $ids = [];
            for ($index = 0; $index < $count; $index++) {
                $id = \Tests\Support\FixtureId::from("batch-{$count}-{$index}");
                $ids[] = $id;
                RecordFixtureQuery::table('consignments')->insert(['consignment_id' => $id, 'hq_id' => '245213294', 'current_status' => 'CFM']);
                RecordFixtureQuery::table('parcels')->insert(['parcel_id' => $id, 'hq_id' => '245213294', 'consignment_id' => $id, 'parcel_number' => $id, 'current_status' => 'PD']);
                RecordFixtureQuery::table('consignment_status_events')->insert(['status_event_id' => $id, 'hq_id' => '245213294', 'consignment_id' => $id, 'event_sequence' => $index + 2, 'new_status' => 'CFM', 'initiator_id' => '84712523']);
            }
            $actor = new AuthenticatedPrincipal('84712523', 'session', '245213294', false);
            $projection = new AggregateProjectionDto('PD', '88468052', '5978816', 'AGGREGATED');
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $this->app->make(ConsignmentAggregateProjector::class)->projectMany($actor, [...$ids, $ids[0]], $projection);
                $queryCounts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $events = StatusEventRecord::query()->whereIn('consignment_id', $ids)->where('new_status', 'PD')->get()->keyBy('consignment_id');
            self::assertCount($count, $events);
            foreach ($ids as $index => $id) {
                self::assertSame($index + 3, $events[$id]->event_sequence);
                self::assertSame(['PD' => 1], $events[$id]->parcel_status_counts);
            }
        }
        self::assertSame([5, 5, 7], $queryCounts);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.aggregate_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('aggregate_test');
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->string('current_status');
            $table->string('aggregate_mode')->nullable();
            $table->json('parcel_status_counts')->nullable();
            $table->timestamp('updated_at', 6)->nullable();
        });
        foreach (['parcels', 'consignment_status_events'] as $table) {
            (require glob(base_path('Modules/Consignment/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $clock = Mockery::mock(ClockInterface::class);
        $clock->shouldReceive('now')->andReturn(new DateTimeImmutable('2026-09-24T01:02:03.123456Z'));
        $this->app->instance(ClockInterface::class, $clock);
    }

    private function project(string $id, string $tenant = '245213294', string $target = 'PD'): void
    {
        $this->app->make(ConsignmentAggregateProjector::class)->project(new AuthenticatedPrincipal('84712523', 'session', $tenant, false),
            $id, $target, '88468052', '5978816', 'AGGREGATED', '189656963', '91733773');
    }
}
