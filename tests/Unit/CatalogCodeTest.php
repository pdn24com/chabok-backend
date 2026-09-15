<?php
declare(strict_types=1);
namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Modules\ServiceCatalog\Application\CatalogCode;
use Modules\ServiceCatalog\Infrastructure\Http\ServiceCatalogController;
use Tests\TestCase;

final class CatalogCodeTest extends TestCase
{
    public function test_generated_codes_are_six_digits_and_do_not_reuse_allocated_codes(): void
    {
        config(['database.default' => 'catalog_code_test', 'database.connections.catalog_code_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::statement('CREATE TABLE hq_tenants (hq_id TEXT PRIMARY KEY)');
        DB::statement('CREATE TABLE service_types (owner_key TEXT, code TEXT, UNIQUE(owner_key, code))');
        DB::table('hq_tenants')->insert(['hq_id' => 'tenant']);
        for ($i = 0; $i < 20; $i++) {
            DB::transaction(function (): void {
                $code = CatalogCode::generate('service_types', 'tenant');
                self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
                self::assertFalse(DB::table('service_types')->where('owner_key', 'tenant')->where('code', $code)->exists());
                DB::table('service_types')->insert(['owner_key' => 'tenant', 'code' => $code]);
            });
        }
        self::assertSame(20, DB::table('service_types')->count());
    }

    public function test_create_rules_allow_omitted_or_six_digit_code_and_reject_wrong_lengths(): void
    {
        $reflection = new \ReflectionClass(ServiceCatalogController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $rules = $reflection->getMethod('draftRules')->invoke($controller, 'service-types', true);
        foreach ([[], ['code' => '123456']] as $input) {
            self::assertTrue(validator($input, ['code' => $rules['code']])->passes());
        }
        self::assertFalse(validator(['code' => '12345'], ['code' => $rules['code']])->passes());
        self::assertFalse(validator(['code' => '1234567'], ['code' => $rules['code']])->passes());
    }
}
