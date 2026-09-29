<?php

declare(strict_types=1);

namespace Tests\Feature;

use Mockery;
use Modules\Consignment\Application\Contracts\OperationalStatusAccessInterface;
use Modules\Consignment\Application\Dto\OperationalStatusDto;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusCommand;
use Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusHandler;
use Modules\Consignment\Infrastructure\Database\Seeders\OperationalStatusCatalogSeeder;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Tests\Support\InstallsNumericSchema;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class InternalDatabaseIdentityTest extends TestCase
{
    use InstallsNumericSchema;

    public function test_schema_installation_does_not_bootstrap_business_data(): void
    {
        self::assertSame(0, StatusRecord::query()->count());
        self::assertSame(0, RecordFixtureQuery::table('operational_status_catalog_lock')->count());
    }

    public function test_seed_is_repeatable_and_preserves_public_identity_and_edits(): void
    {
        $this->seed(OperationalStatusCatalogSeeder::class);
        $status = StatusRecord::query()->where('code', 'CFM')->sole();
        $id = $status->getKey();
        $publicId = $status->status_id;
        $count = StatusRecord::query()->count();
        $status->forceFill(['title_fa' => 'عنوان ویرایش‌شده'])->save();

        $this->seed(OperationalStatusCatalogSeeder::class);

        self::assertIsInt($id);
        self::assertSame((string) $id, $publicId);
        self::assertSame($id, $status->getRouteKey());
        self::assertSame($id, StatusRecord::query()->findOrFail($id)->getKey());
        self::assertSame($count, StatusRecord::query()->count());
        self::assertSame('عنوان ویرایش‌شده', $status->fresh()->title_fa);
        self::assertSame($id, $status->toArray()['id']);
    }

    public function test_revision_uses_numeric_foreign_key_and_preserves_public_snapshot(): void
    {
        $this->seed(OperationalStatusCatalogSeeder::class);
        $status = StatusRecord::query()->where('code', 'CFM')->sole();
        $saved = $this->saveStatus([...$status->attributesToArray(), 'title_fa' => 'عنوان جدید', 'expected_version' => 1], $status->status_id);
        $revision = RecordFixtureQuery::table('operational_status_revisions')->sole();
        self::assertSame(2, $saved->version);
        self::assertSame($status->getKey(), $revision->status_id);
        self::assertSame($status->status_id, json_decode($revision->snapshot, true, 512, JSON_THROW_ON_ERROR)['status_id']);
    }

    public function test_catalog_lock_is_created_without_seed_and_reused(): void
    {
        $input = ['scope' => 'GLOBAL', 'code' => 'CUSTOM_A', 'title_fa' => 'عنوان', 'tone' => 'neutral', 'status_group' => null, 'is_terminal' => false, 'is_active' => true, 'sort_order' => 1];
        $this->saveStatus($input);
        $this->saveStatus([...$input, 'code' => 'CUSTOM_B']);
        self::assertSame(1, RecordFixtureQuery::table('operational_status_catalog_lock')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->installNumericSchema();
    }

    private function saveStatus(array $input, ?string $id = null): StatusRecord
    {
        $access = Mockery::mock(OperationalStatusAccessInterface::class);
        $access->shouldReceive('access')->andReturn(new AccessContextDto(isPlatformAdmin: true));
        $access->shouldReceive('tenantManager')->andReturn(false);
        $this->app->instance(OperationalStatusAccessInterface::class, $access);
        $audit = Mockery::mock(AuditWriterInterface::class);
        $audit->shouldReceive('write')->andReturnNull();
        $this->app->instance(AuditWriterInterface::class, $audit);
        $actor = new AuthenticatedPrincipal((string) random_int(1, 2000000000), 'session', null, true);

        return $this->app->make(SaveOperationalStatusHandler::class)->handle(new SaveOperationalStatusCommand($actor, $id, OperationalStatusDto::fromValidated($input), (string) random_int(1, 2000000000)))->status;
    }
}
