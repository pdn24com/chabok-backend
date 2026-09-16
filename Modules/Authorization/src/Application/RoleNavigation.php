<?php

declare(strict_types=1);

namespace Modules\Authorization\Application;

use Modules\Authorization\Application\Repositories\RoleNavigationRepository;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class RoleNavigation
{
    public function __construct(private readonly RoleNavigationRepository $navigation, private readonly Clock $clock)
    {
    }
    /** Sidebar leaves only; parent groups derive from their children. @return list<string> */

    public static function keys(): array
    {
        return [
            'dashboard',
            'consignments',
            'manifests',
            'users',
            'roles',
            'entitlements',
            'operational-statuses',
            'consignment-number-ranges',
            'service-catalog-overview',
            'service-catalog-service-types',
            'service-catalog-shipping-methods',
            'service-catalog-offerings',
            'service-catalog-options',
            'service-catalog-commitment-schedules',
            'pricing-overview',
            'pricing-tariffs',
            'pricing-zones',
            'pricing-charge-types',
            'pricing-simulator',
            'network-areas',
            'network-nodes',
            'network-coverage',
            'network-routes',
            'fleet-drivers',
            'fleet-vehicles',
            'profile',
            'sessions',
        ];
    }
    /** Null retains automatic permission-based navigation. @return list<string>|null */

    public function forRole(string $roleId): ?array
    {
        return $this->navigation->selection($roleId);
    }
    /** Caller owns the role transaction and authorization. @param list<string>|null $keys */

    public function replace(string $roleId, ?array $keys): void
    {
        if ($keys !== null && (array_diff($keys, self::keys()) !== [] || count(array_unique($keys)) !== count($keys))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Invalid menu selection.');
        }
        $this->navigation->replace($roleId, $keys, $this->clock->now());
    }
    /** @param list<string> $roleIds @return list<string>|null */

    public function effective(array $roleIds): ?array
    {
        if ($roleIds === []) {
            return [];
        }
        if ($this->navigation->configuredRoleCount($roleIds) < count($roleIds)) {
            return null;
        }
        return $this->navigation->selectedKeys($roleIds);
    }
}
