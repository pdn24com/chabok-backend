<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Modules\Authorization\Application\Serialization\RoleDocument;
use Modules\Authorization\Application\Services\AuthorizationCacheInvalidator;
use Modules\Authorization\Application\Services\AuthorizationGuard;
use Modules\Authorization\Application\Services\RoleNavigation;
use Modules\Authorization\Application\Services\RolePermissionWriter;
use Modules\Authorization\Application\Services\RoleReader;
use Modules\Authorization\Application\Services\TenantAssignmentWriter;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\PermissionRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\RolePermissionRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;
use Modules\Organization\Infrastructure\Adapters\EloquentScopeTopology;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AuthorizationNativeBatchTest extends TestCase
{
    public static function roleCounts(): array
    {
        return [[1], [40]];
    }

    #[DataProvider('roleCounts')]
    public function test_role_payloads_eager_load_permissions_and_distinguish_automatic_empty_and_selected_menus(int $count): void
    {
        $reader = $this->app->make(RoleReader::class);
        $navigation = $this->app->make(RoleNavigation::class);
        $this->permission('93644058', 'INACTIVE');
        $this->permission('212432914');
        for ($i = 0; $i < $count; $i++) {
            $id = \Tests\Support\FixtureId::from(sprintf('role-%02d', $i));
            $this->role($id, $i === 0 ? null : '245213294');
            RoleRecord::query()->whereKey($id)->update(['role_code' => sprintf('role-%02d', $i)]);
            foreach (['93644058', '212432914'] as $code) {
                RolePermissionRecord::query()->forceCreate(['role_permission_id' => \Tests\Support\FixtureId::from("$id-$code"), 'role_id' => $id, 'permission_id' => \Tests\Support\FixtureId::from($code)]);
            }
            $navigation->replace($id, $i % 3 === 0 ? null : ($i % 3 === 1 ? [] : ['users', 'dashboard']));
        }
        $this->role('106329882', '227711138');
        DB::connection()->enableQueryLog();
        try {
            $roles = $reader->visibleRoles('245213294')->map(RoleDocument::serialize(...))->all();
            self::assertCount(4, DB::connection()->getQueryLog());
            self::assertCount($count, $roles);
            foreach ($roles as $i => $role) {
                self::assertSame(\Tests\Support\FixtureId::from(sprintf('role-%02d', $i)), $role['role_id']);
                self::assertSame(['212432914', '93644058'], $role['permission_codes']);
                self::assertSame($i % 3 === 0 ? null : ($i % 3 === 1 ? [] : ['dashboard', 'users']), $role['menu_keys']);
                self::assertTrue($role['is_cloneable']);
                self::assertArrayNotHasKey('id', $role);
            }
            self::assertCount(4, DB::connection()->getQueryLog(), 'Payload serialization must not issue queries.');
        } finally {
            DB::connection()->disableQueryLog();
        }
        self::assertSame($roles[0], RoleDocument::serialize($reader->role('235055944')));
        self::assertCount(1, $reader->visibleRoles(null));
        self::assertNull($navigation->effective(['235055944']));
        $navigation->replace('235055944', []);
        self::assertSame([], $navigation->effective(['235055944']));
        $navigation->replace('235055944', ['users']);
        self::assertSame(['users'], $navigation->effective(['235055944']));
        $navigation->replace('235055944', null);
        self::assertNull($navigation->forRole('235055944'));
    }

    public function test_grants_are_validated_before_bounded_insertion_and_rollback_with_the_callers_transaction(): void
    {
        $this->role('55182337', '245213294');
        $codes = [];
        for ($i = 0; $i < 205; $i++) {
            $codes[] = $code = \Tests\Support\FixtureId::from("permission-$i");
            $this->permission($code);
        }
        $writer = $this->app->make(RolePermissionWriter::class);
        DB::connection()->enableQueryLog();
        try {
            $writer->insertRolePermissions('55182337', [...$codes, $codes[0]], '84712523');
            self::assertCount(4, DB::connection()->getQueryLog(), 'One permission query and three batches.');
        } finally {
            DB::connection()->disableQueryLog();
        }
        self::assertSame(205, RolePermissionRecord::query()->count());
        self::assertSame(1, RolePermissionRecord::query()->distinct()->count('created_at'));
        self::assertSame(205, RolePermissionRecord::query()->distinct()->count('role_permission_id'));
        $this->permission('219161186', 'INACTIVE');
        try {
            $writer->insertRolePermissions('invalid', ['permission-0', '219161186'], '84712523');
            self::fail('Inactive permissions must be rejected before insertion.');
        } catch (ApiException) {
            self::assertFalse(RolePermissionRecord::query()->where('role_id', 'invalid')->exists());
        }
        DB::beginTransaction();
        $writer->insertRolePermissions('228742273', $codes, '84712523');
        DB::rollBack();
        self::assertFalse(RolePermissionRecord::query()->where('role_id', '228742273')->exists());
    }

    #[DataProvider('roleCounts')]
    public function test_assignments_batch_reads_and_writes_preserve_order_and_reject_duplicates_without_partial_writes(int $count): void
    {
        foreach (['areas', 'nodes', 'area_hierarchies'] as $table) {
            (require glob(base_path('Modules/Organization/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $this->permission('iam.roles.assign');
        $this->permission('consignment.view');
        $this->role('195776946', '245213294');
        foreach (['iam.roles.assign', 'consignment.view'] as $code) {
            RolePermissionRecord::query()->forceCreate(['role_permission_id' => \Tests\Support\FixtureId::from('actor-'.$code), 'role_id' => '195776946', 'permission_id' => \Tests\Support\FixtureId::from($code)]);
        }
        AssignmentRecord::query()->forceCreate(['assignment_id' => '147276922', 'hq_id' => '245213294', 'user_id' => '84712523', 'role_id' => '195776946',
            'scope_type' => 'TENANT', 'includes_descendants' => false, 'status' => 'ACTIVE', 'active_slot' => 'actor-slot', 'assigned_by' => '84712523']);
        $inputs = [];
        for ($index = 0; $index < $count; $index++) {
            $id = \Tests\Support\FixtureId::from('target-role-'.$index);
            $this->role($id, '245213294');
            RolePermissionRecord::query()->forceCreate(['role_permission_id' => \Tests\Support\FixtureId::from($id.'-permission'), 'role_id' => $id, 'permission_id' => \Tests\Support\FixtureId::from('consignment.view')]);
            $inputs[] = new RoleAssignment($id, new PermissionScope(ScopeType::TENANT, null));
        }
        $cache = new CacheRepository(new ArrayStore);
        $invalidator = $this->app->makeWith(AuthorizationCacheInvalidator::class, ['authorizationCache' => $cache]);
        $cache->put($invalidator->cacheKey('84712523'), new AccessContextDto(hqId: '245213294', permissions: ['iam.roles.assign', 'consignment.view'],
            permissionScopes: ['iam.roles.assign' => [new PermissionScope(ScopeType::TENANT, null)], 'consignment.view' => [new PermissionScope(ScopeType::TENANT, null)]]));
        $topology = new EloquentScopeTopology;
        $clock = $this->app->make(ClockInterface::class);
        $context = $this->app->makeWith(ResolveContextHandler::class, ['authorizationCacheInvalidator' => $invalidator, 'authorizationCache' => $cache]);
        $guard = $this->app->makeWith(AuthorizationGuard::class, ['resolveContextHandler' => $context]);
        $writer = $this->app->makeWith(TenantAssignmentWriter::class, ['authorizationGuard' => $guard]);
        $actor = new AuthenticatedPrincipal('84712523', 'session', '245213294', false);
        DB::beginTransaction();
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $created = $writer->createTenantAssignments($actor, '185082321', '245213294', $inputs);
            self::assertCount(10, DB::connection()->getQueryLog(), 'One or forty assignments use the same query count.');
        } finally {
            DB::connection()->disableQueryLog();
            DB::commit();
        }
        self::assertSame(array_map(fn (RoleAssignment $input): string => $input->roleId, $inputs), array_map(fn (AssignmentRecord $row): string => $row->role_id, $created));
        foreach ($created as $record) {
            self::assertTrue($record->exists);
            self::assertIsInt($record->id);
            self::assertSame('185082321', $record->user_id);
        }
        foreach (['already-created', 'duplicate-within-request'] as $case) {
            $userId = $case === 'already-created' ? '185082321' : '108152664';
            try {
                DB::transaction(fn () => $writer->createTenantAssignments($actor, $userId, '245213294', [$inputs[0], $inputs[0]]));
                self::fail('Duplicate active slots must fail.');
            } catch (ApiException $error) {
                self::assertSame(ApiErrorCode::Conflict, $error->errorCode);
            }
        }
        self::assertFalse(AssignmentRecord::query()->where('user_id', '108152664')->exists());
        self::assertSame($count, AssignmentRecord::query()->where('user_id', '185082321')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.auth_batch' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('auth_batch');
        foreach (glob(base_path('Modules/Authorization/database/migrations/*.php')) as $migration) {
            (require $migration)->up();
        }
    }

    private function role(string $id, ?string $tenant): void
    {
        RoleRecord::query()->forceCreate(['role_id' => $id, 'hq_id' => $tenant, 'owner_key' => $tenant ?? '134224936',
            'role_code' => $id, 'role_title' => $id, 'role_kind' => 'CUSTOM', 'is_cloneable' => true]);
    }

    private function permission(string $code, string $status = 'ACTIVE'): void
    {
        PermissionRecord::query()->forceCreate(['permission_id' => \Tests\Support\FixtureId::from($code), 'permission_code' => $code,
            'module_code' => 'iam', 'resource_code' => 'roles', 'action_code' => 'view', 'description' => $code, 'status' => $status]);
    }
}
