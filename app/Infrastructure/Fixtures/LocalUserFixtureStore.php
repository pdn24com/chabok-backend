<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures;

use DateTimeInterface;
use Illuminate\Support\Facades\Schema;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\TenantEntitlementRecord;
use Modules\Iam\Infrastructure\Persistence\Models\CredentialRecord;
use Modules\Iam\Infrastructure\Persistence\Models\SessionRecord;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\HqTenantRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

/**
 * Persistence for the local-only login fixture. It provisions the same graph a seeder would, so the
 * console command that drives it stays free of persistence detail.
 */
final class LocalUserFixtureStore
{
    public function schemaReady(): bool
    {
        return Schema::hasTable('users') && Schema::hasTable('roles');
    }

    public function globalRoleId(string $roleCode): ?string
    {
        $id = RoleRecord::query()->where('owner_key', 'GLOBAL')->where('role_code', $roleCode)->value('role_id');

        return $id === null || $id === '' ? null : (string) $id;
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $attributes */
    public function putTenant(array $identity, array $attributes): string
    {
        return $this->put(HqTenantRecord::query()->getModel(), $identity, $attributes);
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $attributes */
    public function putArea(array $identity, array $attributes): string
    {
        return $this->put(AreaRecord::query()->getModel(), $identity, $attributes);
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $attributes */
    public function putNode(array $identity, array $attributes): string
    {
        return $this->put(NodeRecord::query()->getModel(), $identity, $attributes);
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $attributes */
    public function putUser(array $identity, array $attributes): string
    {
        return $this->put(UserRecord::query()->getModel(), $identity, $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function putCredential(string $userId, array $attributes): void
    {
        $this->put(CredentialRecord::query()->getModel(), ['user_id' => $userId],
            $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function putAssignment(string $activeSlot, array $attributes): void
    {
        $this->put(AssignmentRecord::query()->getModel(), ['active_slot' => $activeSlot],
            $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function putEntitlement(string $hqId, string $moduleCode, array $attributes): void
    {
        $identity = ['hq_id' => $hqId, 'module_code' => $moduleCode];
        $this->put(TenantEntitlementRecord::query()->getModel(), $identity,
            $attributes);
    }

    public function revokeSessions(string $userId, string $reason, DateTimeInterface $at): void
    {
        SessionRecord::query()->where('user_id', $userId)->whereNull('revoked_at')->update([
            'revoked_at' => $at,
            'revoked_reason' => $reason,
            'updated_at' => $at,
        ]);
    }

    /** Writes a fixture row, reusing the existing one when the natural key already matches. @param array<string, mixed> $identity @param array<string, mixed> $attributes */
    private function put(object $model, array $identity, array $attributes): string
    {
        $existing = $model->newQuery()->where($identity)->first() ?? $model->newInstance();
        $existing->forceFill($identity + $attributes)->save();

        return (string) $existing->getKey();
    }
}
