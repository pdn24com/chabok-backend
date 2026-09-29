<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Authorization\Application\Contracts\RoleNavigationInterface;
use Modules\Authorization\Application\Repositories\RoleNavigationRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final class RoleNavigation implements RoleNavigationInterface
{
    public function __construct(
        private readonly ClockInterface $clock,
        private RoleNavigationRepositoryInterface $roleNavigationRepository,
    ) {}

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
        if (! $this->roleNavigationRepository->hasPreference($roleId)) {
            return null;
        }

        return $this->selectedKeys([$roleId]);
    }

    /** Caller owns the role transaction and authorization. @param list<string>|null $keys */
    public function replace(string $roleId, ?array $keys): void
    {
        if ($keys !== null && (array_diff($keys, self::keys()) !== [] || count(array_unique($keys)) !== count($keys))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.invalid_menu_selection');
        }
        $at = $this->clock->now();
        // Replace the selected menu set inside the caller's transaction.
        $this->roleNavigationRepository->deleteItems($roleId);
        if ($keys === null) {
            $this->roleNavigationRepository->deletePreference($roleId);

            return;
        }
        $this->roleNavigationRepository->touchPreference($roleId, $at);
        if ($keys !== []) {
            $this->roleNavigationRepository->insertItems($roleId, $keys);
        }
    }

    /** @param list<string> $roleIds @return list<string>|null */
    public function effective(array $roleIds): ?array
    {
        if ($roleIds === []) {
            return [];
        }
        if ($this->roleNavigationRepository->preferenceCount($roleIds) < count($roleIds)) {
            return null;
        }

        return $this->selectedKeys($roleIds);
    }

    private function selectedKeys(array $roleIds): array
    {
        return $this->roleNavigationRepository->selectedKeys($roleIds);
    }
}
