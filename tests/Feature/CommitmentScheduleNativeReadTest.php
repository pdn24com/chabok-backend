<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\CommitmentResolutionDocument;
use Modules\ServiceCatalog\Application\Serialization\ScheduleDocument;
use Modules\ServiceCatalog\Application\Services\ScheduleWindows;
use Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryCommand;
use Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryHandler;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentWindowUnavailable;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;
use Tests\Support\AccessContexts;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class CommitmentScheduleNativeReadTest extends TestCase
{
    public function test_history_batches_windows_scopes_and_legacy_policy_candidates_without_leaking_internal_keys(): void
    {
        config(['database.connections.schedule_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('schedule_read_test');
        foreach (['commitment_schedules', 'commitment_schedule_versions', 'commitment_schedule_windows', 'commitment_schedule_scopes', 'service_offering_commitment_bindings'] as $table) {
            (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        RecordFixtureQuery::table('commitment_schedules')->insert(['commitment_schedule_id' => '100081670', 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => 'SCH', 'title' => 'Schedule', 'created_by' => '84712523']);
        $authorization = Mockery::mock(AccessContextResolverInterface::class);
        $authorization->shouldReceive('resolve')->andReturn(AccessContexts::make([
            'hq_id' => '245213294',
            'permissions' => ['service_catalog.history.view'],
            'module_entitlements' => [['module_code' => 'ServiceCatalog', 'status' => 'ENABLED']],
        ]));
        $this->app->instance(AccessContextResolverInterface::class, $authorization);
        $handler = $this->app->make(GetCommitmentScheduleHistoryHandler::class);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $version = \Tests\Support\FixtureId::from('version-'.$index);
            RecordFixtureQuery::table('commitment_schedule_versions')->insert(['commitment_schedule_version_id' => $version, 'commitment_schedule_id' => '100081670', 'hq_id' => '245213294', 'version_number' => $index, 'created_by' => '84712523']);
            RecordFixtureQuery::table('commitment_schedule_windows')->insert(['commitment_schedule_window_id' => \Tests\Support\FixtureId::from('window-'.$index), 'commitment_schedule_version_id' => $version, 'window_code' => 'AM', 'window_type' => 'PICKUP', 'label_fa' => 'صبح', 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'booking_cutoff_time' => '08:00:00', 'applicable_weekdays' => '[1,2,3,4,5,6,7]']);
            RecordFixtureQuery::table('commitment_schedule_scopes')->insert(['commitment_schedule_scope_id' => \Tests\Support\FixtureId::from('scope-'.$index), 'commitment_schedule_version_id' => $version, 'hq_id' => '245213294', 'scope_type' => 'HQ']);
            RecordFixtureQuery::table('service_offering_commitment_bindings')->insert(['offering_commitment_binding_id' => \Tests\Support\FixtureId::from('binding-'.$index), 'service_offering_version_id' => \Tests\Support\FixtureId::from('offering-'.$index), 'commitment_schedule_version_id' => $version, 'pickup_mode' => 'SELECTABLE_WINDOW', 'delivery_mode' => 'COMPUTED', 'duration_value' => 72, 'duration_unit' => 'HOUR', 'duration_anchor' => 'PICKUP_COMMITMENT_END']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $result = $handler->handle(new GetCommitmentScheduleHistoryCommand(new AuthenticatedPrincipal('84712523', 'session', '245213294', false), '100081670'));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $result);
            self::assertSame($index, $result[0]['version_number']);
            self::assertSame('SCH', $result[0]['code']);
            self::assertCount(1, $result[0]['legacy_policy_candidates']);
            self::assertSame(72, $result[0]['legacy_policy_candidates'][0]['delivery']['duration_value']);
            self::assertSame([1, 2, 3, 4, 5, 6, 7], $result[0]['windows'][0]['applicable_weekdays']);
            self::assertArrayHasKey('id', $result[0]);
            self::assertArrayHasKey('id', $result[0]['windows'][0]);
            self::assertArrayHasKey('id', $result[0]['scopes'][0]);
        }
        self::assertSame([6, 6], $counts);
        $query = $this->app->make(CommitmentScheduleRepositoryInterface::class);
        self::assertCount(0, $query->tenantVersions('106329882'));
        $version = $query->tenantVersions('245213294')->first();
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            ScheduleDocument::version($version);
            self::assertCount(0, DB::connection()->getQueryLog());
        } finally {
            DB::connection()->disableQueryLog();
        }
    }

    public function test_loaded_window_previews_do_not_query_per_window_and_preserve_cutoff_rules(): void
    {
        config(['database.connections.window_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('window_read_test');
        $clock = Mockery::mock(ClockInterface::class);
        $clock->shouldReceive('now')->andReturn(new DateTimeImmutable('2026-09-24T00:00:00Z'));
        $windows = new Collection;
        $service = new ScheduleWindows($clock);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $windows->push((new CommitmentScheduleWindowRecord)->forceFill([
                'window_code' => 'W'.$index, 'window_type' => 'PICKUP', 'active' => true, 'label_fa' => 'صبح',
                'start_time' => '09:00:00', 'end_time' => '12:00:00', 'booking_cutoff_time' => '08:00:00',
                'applicable_weekdays' => [1, 2, 3, 4, 5, 6, 7], 'day_offset' => 0, 'risk_threshold_minutes' => 120,
            ]));
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $options = array_map(CommitmentResolutionDocument::window(...), $service->pickupWindowOptions($windows, 'Asia/Tehran', '2026-09-24'));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $options);
            self::assertSame('2026-09-24T05:30:00.000000Z', $options[0]['starts_at']);
            self::assertSame('2026-09-24T04:30:00.000000Z', $options[0]['booking_cutoff_at']);
        }
        self::assertSame([0, 0], $counts);
        $next = CommitmentResolutionDocument::window($service->nextWindow($windows->first(), 'Asia/Tehran', CarbonImmutable::parse('2026-09-24T12:00:00Z')));
        self::assertSame('2026-09-25', $next['service_date']);
        $windows->first()->active = false;
        try {
            $service->windowInstance($windows->first(), CommitmentWindowType::Pickup, '2026-09-24', 'Asia/Tehran');
            self::fail('Inactive window accepted.');
        } catch (CommitmentWindowUnavailable $error) {
            self::assertSame('PICKUP_WINDOW_INVALID', $error->reasonCode());
        }
        try {
            $service->windowInstance($windows->last(), CommitmentWindowType::Pickup, '2026-09-23', 'Asia/Tehran');
            self::fail('Past cutoff accepted.');
        } catch (CommitmentWindowUnavailable $error) {
            self::assertSame('PICKUP_CUTOFF_PASSED', $error->reasonCode());
        }
    }
}
