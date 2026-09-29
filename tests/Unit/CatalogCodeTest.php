<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Modules\ServiceCatalog\Application\Services\CatalogCode;
use Modules\ServiceCatalog\Presentation\Http\Requests\CatalogRequestRules;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class CatalogCodeTest extends TestCase
{
    public function test_generated_codes_are_six_digits_and_do_not_reuse_allocated_codes(): void
    {
        config([
            'database.default' => 'catalog_code_test',
            'database.connections.catalog_code_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::statement('CREATE TABLE hq_tenants (id INTEGER PRIMARY KEY AUTOINCREMENT, hq_id TEXT UNIQUE)');
        DB::statement('CREATE TABLE service_types (owner_key TEXT, code TEXT, UNIQUE(owner_key, code))');
        RecordFixtureQuery::table('hq_tenants')->insert(['hq_id' => '245213294']);
        for ($i = 0; $i < 20; $i++) {
            DB::transaction(function (): void {
                $code = $this->app->make(CatalogCode::class)->generate('service-types', '245213294');
                self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
                self::assertFalse(RecordFixtureQuery::table('service_types')->where('owner_key', '245213294')->where('code', $code)->exists());
                RecordFixtureQuery::table('service_types')->insert(['owner_key' => '245213294', 'code' => $code]);
            });
        }
        self::assertSame(20, RecordFixtureQuery::table('service_types')->count());
    }

    public function test_create_rules_allow_omitted_or_six_digit_code_and_reject_wrong_lengths(): void
    {
        $rules = CatalogRequestRules::draftRules('service-types', true);
        foreach ([[], ['code' => '123456']] as $input) {
            self::assertTrue(validator($input, ['code' => $rules['code']])->passes());
        }
        self::assertFalse(validator(['code' => '12345'], ['code' => $rules['code']])->passes());
        self::assertFalse(validator(['code' => '1234567'], ['code' => $rules['code']])->passes());
    }
}
