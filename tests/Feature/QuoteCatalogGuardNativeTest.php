<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Dto\CommitmentZoneGroupDto;
use Modules\ServiceCatalog\Application\Dto\QuoteCatalogReferencesDto;
use Modules\ServiceCatalog\Application\Services\CurrentCatalog;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Tests\Support\CatalogTables;
use Tests\TestCase;

final class QuoteCatalogGuardNativeTest extends TestCase
{
    public function test_quote_evidence_checks_current_scoped_revisions_with_constant_queries_for_many_options(): void
    {
        config(['database.connections.quote_catalog' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('quote_catalog');
        foreach ([CatalogResource::Offering, CatalogResource::Option] as $kind) {
            foreach ([CatalogTables::identities($kind), CatalogTables::versions($kind)] as $query) {
                (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$query->getModel()->getTable().'.php'))[0])->up();
            }
        }
        $zones = Mockery::mock(CommitmentZoneResolverInterface::class);
        $zones->shouldReceive('group')->once()->with('245213294', '11710567', true)->andReturn(new CommitmentZoneGroupDto('11710567', 'new-zones', 'ZONES', 'Zones', []));
        $this->app->instance(CommitmentZoneResolverInterface::class, $zones);
        $guard = $this->app->make(CurrentCatalog::class);
        $this->record(CatalogResource::Offering, '90771604');
        $options = $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $id = \Tests\Support\FixtureId::from(sprintf('option-%02d', $index));
            $this->record(CatalogResource::Option, $id);
            $options[] = \Tests\Support\FixtureId::from($id.'-v1');
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                DB::transaction(fn () => $guard->assertQuoteCurrent(new QuoteCatalogReferencesDto('245213294', \Tests\Support\FixtureId::from('offering-v1'), optionVersionIds: $options)));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
        }
        self::assertSame([6, 6], $counts);
        foreach (['219161186', '106329882', 'newer', 'missing'] as $failure) {
            $this->record(CatalogResource::Option, $failure);
            if ($failure === '219161186') {
                CatalogTables::identities(CatalogResource::Option)->where('service_option_id', $failure)->update(['status' => 'INACTIVE']);
            } elseif ($failure === '106329882') {
                CatalogTables::identities(CatalogResource::Option)->where('service_option_id', $failure)->update(['hq_id' => '227711138']);
            } elseif ($failure === 'newer') {
                $version = CatalogTables::versions(CatalogResource::Option)->where('service_option_version_id', \Tests\Support\FixtureId::from($failure.'-v1'))->sole();
                $version->replicate()->forceFill(['service_option_version_id' => \Tests\Support\FixtureId::from($failure.'-v2'), 'version_number' => 2])->save();
            }
            try {
                DB::transaction(fn () => $guard->assertQuoteCurrent(new QuoteCatalogReferencesDto('245213294', \Tests\Support\FixtureId::from('offering-v1'), optionVersionIds: [$failure === 'missing' ? 'unknown' : \Tests\Support\FixtureId::from($failure.'-v1')])));
                self::fail('An unavailable or changed quote dependency must fail.');
            } catch (ApiException $error) {
                self::assertSame(422, $error->httpStatus);
                self::assertSame($failure === 'newer' ? 'CATALOG_CHANGED' : 'CATALOG_DEPENDENCY_UNAVAILABLE', $error->details['reason_code']);
            }
        }
        $references = QuoteCatalogReferencesDto::fromEvidence('245213294', \Tests\Support\FixtureId::from('offering-v1'), ['service' => [
            'service_type_version_id' => '19938311', 'shipping_method_version_id' => '95938240',
            'selected_services' => [['service_option_version_id' => '173214464']],
            'commitment' => ['schedule_version_id' => '100081670', 'destination_zone' => ['zone_set_id' => '11710567', 'zone_set_version_id' => '240020445']],
        ]]);
        self::assertSame('19938311', $references->typeVersionId);
        self::assertSame('95938240', $references->methodVersionId);
        self::assertSame('100081670', $references->scheduleVersionId);
        self::assertSame(['173214464'], $references->optionVersionIds);
        self::assertSame('11710567', $references->destinationZone->zoneSetId);
        try {
            $guard->assertQuoteCurrent($references);
            self::fail('Changed destination zone revision must reject the quote.');
        } catch (ApiException $error) {
            self::assertSame('CATALOG_CHANGED', $error->details['reason_code']);
        }
    }

    private function record(CatalogResource $resource, string $id): void
    {
        $id = \Tests\Support\FixtureId::from($id);
        CatalogTables::identities($resource)->insert([$resource->identityKey() => $id, 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => $id, 'status' => 'ACTIVE', 'created_by' => '84712523']);
        $columns = $resource === CatalogResource::Offering
            ? ['service_type_version_id' => '19938311', 'shipping_method_version_id' => '95938240', 'sla_policy' => '{}']
            : ['definition' => '{}'];
        CatalogTables::versions($resource)->insert([...$columns, $resource->identityKey() => $id, $resource->versionKey() => \Tests\Support\FixtureId::from($id.'-v1'),
            'hq_id' => '245213294', 'version_number' => 1, 'status' => 'PUBLISHED', 'labels' => '{}', 'created_by' => '84712523']);
    }
}
