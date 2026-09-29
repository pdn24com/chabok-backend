<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;
use Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryCommand;
use Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryHandler;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesCommand;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesHandler;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsCommand;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsHandler;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Tests\Support\AccessContexts;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class CatalogNativeReadTest extends TestCase
{
    public function test_history_loads_every_child_family_in_constant_queries_and_serializes_without_queries(): void
    {
        $this->identity('90771604');
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $version = \Tests\Support\FixtureId::from('version-'.$index);
            $this->version('90771604', $version, $index);
            RecordFixtureQuery::table('service_offering_option_rules')->insert(['offering_option_rule_id' => \Tests\Support\FixtureId::from('rule-'.$index), 'service_offering_version_id' => $version, 'service_option_version_id' => '168929119', 'compatibility' => 'CONDITIONAL', 'condition' => '{"flag":true}']);
            RecordFixtureQuery::table('service_eligibility_rules')->insert(['eligibility_rule_id' => \Tests\Support\FixtureId::from('eligibility-'.$index), 'service_offering_version_id' => $version, 'dimension' => 'PHYSICAL', 'fact_key' => 'weight', 'operator' => 'MAX', 'expected_value' => '25', 'reason_code' => 'TOO_HEAVY']);
            RecordFixtureQuery::table('service_coverage_references')->insert(['coverage_reference_id' => \Tests\Support\FixtureId::from('coverage-'.$index), 'service_offering_version_id' => $version, 'direction' => 'BOTH', 'reference_type' => 'COUNTRY', 'reference_value' => 'IR']);
            RecordFixtureQuery::table('service_availability_bindings')->insert(['availability_binding_id' => \Tests\Support\FixtureId::from('availability-'.$index), 'service_offering_version_id' => $version, 'scope_type' => 'TENANT', 'scope_value' => '245213294']);
            RecordFixtureQuery::table('service_offering_commitment_bindings')->insert(['offering_commitment_binding_id' => \Tests\Support\FixtureId::from('binding-'.$index), 'service_offering_version_id' => $version, 'commitment_schedule_version_id' => '100081670', 'pickup_mode' => 'NONE', 'delivery_mode' => 'NONE']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            $history = $this->measure($counts, fn () => $this->app->make(GetCatalogHistoryHandler::class)->handle(new GetCatalogHistoryCommand($this->actor(), 'offerings', '90771604')));
            self::assertCount($index, $history);
            self::assertSame($index, $history[0]['version_number']);
            self::assertSame(['fa' => 'خدمت'], $history[0]['labels']);
            self::assertSame(['flag' => true], $history[0]['option_rules'][0]['condition']);
            self::assertSame(25, $history[0]['eligibility_rules'][0]['expected_value']);
            self::assertSame('100081670', $history[0]['commitment_binding']['commitment_schedule_version_id']);
            foreach (['option_rules', 'eligibility_rules', 'coverage_references', 'availability_bindings'] as $child) {
                self::assertCount(1, $history[0][$child]);
                self::assertArrayHasKey('id', $history[0][$child][0]);
            }
            self::assertArrayHasKey('id', $history[0]);
        }
        self::assertSame([8, 8], $counts);
        $version = $this->app->make(CatalogRepositoryInterface::class)->findVisibleVersionDetail(CatalogResource::Offering, $version, '245213294');
        $serialization = [];
        $this->measure($serialization, fn () => CatalogDocument::version($version));
        self::assertSame([0], $serialization);
        self::assertNull($this->app->make(CatalogRepositoryInterface::class)->findVisibleVersionDetail(CatalogResource::Offering, $version->service_offering_version_id, '106329882'));
    }

    public function test_published_inclusions_batch_latest_identity_links_and_do_not_bypass_tenant_visibility(): void
    {
        $this->identity('106329882', '106329882');
        $this->version('106329882', '262456316', 1, '106329882');
        $included = ['106329882'];
        $publishedCounts = [];
        $listCounts = [];
        for ($index = 1; $index <= 40; $index++) {
            $id = \Tests\Support\FixtureId::from('offering-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
            $this->identity($id);
            RecordFixtureQuery::table('service_offerings')->where('service_offering_id', $id)->update(['code' => sprintf('offering-%02d', $index)]);
            $this->version($id, \Tests\Support\FixtureId::from($id.'-old'), 1);
            $this->version($id, \Tests\Support\FixtureId::from($id.'-latest'), 2);
            $included[] = $id;
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            $page = $this->measure($publishedCounts, fn () => $this->app->make(ListPublishedCatalogVersionsHandler::class)->handle(new ListPublishedCatalogVersionsCommand($this->actor(), 'offerings', ['include_version_ids' => $included, 'search' => 'no-match'])));
            self::assertSame($index, $page->total());
            self::assertSame('208025567', $page->items()[0]['service_offering_version_id']);
            self::assertSame('DRAFT', $page->items()[0]['status']);
            self::assertSame('ACTIVE', $page->items()[0]['identity_status']);
            self::assertArrayHasKey('id', $page->items()[0]);
            $identities = $this->measure($listCounts, fn () => $this->app->make(ListCatalogIdentitiesHandler::class)->handle(new ListCatalogIdentitiesCommand($this->actor(), 'offerings', ['page_size' => 100])));
            self::assertSame($index, $identities->total());
            self::assertSame(2, $identities->items()[0]['latest_version_number']);
            self::assertSame(['fa' => 'خدمت'], $identities->items()[0]['labels']);
        }
        self::assertSame([5, 5], $publishedCounts);
        self::assertSame([3, 3], $listCounts);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.catalog_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('catalog_read_test');
        foreach (['service_offerings', 'service_offering_versions', 'service_offering_option_rules', 'service_eligibility_rules', 'service_coverage_references', 'service_availability_bindings', 'service_offering_commitment_bindings'] as $table) {
            (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $authorization = Mockery::mock(AccessContextResolverInterface::class);
        $authorization->shouldReceive('resolve')->andReturn(AccessContexts::make([
            'hq_id' => '245213294', 'permissions' => ['service_catalog.view', 'service_catalog.history.view'],
            'module_entitlements' => [['module_code' => 'ServiceCatalog', 'status' => 'ENABLED']],
        ]));
        $this->app->instance(AccessContextResolverInterface::class, $authorization);
    }

    private function identity(string $id, string $tenant = '245213294'): void
    {
        RecordFixtureQuery::table('service_offerings')->insert(['service_offering_id' => $id, 'hq_id' => $tenant, 'owner_key' => $tenant, 'code' => $id, 'created_by' => '84712523']);
    }

    private function version(string $id, string $version, int $number, string $tenant = '245213294'): void
    {
        RecordFixtureQuery::table('service_offering_versions')->insert(['service_offering_id' => $id, 'service_offering_version_id' => $version, 'hq_id' => $tenant, 'version_number' => $number, 'service_type_version_id' => '19938311', 'shipping_method_version_id' => '95938240', 'labels' => '{"fa":"خدمت"}', 'sla_policy' => '{}', 'created_by' => '84712523']);
    }

    private function actor(): AuthenticatedPrincipal
    {
        return new AuthenticatedPrincipal('84712523', 'session', '245213294', false);
    }

    private function measure(array &$counts, callable $read): mixed
    {
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $result = $read();
            $counts[] = count(DB::connection()->getQueryLog());

            return $result;
        } finally {
            DB::connection()->disableQueryLog();
        }
    }
}
