<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Pricing\Application\Mappers\PricingZoneInput;
use Modules\Pricing\Application\Services\PricingZoneWriter;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneRecord;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class PricingZoneWriteTest extends TestCase
{
    public function test_bulk_zone_replacement_preserves_owned_identifiers_and_legacy_members_atomically(): void
    {
        config(['database.connections.zone_write' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('zone_write');
        Schema::create('pricing_zone_set_versions', fn (Blueprint $table) => $table->increments('id'));
        DB::table('pricing_zone_set_versions')->insert([['id' => 97144633], ['id' => 190669277]]);
        foreach (['pricing_zones', 'pricing_zone_members'] as $table) {
            (require glob(base_path('Modules/Pricing/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        Schema::table('pricing_zone_members', function (Blueprint $table): void {
            $table->foreign('pricing_zone_id')->references('id')->on('pricing_zones')->cascadeOnDelete();
        });
        Schema::create('cities', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('normalized_name');
            $table->boolean('is_active');
        });
        Schema::create('provinces', function (Blueprint $table): void {
            $table->increments('id');
            $table->boolean('is_active');
        });
        $writer = $this->app->make(PricingZoneWriter::class);
        $counts = [];
        $firstId = null;
        foreach ([1, 40, 101] as $count) {
            $input = [];
            for ($index = 1; $index <= $count; $index++) {
                $input[] = ['code' => 'zone-'.$index, 'title' => 'زون '.$index, 'rank' => $index, 'remote_area' => true,
                    'members' => [['member_type' => 'POSTAL_RANGE', 'reference_value' => '۰۰۰۰۰۰۰۰۰۱', 'range_end' => '٠٠٠٠٠٠٠٠٠٩']]];
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $writer->replaceZones('97144633', PricingZoneInput::many($input)));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $stored = PricingZoneRecord::query()->where('zone_set_version_id', '97144633')->orderBy('rank')->with('members')->get();
            self::assertCount($count, $stored);
            self::assertSame($count, RecordFixtureQuery::table('pricing_zone_members')->count());
            $firstId ??= $stored[0]->pricing_zone_id;
            self::assertSame($firstId, $stored[0]->pricing_zone_id);
            self::assertSame('ZONE-1', $stored[0]->code);
            self::assertSame('0000000001', $stored[0]->members[0]->reference_value);
            self::assertSame('0000000009', $stored[0]->members[0]->range_end);
            self::assertSame(300, $stored[0]->members[0]->precedence);
            self::assertTrue((bool) $stored[0]->remote_area);
        }
        self::assertSame([8, 8, 10], $counts);
        $snapshot = $stored->toArray();
        $duplicate = $input;
        $duplicate[0]['pricing_zone_id'] = $firstId;
        $duplicate[1]['pricing_zone_id'] = $firstId;
        try {
            DB::transaction(fn () => $writer->replaceZones('97144633', PricingZoneInput::many($duplicate)));
            self::fail('Duplicate requested zone identifiers must roll back the entire replacement.');
        } catch (QueryException) {
            self::assertSame($snapshot, PricingZoneRecord::query()->where('zone_set_version_id', '97144633')->orderBy('rank')->with('members')->get()->toArray());
        }
        // Previously stored short postal ranges remain preservable, including when cloning a version.
        RecordFixtureQuery::table('pricing_zone_members')->where('pricing_zone_id', $firstId)->update(['reference_value' => '1', 'range_end' => '9']);
        $source = PricingZoneRecord::query()->where('zone_set_version_id', '97144633')->with('members')->get();
        $drafts = PricingZoneInput::fromRecords($source);
        DB::transaction(fn () => $writer->replaceZones('190669277', $drafts, '97144633'));
        $copy = PricingZoneRecord::query()->where(['zone_set_version_id' => '190669277', 'code' => 'ZONE-1'])->with('members')->sole();
        self::assertNotSame($firstId, $copy->pricing_zone_id);
        self::assertSame('1', $copy->members[0]->reference_value);
        self::assertSame('9', $copy->members[0]->range_end);
        self::assertSame(101, PricingZoneRecord::query()->where('zone_set_version_id', '97144633')->count());
    }
}
