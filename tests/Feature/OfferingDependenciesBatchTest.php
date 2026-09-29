<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;
use Modules\ServiceCatalog\Application\Services\OfferingDependencies;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Tests\Support\CatalogTables;
use Tests\TestCase;

final class OfferingDependenciesBatchTest extends TestCase
{
    public function test_runtime_dependencies_load_current_tenant_and_platform_revisions_once_for_many_offerings(): void
    {
        config(['database.connections.offering_dependencies' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('offering_dependencies');
        foreach ([CatalogResource::ServiceType, CatalogResource::ShippingMethod, CatalogResource::Offering] as $resource) {
            foreach ([CatalogTables::identities($resource), CatalogTables::versions($resource)] as $query) {
                (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$query->getModel()->getTable().'.php'))[0])->up();
            }
        }
        $resolver = $this->app->make(OfferingDependencies::class);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            foreach ([CatalogResource::ServiceType, CatalogResource::ShippingMethod, CatalogResource::Offering] as $resource) {
                $identity = \Tests\Support\FixtureId::from($resource->value.'-'.$index);
                CatalogTables::identities($resource)->insert([$resource->identityKey() => $identity, 'hq_id' => $resource === CatalogResource::ServiceType ? null : '245213294',
                    'owner_key' => $resource === CatalogResource::ServiceType ? 'PLATFORM' : '245213294', 'code' => $identity, 'status' => 'ACTIVE', 'created_by' => '84712523']);
                foreach ([1 => 'PUBLISHED', 2 => 'PUBLISHED', 3 => 'DRAFT'] as $number => $status) {
                    $extra = $resource === CatalogResource::Offering
                        ? ['service_type_version_id' => \Tests\Support\FixtureId::from('service-types-'.$index.'-v1'), 'shipping_method_version_id' => \Tests\Support\FixtureId::from('shipping-methods-'.$index.'-v1'), 'sla_policy' => '{}']
                        : ['definition' => '{}'];
                    CatalogTables::versions($resource)->insert([...$extra, $resource->identityKey() => $identity, $resource->versionKey() => \Tests\Support\FixtureId::from($identity.'-v'.$number),
                        'hq_id' => '245213294', 'version_number' => $number, 'status' => $status, 'labels' => json_encode(['fa' => $identity.'-'.$number]), 'created_by' => '84712523']);
                }
            }
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            $rows = ServiceOfferingVersionRecord::query()->where('version_number', 1)->with(['offering', 'serviceTypeVersion', 'shippingMethodVersion'])->get();
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $dependencies = $resolver->currentFor($rows, '245213294');
                $counts[] = count(DB::connection()->getQueryLog());
                DB::connection()->flushQueryLog();
                foreach ($rows as $row) {
                    $current = $dependencies[$row->service_offering_version_id];
                    $resolver->assertAvailable($current);
                    $document = CatalogDocument::offering($row, $current);
                    self::assertSame(\Tests\Support\FixtureId::from($row->serviceTypeVersion->service_type_id.'-v2'), $document['service_type_version_id']);
                    self::assertSame(\Tests\Support\FixtureId::from($row->shippingMethodVersion->shipping_method_id.'-v2'), $document['shipping_method_version_id']);
                    self::assertSame(['fa' => $row->serviceTypeVersion->service_type_id.'-2'], $document['service_type_labels']);
                    self::assertArrayHasKey('id', $document);
                }
                self::assertSame([], DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
        }
        self::assertSame([2, 2], $counts);
        CatalogTables::identities(CatalogResource::ServiceType)->where('service_type_id', '226153776')->update(['status' => 'INACTIVE']);
        CatalogTables::identities(CatalogResource::ShippingMethod)->where('shipping_method_id', '140539322')->update(['hq_id' => '106329882']);
        $current = $resolver->currentFor($rows, '245213294');
        foreach (['offerings-1-v1' => 'service-types', 'offerings-2-v1' => 'shipping-methods'] as $id => $resource) {
            self::assertFalse($current[\Tests\Support\FixtureId::from($id)]->available());
            try {
                $resolver->assertAvailable($current[\Tests\Support\FixtureId::from($id)]);
                self::fail('An unavailable dependency must reject selection.');
            } catch (ApiException $error) {
                self::assertSame('CATALOG_DEPENDENCY_UNAVAILABLE', $error->details['reason_code']);
                self::assertSame($resource, $error->details['resource']);
            }
        }
    }
}
