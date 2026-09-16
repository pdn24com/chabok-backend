<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Authorization\Application\AuthorizationCatalog;
use PHPUnit\Framework\TestCase;

final class ConfigurableOperationsAuthorizationCatalogTest extends TestCase
{
    public function test_network_and_fleet_permissions_have_stable_entitlement_ownership(): void
    {
        $permissions = AuthorizationCatalog::permissions();
        $entitlements = AuthorizationCatalog::administrativeEntitlements();

        self::assertCount(18, $entitlements);
        foreach ($entitlements as $permission => $entitlement) {
            self::assertArrayHasKey($permission, $permissions);
            self::assertTrue(in_array($entitlement, ['LiveOperations', 'Driver'], true));
            self::assertSame($entitlement, $permissions[$permission]);
        }
        self::assertSame('LiveOperations', $entitlements['network.route.publish']);
        self::assertSame('Driver', $entitlements['fleet.vehicle.manage']);
    }

    public function test_hq_admin_alone_receives_the_new_administration_permissions(): void
    {
        $newPermissions = array_keys(AuthorizationCatalog::administrativeEntitlements());
        $grants = AuthorizationCatalog::grants();

        self::assertSame([], array_values(array_diff($newPermissions, $grants['hq_admin'])));

        foreach ($grants as $roleCode => $rolePermissions) {
            if ($roleCode === 'hq_admin') {
                continue;
            }
            self::assertSame([], array_values(array_intersect($newPermissions, $rolePermissions)));
        }
    }

    public function test_wave2_operational_roles_remain_least_privilege(): void
    {
        $grants = AuthorizationCatalog::grants();

        self::assertContains('pickup_request.view', $grants['branch_operator']);
        self::assertContains('live_operations.view', $grants['branch_operator']);
        self::assertNotContains('live_operations.intervene', $grants['branch_operator']);
        self::assertNotContains('manifest.approve', $grants['branch_operator']);

        self::assertContains('pickup_request.assign', $grants['dispatcher']);
        self::assertContains('live_operations.intervene', $grants['dispatcher']);
        self::assertNotContains('manifest.approve', $grants['dispatcher']);

        self::assertContains('manifest.view', $grants['branch_read_only']);
        self::assertContains('pickup_request.view', $grants['branch_read_only']);
        self::assertContains('live_operations.view', $grants['branch_read_only']);
        self::assertSame([], array_values(array_filter(
            $grants['branch_read_only'],
            static fn (string $permission): bool => preg_match('/\.(create|edit|assign|reassign|cancel|approve|reopen|intervene|manage|publish)$/', $permission) === 1,
        )));
    }
}
