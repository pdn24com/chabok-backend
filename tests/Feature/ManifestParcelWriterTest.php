<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Manifest\Application\Contracts\ManifestParcelWriterInterface;
use Modules\Manifest\Application\Dto\ManifestParcelAdditionDto;
use Modules\Manifest\Application\Dto\ManifestParcelOutcomeDto;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Enums\ManifestParcelAddResult;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;
use Tests\TestCase;

final class ManifestParcelWriterTest extends TestCase
{
    public function test_normal_inserts_are_bounded_batches_and_preserve_every_row(): void
    {
        $writer = $this->app->make(ManifestParcelWriterInterface::class);
        foreach ([1 => 1, 40 => 1, 101 => 2] as $size => $expectedQueries) {
            $additions = [];
            for ($index = 0; $index < $size; $index++) {
                $additions[] = $this->addition("m{$size}", \Tests\Support\FixtureId::from("p{$size}-{$index}"), "slot{$size}-{$index}");
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $writer->insert($additions));
                self::assertCount($expectedQueries, DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertSame($size, ManifestParcelRecord::query()->where('manifest_id', "m{$size}")->count());
            foreach ($additions as $addition) {
                self::assertSame(ManifestParcelAddResult::Added, $addition->outcome->result);
            }
        }
    }

    public function test_a_concurrent_slot_claim_keeps_other_rows_and_returns_a_failed_outcome(): void
    {
        $writer = $this->app->make(ManifestParcelWriterInterface::class);
        $existing = $this->addition('other-manifest', '226087750', 'claimed-slot');
        $existing->record->save();
        $claimed = $this->addition('5978816', '226087750', 'claimed-slot');
        $available = $this->addition('5978816', '232626201', 'available-slot');

        DB::transaction(fn () => $writer->insert([$claimed, $available]));

        self::assertSame(ManifestParcelAddResult::Failed, $claimed->outcome->result);
        self::assertSame(ManifestEligibilityReason::ParcelAlreadyAssigned, $claimed->outcome->reason);
        self::assertSame(ManifestParcelAddResult::Added, $available->outcome->result);
        $rows = ManifestParcelRecord::query()->where('manifest_id', '5978816')->get()->keyBy('parcel_id');
        self::assertCount(2, $rows);
        self::assertNull($rows['226087750']->active_slot);
        self::assertSame('FAILED', $rows['226087750']->manifest_parcel_status);
        self::assertSame('PENDING', $rows['232626201']->manifest_parcel_status);
    }

    public function test_unrelated_integrity_failures_are_not_hidden_as_slot_conflicts(): void
    {
        $writer = $this->app->make(ManifestParcelWriterInterface::class);
        $existing = $this->addition('5978816', '77383142', 'old-slot');
        $existing->record->save();
        $duplicate = $this->addition('5978816', '77383142', 'different-slot');
        $duplicate->record->manifest_parcel_id = 'different-public-id';
        $this->expectException(QueryException::class);
        DB::transaction(fn () => $writer->insert([$duplicate]));
    }

    public function test_state_changes_are_batched_and_preserve_unmodified_evidence(): void
    {
        $writer = $this->app->make(ManifestParcelWriterInterface::class);
        foreach ([1 => 1, 40 => 1, 101 => 2] as $size => $expectedQueries) {
            $additions = [];
            for ($index = 0; $index < $size; $index++) {
                $additions[] = $this->addition("state-{$size}", \Tests\Support\FixtureId::from("p{$size}-{$index}"), "state-slot{$size}-{$index}");
            }
            DB::transaction(fn () => $writer->insert($additions));
            $rows = ManifestParcelRecord::query()->where('manifest_id', "state-{$size}")->get();
            foreach ($rows as $row) {
                $row->forceFill(['manifest_parcel_status' => 'SUCCEEDED', 'active_slot' => null, 'source_status' => 'CFM']);
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $writer->saveStates($rows));
                self::assertCount($expectedQueries, DB::connection()->getQueryLog());
                DB::connection()->flushQueryLog();
                $writer->saveStates($rows);
                self::assertCount(0, DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $saved = ManifestParcelRecord::query()->where('manifest_id', "state-{$size}")->get();
            foreach ($saved as $row) {
                self::assertSame('SUCCEEDED', $row->manifest_parcel_status);
                self::assertSame('CFM', $row->source_status);
                self::assertSame('84712523', $row->created_by);
                self::assertSame($row->parcel_id, $row->input_value);
                self::assertNull($row->active_slot);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.manifest_append_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('manifest_append_test');
        (require glob(base_path('Modules/Manifest/database/migrations/*_create_manifest_parcels.php'))[0])->up();
    }

    private function addition(string $manifest, string $parcel, string $slot): ManifestParcelAdditionDto
    {
        $reason = ManifestEligibilityReason::Eligible;
        $record = (new ManifestParcelRecord)->forceFill([
            'manifest_parcel_id' => \Tests\Support\FixtureId::from($manifest.'-'.$parcel),
            'hq_id' => '245213294',
            'manifest_id' => $manifest,
            'parcel_id' => $parcel,
            'manifest_parcel_status' => 'PENDING',
            'failure_code' => null,
            'failure_reason' => null,
            'input_source' => 'BATCH',
            'input_value' => $parcel,
            'active_slot' => $slot,
            'created_by' => '84712523',
        ]);

        return new ManifestParcelAdditionDto($record, new ManifestParcelOutcomeDto($parcel, ManifestParcelAddResult::Added, $reason, $parcel));
    }
}
