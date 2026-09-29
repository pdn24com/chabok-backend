<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\CurrentCatalogInterface;
use Modules\ServiceCatalog\Application\Services\CurrentCatalog;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Tests\Support\CatalogTables;
use Tests\TestCase;

final class CurrentCatalogNativeTest extends TestCase
{
    public function test_native_current_resolution_preserves_stable_links_visibility_and_only_returns_the_needed_revision_id(): void
    {
        config(['database.connections.current_catalog_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('current_catalog_test');
        $resolver = $this->app->make(CurrentCatalog::class);
        foreach (CatalogResource::cases() as $kind) {
            foreach ([CatalogTables::identities($kind), CatalogTables::versions($kind)] as $query) {
                $table = $query->getModel()->getTable();
                (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
            }
            $identity = [
                $kind->identityKey() => '109704871', 'hq_id' => '245213294', 'owner_key' => '245213294',
                'code' => 'CATALOG', 'status' => 'ACTIVE', 'created_by' => '84712523',
            ];
            if ($kind === CatalogResource::CommitmentSchedule) {
                $identity['title'] = 'Schedule';
            }
            CatalogTables::identities($kind)->insert($identity);
            $columns = match ($kind) {
                CatalogResource::CommitmentSchedule => ['commitment_policy' => '{"include_holidays":true}'],
                CatalogResource::Offering => ['labels' => '{"fa":"خدمت"}', 'service_type_version_id' => '19938311', 'shipping_method_version_id' => '95938240', 'sla_policy' => '{}'],
                default => ['labels' => '{"fa":"خدمت"}', 'definition' => '{}'],
            };
            foreach ([1 => 'SUPERSEDED', 2 => 'PUBLISHED', 3 => 'DRAFT'] as $number => $status) {
                CatalogTables::versions($kind)->insert([
                    ...$columns, $kind->identityKey() => '109704871', $kind->versionKey() => \Tests\Support\FixtureId::from('revision-'.$number),
                    'hq_id' => '245213294', 'version_number' => $number, 'status' => $status, 'created_by' => '84712523',
                ]);
            }
            foreach (['109704871', '165701929', '72668024', '15274671'] as $reference) {
                DB::connection()->enableQueryLog();
                DB::connection()->flushQueryLog();
                try {
                    self::assertSame('72668024', $resolver->currentVersion($kind, $reference, '245213294'));
                    self::assertCount(2, DB::connection()->getQueryLog());
                } finally {
                    DB::connection()->disableQueryLog();
                }
            }
            self::assertSame(['165701929', '72668024', '15274671'], $resolver->relatedVersions($kind, '165701929'));
            $this->assertUnavailable($resolver, $kind, '106329882');
            if ($kind !== CatalogResource::CommitmentSchedule) {
                CatalogTables::identities($kind)->where($kind->identityKey(), '109704871')->update(['hq_id' => null]);
                self::assertSame('72668024', $resolver->currentVersion($kind, '109704871', '106329882'));
            }
            CatalogTables::identities($kind)->where($kind->identityKey(), '109704871')->update(['status' => 'INACTIVE']);
            $this->assertUnavailable($resolver, $kind, '245213294');
            CatalogTables::identities($kind)->where($kind->identityKey(), '109704871')->update(['status' => 'ACTIVE']);
            CatalogTables::versions($kind)->where('status', 'PUBLISHED')->update(['status' => 'SUPERSEDED']);
            $this->assertUnavailable($resolver, $kind, '245213294');
        }
        try {
            $this->app->make(CatalogResourceDefinitionInterface::class)->resource('unknown');
            self::fail('Unknown catalog resources must return the existing 404 response.');
        } catch (ApiException $error) {
            self::assertSame(404, $error->httpStatus);
        }
    }

    private function assertUnavailable(CurrentCatalogInterface $resolver, CatalogResource $resource, string $tenant): void
    {
        try {
            $resolver->currentVersion($resource, '109704871', $tenant);
            self::fail('Inactive, unpublished or foreign catalog identities must not resolve.');
        } catch (ApiException $error) {
            self::assertSame(422, $error->httpStatus);
            self::assertSame('CATALOG_DEPENDENCY_UNAVAILABLE', $error->details['reason_code']);
            self::assertSame($resource->value, $error->details['resource']);
        }
    }
}
