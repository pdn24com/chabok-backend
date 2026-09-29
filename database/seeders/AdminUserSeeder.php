<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Fixtures\LocalUserFixtureStore;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Seeder;
use Modules\Authorization\Application\Catalogs\AuthorizationCatalog;
use RuntimeException;

/**
 * A ready-made `admin` / `admin` login for local and staging databases: tenant admin (`hq_admin`) of a
 * tenant that has every module enabled. It is deliberately weak, so it never runs in production.
 * Re-running it resets the password and reactivates the account instead of creating a second user.
 */
final class AdminUserSeeder extends Seeder
{
    private const TENANT_CODE = 'CHABOK-HQ';

    private const USERNAME = 'admin';

    private const PASSWORD = 'admin';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly LocalUserFixtureStore $fixtures,
    ) {}

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('AdminUserSeeder skipped: the admin/admin account is never created in production.');

            return;
        }

        $this->connection->transaction(function (): void {
            $now = now();
            $hqId = $this->fixtures->putTenant(['hq_code' => self::TENANT_CODE],
                ['hq_title' => 'Chabok HQ', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
            $userId = $this->fixtures->putUser(['normalized_username' => self::USERNAME],
                ['hq_id' => $hqId, 'username' => self::USERNAME, 'mobile' => null, 'normalized_mobile' => null,
                    'email' => null, 'normalized_email' => null, 'first_name' => 'Admin', 'last_name' => 'Chabok',
                    'display_name' => 'Admin', 'status' => 'ACTIVE', 'must_change_password' => false, 'created_by' => null,
                    'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $this->fixtures->putCredential($userId,
                ['password_hash' => password_hash(self::PASSWORD, PASSWORD_ARGON2ID), 'algorithm' => 'argon2id', 'algorithm_version' => 1,
                    'password_changed_at' => $now, 'failed_attempt_count' => 0, 'locked_until' => null, 'created_at' => $now, 'updated_at' => $now]);

            $roleId = $this->fixtures->globalRoleId('hq_admin')
                ?? throw new RuntimeException('The hq_admin role is not seeded; run AuthorizationCatalogSeeder first.');
            $slot = hash('sha256', "{$userId}|{$roleId}|TENANT|");
            $this->fixtures->putAssignment($slot,
                ['hq_id' => $hqId, 'user_id' => $userId, 'role_id' => $roleId, 'scope_type' => 'TENANT', 'scope_id' => null,
                    'includes_descendants' => false, 'status' => 'ACTIVE', 'assigned_by' => null, 'revoked_by' => null,
                    'revoked_at' => null, 'created_at' => $now, 'updated_at' => $now]);

            // Every module the permission catalog names, so the whole panel (CRM included) is reachable.
            foreach (array_unique(array_values(AuthorizationCatalog::permissions())) as $moduleCode) {
                $this->fixtures->putEntitlement($hqId, $moduleCode,
                    ['status' => 'ENABLED', 'activated_at' => $now, 'deactivated_at' => null, 'updated_by' => null,
                        'created_at' => $now, 'updated_at' => $now]);
            }
            $this->fixtures->revokeSessions($userId, 'ADMIN_SEED_RESET', $now);
        });

        $this->command?->info('Admin user ready: username "admin", password "admin".');
    }
}
