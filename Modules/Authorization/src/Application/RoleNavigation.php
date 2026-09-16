<?php

declare(strict_types=1);

namespace Modules\Authorization\Application;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class RoleNavigation
{
    /** Sidebar leaves only; parent groups derive from their children. @return list<string> */
    public static function keys(): array
    {
        return [
            'dashboard', 'consignments', 'manifests', 'users', 'roles',
            'entitlements', 'operational-statuses', 'consignment-number-ranges',
            'service-catalog-overview', 'service-catalog-service-types', 'service-catalog-shipping-methods',
            'service-catalog-offerings', 'service-catalog-options', 'service-catalog-commitment-schedules',
            'pricing-overview', 'pricing-tariffs', 'pricing-zones', 'pricing-charge-types', 'pricing-simulator',
            'network-areas', 'network-nodes', 'network-coverage', 'network-routes',
            'fleet-drivers', 'fleet-vehicles', 'profile', 'sessions',
        ];
    }

    /** Null retains automatic permission-based navigation. @return list<string>|null */
    public function forRole(string $roleId): ?array
    {
        if (! DB::table('role_menu_preferences')->where('role_id', $roleId)->exists()) {
            return null;
        }
        return DB::table('role_menu_items')->where('role_id', $roleId)->orderBy('menu_key')->pluck('menu_key')->all();
    }

    /** Caller owns the role transaction and authorization. @param list<string>|null $keys */
    public function replace(string $roleId, ?array $keys): void
    {
        if ($keys !== null && (array_diff($keys, self::keys()) !== [] || count(array_unique($keys)) !== count($keys))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Invalid menu selection.');
        }
        DB::table('role_menu_items')->where('role_id', $roleId)->delete();
        if ($keys === null) {
            DB::table('role_menu_preferences')->where('role_id', $roleId)->delete();
            return;
        }
        DB::table('role_menu_preferences')->insertOrIgnore(['role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_menu_preferences')->where('role_id', $roleId)->update(['updated_at' => now()]);
        foreach ($keys as $key) {
            DB::table('role_menu_items')->insert(['role_id' => $roleId, 'menu_key' => $key]);
        }
    }

    /** @param list<string> $roleIds @return list<string>|null */
    public function effective(array $roleIds): ?array
    {
        if ($roleIds === []) return [];
        if (DB::table('role_menu_preferences')->whereIn('role_id', $roleIds)->count() < count($roleIds)) return null;
        return DB::table('role_menu_items')->whereIn('role_id', $roleIds)->distinct()->orderBy('menu_key')->pluck('menu_key')->all();
    }
}
