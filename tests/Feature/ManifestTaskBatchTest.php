<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Operations\Application\Contracts\ManifestTaskBatchWriterInterface;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ManifestTaskBatchTest extends TestCase
{
    public function test_pickup_assignment_batches_parents_and_preserves_per_parcel_version_counts(): void
    {
        $writer = $this->app->make(ManifestTaskBatchWriterInterface::class);
        $queries = [];
        foreach ([1, 40, 101] as $count) {
            $parcels = [];
            for ($index = 0; $index < $count; $index++) {
                $id = \Tests\Support\FixtureId::from("c{$count}-{$index}");
                RecordFixtureQuery::table('consignments')->insert(['consignment_id' => $id, 'hq_id' => '245213294']);
                foreach ([1, 2] as $parcel) {
                    $parcels[] = (new ParcelRecord)->forceFill(['parcel_id' => \Tests\Support\FixtureId::from("$id-$parcel"), 'hq_id' => '245213294', 'consignment_id' => $id, 'current_status' => 'CFM']);
                }
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $writer->apply('245213294', '88468052', 'PD', '189656963', $parcels));
                $queries[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $tasks = PickupTaskRecord::query()->whereIn('consignment_id', array_column(array_map(fn ($parcel) => $parcel->toArray(), $parcels), 'consignment_id'))->get();
            self::assertCount($count, $tasks);
            foreach ($tasks as $task) {
                self::assertSame(2, $task->version);
                self::assertSame('ASSIGNED', $task->status->value);
            }
            // Reassignment historically increments once for every included parcel.
            DB::transaction(fn () => $writer->apply('245213294', '88468052', 'PD', '189656963', $parcels));
            self::assertSame(4, $tasks->first()->fresh()->version);
            // Completion changes an active task once, failure increments for each parcel.
            DB::transaction(fn () => $writer->apply('245213294', '88468052', 'PU', '189656963', $parcels));
            self::assertSame(5, $tasks->first()->fresh()->version);
            DB::transaction(fn () => $writer->apply('245213294', '88468052', 'NPU', '189656963', $parcels));
            self::assertSame(7, $tasks->first()->fresh()->version);
        }
        self::assertSame([4, 4, 5], $queries);
    }

    public function test_delivery_completion_preserves_versions_and_scopes_driver_release(): void
    {
        RecordFixtureQuery::table('drivers')->insert(['driver_id' => '108675677', 'hq_id' => '106329882', 'availability_status' => 'ON_MISSION']);
        RecordFixtureQuery::table('drivers')->where('driver_id', '189656963')->update(['availability_status' => 'ON_MISSION']);
        RecordFixtureQuery::table('delivery_tasks')->insert(['delivery_task_id' => '15447082', 'hq_id' => '245213294', 'consignment_id' => '48747201', 'node_id' => '88468052', 'assigned_driver_id' => '189656963', 'status' => 'IN_PROGRESS', 'version' => 5]);
        RecordFixtureQuery::table('delivery_tasks')->insert(['delivery_task_id' => '46699866', 'hq_id' => '106329882', 'consignment_id' => '48747201', 'node_id' => '88468052', 'assigned_driver_id' => '108675677', 'status' => 'IN_PROGRESS', 'version' => 2]);
        $parcels = [];
        for ($index = 0; $index < 40; $index++) {
            $parcels[] = (new ParcelRecord)->forceFill(['parcel_id' => \Tests\Support\FixtureId::from("p$index"), 'hq_id' => '245213294', 'consignment_id' => '48747201', 'current_status' => 'OD']);
        }
        DB::transaction(fn () => $this->app->make(ManifestTaskBatchWriterInterface::class)->apply('245213294', '88468052', 'OK', '189656963', $parcels));
        $task = RecordFixtureQuery::table('delivery_tasks')->where('delivery_task_id', '15447082')->first();
        self::assertSame('COMPLETED', $task->status);
        self::assertSame(45, $task->version);
        self::assertNotNull($task->delivered_at);
        self::assertSame(2, RecordFixtureQuery::table('delivery_tasks')->where('delivery_task_id', '46699866')->value('version'));
        self::assertSame('AVAILABLE', RecordFixtureQuery::table('drivers')->where('driver_id', '189656963')->value('availability_status'));
        self::assertSame('ON_MISSION', RecordFixtureQuery::table('drivers')->where('driver_id', '108675677')->value('availability_status'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.task_batch_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('task_batch_test');
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->string('pickup_man_id')->nullable();
        });
        Schema::create('drivers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->string('availability_status');
            $table->timestamp('updated_at')->nullable();
        });
        foreach (['pickup_tasks', 'delivery_tasks'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        RecordFixtureQuery::table('drivers')->insert(['driver_id' => '189656963', 'hq_id' => '245213294', 'availability_status' => 'AVAILABLE']);
    }
}
