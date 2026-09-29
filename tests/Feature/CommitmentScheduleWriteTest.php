<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;
use Modules\ServiceCatalog\Application\Services\ScheduleChildrenWriter;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleScopeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;
use Tests\TestCase;

final class CommitmentScheduleWriteTest extends TestCase
{
    public function test_child_replacement_is_bounded_and_preserves_other_versions_and_rollback(): void
    {
        config(['database.connections.schedule_write_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('schedule_write_test');
        foreach (['commitment_schedule_windows', 'commitment_schedule_scopes'] as $table) {
            (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $writer = $this->app->make(ScheduleChildrenWriter::class);
        $other = CommitmentScheduleDto::fromValidated(['title' => 'Other', 'windows' => [$this->window(0)], 'scopes' => [['scope_type' => 'HQ', 'node_id' => '55659769']]]);
        $writer->replaceChildren('128450447', 'other-tenant', $other);
        $counts = [];
        foreach ([1, 40, 101] as $count) {
            $input = CommitmentScheduleDto::fromValidated([
                'title' => 'Schedule',
                'windows' => array_map($this->window(...), range(1, $count)),
                'scopes' => array_map(fn ($index) => ['scope_type' => 'NODE', 'node_id' => \Tests\Support\FixtureId::from('node-'.$index)], range(1, $count)),
            ]);
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $writer->replaceChildren('97144633', '245213294', $input));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $windows = CommitmentScheduleWindowRecord::query()->where('commitment_schedule_version_id', '97144633')->get();
            $scopes = CommitmentScheduleScopeRecord::query()->where('commitment_schedule_version_id', '97144633')->get();
            self::assertCount($count, $windows);
            self::assertCount($count, $scopes);
            self::assertSame([1, 3, 7], $windows[0]->applicable_weekdays);
            self::assertSame(0, $windows[0]->risk_threshold_minutes);
            self::assertSame(2, $windows[0]->day_offset);
            self::assertFalse((bool) $windows[0]->active);
            self::assertSame('245213294', $scopes[0]->hq_id);
            self::assertEqualsCanonicalizing(array_map(fn ($scope) => $scope->nodeId, $input->scopes), $scopes->pluck('node_id')->all());
        }
        self::assertSame([4, 4, 6], $counts);
        $before = CommitmentScheduleWindowRecord::query()->where('commitment_schedule_version_id', '97144633')->pluck('commitment_schedule_window_id')->all();
        try {
            DB::transaction(fn () => $writer->replaceChildren('97144633', '245213294', CommitmentScheduleDto::fromValidated([
                'title' => 'Invalid duplicate', 'windows' => [$this->window(1), $this->window(1)],
            ])));
            self::fail('Duplicate window codes must roll back the replacement.');
        } catch (QueryException) {
            self::assertSame($before, CommitmentScheduleWindowRecord::query()->where('commitment_schedule_version_id', '97144633')->pluck('commitment_schedule_window_id')->all());
            self::assertSame(101, CommitmentScheduleScopeRecord::query()->where('commitment_schedule_version_id', '97144633')->count());
        }
        $foreignWindow = CommitmentScheduleWindowRecord::query()->where('commitment_schedule_version_id', '128450447')->sole();
        $foreignScope = CommitmentScheduleScopeRecord::query()->where('commitment_schedule_version_id', '128450447')->sole();
        self::assertSame('WINDOW_0', $foreignWindow->window_code);
        self::assertSame('other-tenant', $foreignScope->hq_id);
        self::assertSame('HQ', $foreignScope->scope_type);
        self::assertNull($foreignScope->node_id);
    }

    private function window(int $index): array
    {
        return [
            'window_code' => 'WINDOW_'.$index, 'window_type' => 'PICKUP', 'label_fa' => 'صبح',
            'start_time' => '09:00', 'end_time' => '12:00', 'booking_cutoff_time' => '08:00',
            'applicable_weekdays' => [1, 3, 7], 'risk_threshold_minutes' => 0, 'day_offset' => 2, 'active' => false,
        ];
    }
}
