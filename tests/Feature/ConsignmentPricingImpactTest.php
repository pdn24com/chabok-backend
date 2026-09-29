<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Consignment\Application\Services\EditPricingImpact;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ConsignmentPricingImpactTest extends TestCase
{
    public function test_safe_contact_fields_follow_accepted_and_current_commitment_geography_with_tenant_and_version_scope(): void
    {
        config(['database.connections.impact_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('impact_test');
        foreach ([
            'pricing_snapshots' => ['hq_id', 'pricing_snapshot_id', 'quote_id'],
            'pricing_quotes' => ['quote_id', 'zone_set_version_id'],
            'pricing_zone_set_versions' => ['hq_id', 'zone_set_version_id', 'pricing_zone_set_id', 'status'],
            'pricing_zones' => ['pricing_zone_id', 'zone_set_version_id'],
            'pricing_zone_members' => ['zone_member_id', 'pricing_zone_id', 'member_type'],
            'commitment_schedule_versions' => ['commitment_schedule_version_id', 'commitment_schedule_id', 'status', 'commitment_policy', 'version_number'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns): void {
                $table->increments('id');
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
        $consignment = (new ConsignmentRecord)->forceFill(['hq_id' => '245213294', 'active_pricing_snapshot_id' => null]);
        $impact = $this->app->make(EditPricingImpact::class);
        self::assertSame(['contact_name', 'mobile', 'phone'], $impact->contactFields($consignment));
        RecordFixtureQuery::table('pricing_quotes')->insert(['quote_id' => '103969350', 'zone_set_version_id' => '7389537']);
        RecordFixtureQuery::table('pricing_snapshots')->insert(['hq_id' => '245213294', 'pricing_snapshot_id' => '23727852', 'quote_id' => '103969350']);
        $consignment->active_pricing_snapshot_id = '23727852';
        RecordFixtureQuery::table('pricing_zone_set_versions')->insert(['hq_id' => '245213294', 'zone_set_version_id' => '7389537', 'pricing_zone_set_id' => '182007549', 'status' => 'SUPERSEDED']);
        RecordFixtureQuery::table('pricing_zones')->insert(['pricing_zone_id' => '88335165', 'zone_set_version_id' => '7389537']);
        self::assertSame(['contact_name', 'mobile', 'phone', 'address_text', 'latitude', 'longitude', 'postal_code'], $impact->contactFields($consignment));
        foreach (['106329882' => '106329882', '125058276' => '245213294'] as $version => $tenant) {
            RecordFixtureQuery::table('pricing_zone_set_versions')->insert(['hq_id' => $tenant, 'zone_set_version_id' => $version, 'pricing_zone_set_id' => '182007549', 'status' => (string) $version === '125058276' ? 'DRAFT' : 'PUBLISHED']);
            RecordFixtureQuery::table('pricing_zones')->insert(['pricing_zone_id' => $version, 'zone_set_version_id' => $version]);
            RecordFixtureQuery::table('pricing_zone_members')->insert(['zone_member_id' => $version, 'pricing_zone_id' => $version, 'member_type' => 'POLYGON']);
        }
        self::assertContains('latitude', $impact->contactFields($consignment));
        RecordFixtureQuery::table('pricing_zone_members')->insert(['zone_member_id' => '45330818', 'pricing_zone_id' => '88335165', 'member_type' => 'POLYGON']);
        self::assertSame(['contact_name', 'mobile', 'phone', 'address_text', 'postal_code'], $impact->contactFields($consignment));
        RecordFixtureQuery::table('commitment_schedule_versions')->insert([
            ['commitment_schedule_version_id' => '268120137', 'commitment_schedule_id' => '100081670', 'status' => 'SUPERSEDED', 'version_number' => 1, 'commitment_policy' => '{}'],
            ['commitment_schedule_version_id' => '184870379', 'commitment_schedule_id' => '100081670', 'status' => 'PUBLISHED', 'version_number' => 2, 'commitment_policy' => '{"zone_set_id":"65783294"}'],
        ]);
        $consignment->commitment_schedule_version_id = '268120137';
        RecordFixtureQuery::table('pricing_zone_set_versions')->insert(['hq_id' => null, 'zone_set_version_id' => '134224936', 'pricing_zone_set_id' => '65783294', 'status' => 'PUBLISHED']);
        RecordFixtureQuery::table('pricing_zones')->insert(['pricing_zone_id' => '229683387', 'zone_set_version_id' => '134224936']);
        RecordFixtureQuery::table('pricing_zone_members')->insert(['zone_member_id' => '68224190', 'pricing_zone_id' => '229683387', 'member_type' => 'POSTAL_RANGE']);
        self::assertSame(['contact_name', 'mobile', 'phone', 'address_text'], $impact->contactFields($consignment));
        $consignment->hq_id = '106329882';
        self::assertSame(['contact_name', 'mobile', 'phone'], $impact->contactFields($consignment));
    }
}
