<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use RuntimeException;

final class CreateLocalUser extends Command
{
    protected $signature = 'chabok:local-user
        {--identifier=branch.manager.local : Globally unique local username}
        {--display-name=Local Branch Manager : Display name for the local account}
        {--allow-insecure-local-password : Explicitly allow a weak password in local/testing only}';

    protected $description = 'Create or update a local-only Branch Manager login and organization fixture';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->error('Run the database migrations before creating the local user.');

            return self::FAILURE;
        }

        $identifier = mb_strtolower(trim((string) $this->option('identifier')));
        $displayName = trim((string) $this->option('display-name'));
        $password = (string) (env('CHABOK_LOCAL_PASSWORD') ?: $this->secret(
            'Local password (minimum 12 characters with upper, lower, number, and symbol)',
        ));

        if ($identifier === '' || $displayName === '') {
            $this->error('Identifier and display name are required.');

            return self::FAILURE;
        }

        $allowInsecure = (bool) $this->option('allow-insecure-local-password');
        if (! $allowInsecure && ! $this->validPassword($password)) {
            $this->error('Password must contain at least 12 characters with upper, lower, number, and symbol.');

            return self::FAILURE;
        }

        $this->call('db:seed', [
            '--class' => AuthorizationCatalogSeeder::class,
            '--force' => true,
        ]);

        $fixture = DB::transaction(function () use ($identifier, $displayName, $password): array {
            $now = now();
            $hqId = $this->existingId('hq_tenants', 'hq_code', 'LOCAL-HQ', 'hq_id');
            $areaId = $this->existingId('areas', 'area_title', 'Local Operations Area', 'area_id');
            $nodeId = $this->existingId('nodes', 'node_code', 'LOCAL-BRANCH', 'node_id');
            $userId = $this->existingId('users', 'normalized_username', $identifier, 'user_id');

            DB::table('hq_tenants')->updateOrInsert(
                ['hq_code' => 'LOCAL-HQ'],
                [
                    'hq_id' => $hqId,
                    'hq_title' => 'Chabok Local HQ',
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('areas')->updateOrInsert(
                ['area_id' => $areaId],
                [
                    'hq_id' => $hqId,
                    'area_title' => 'Local Operations Area',
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('nodes')->updateOrInsert(
                ['hq_id' => $hqId, 'node_code' => 'LOCAL-BRANCH'],
                [
                    'node_id' => $nodeId,
                    'area_id' => $areaId,
                    'node_title' => 'Local Branch',
                    'node_type' => 'BRANCH',
                    'address_snapshot' => json_encode([
                        'country' => 'IR',
                        'city' => 'Tehran',
                        'label' => 'Local development branch',
                    ], JSON_THROW_ON_ERROR),
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            [$firstName, $lastName] = $this->splitName($displayName);
            DB::table('users')->updateOrInsert(
                ['normalized_username' => $identifier],
                [
                    'user_id' => $userId,
                    'hq_id' => $hqId,
                    'username' => $identifier,
                    'mobile' => null,
                    'normalized_mobile' => null,
                    'email' => null,
                    'normalized_email' => null,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'display_name' => $displayName,
                    'status' => 'ACTIVE',
                    'must_change_password' => false,
                    'created_by' => null,
                    'activated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('authentication_credentials')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'credential_id' => $this->existingId(
                        'authentication_credentials',
                        'user_id',
                        $userId,
                        'credential_id',
                    ),
                    'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
                    'algorithm' => 'argon2id',
                    'algorithm_version' => 1,
                    'password_changed_at' => $now,
                    'failed_attempt_count' => 0,
                    'locked_until' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            foreach (['branch_manager', 'manifest_approver'] as $roleCode) {
                $roleId = (string) DB::table('roles')
                    ->where('owner_key', 'GLOBAL')
                    ->where('role_code', $roleCode)
                    ->value('role_id');
                if ($roleId === '') {
                    throw new RuntimeException("The {$roleCode} role was not seeded.");
                }

                $slot = hash('sha256', "{$userId}|{$roleId}|NODE|{$nodeId}");
                DB::table('user_role_assignments')->updateOrInsert(
                    ['active_slot' => $slot],
                    [
                        'assignment_id' => $this->existingId(
                            'user_role_assignments',
                            'active_slot',
                            $slot,
                            'assignment_id',
                        ),
                        'hq_id' => $hqId,
                        'user_id' => $userId,
                        'role_id' => $roleId,
                        'scope_type' => 'NODE',
                        'scope_id' => $nodeId,
                        'includes_descendants' => false,
                        'status' => 'ACTIVE',
                        'assigned_by' => null,
                        'revoked_by' => null,
                        'revoked_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            foreach (['Foundation', 'IAM', 'Consignment', 'Parcel', 'Manifest'] as $moduleCode) {
                DB::table('tenant_module_entitlements')->updateOrInsert(
                    ['hq_id' => $hqId, 'module_code' => $moduleCode],
                    [
                        'entitlement_id' => $this->existingEntitlementId($hqId, $moduleCode),
                        'status' => 'ENABLED',
                        'activated_at' => $now,
                        'deactivated_at' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            DB::table('user_sessions')->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $now,
                    'revoked_reason' => 'LOCAL_CREDENTIAL_RESET',
                    'updated_at' => $now,
                ]);

            return compact('identifier', 'displayName', 'hqId', 'areaId', 'nodeId', 'userId');
        });

        $this->newLine();
        $this->info('Local Branch Manager is ready.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Identifier', $fixture['identifier']],
                ['Display name', $fixture['displayName']],
                ['Tenant', 'LOCAL-HQ'],
                ['Node', 'LOCAL-BRANCH'],
            ],
        );
        $this->warn('The password was accepted from hidden input/environment and was not printed or stored in source.');
        if ($allowInsecure) {
            $this->warn('The explicit insecure-local password exception was used. This account must never be promoted.');
        }

        return self::SUCCESS;
    }

    private function validPassword(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    /** @return array{string, string} */
    private function splitName(string $displayName): array
    {
        $parts = preg_split('/\s+/', $displayName, 2) ?: [];

        return [$parts[0] ?? 'Local', $parts[1] ?? 'Manager'];
    }

    private function existingId(string $table, string $key, string $value, string $id): string
    {
        return (string) (DB::table($table)->where($key, $value)->value($id) ?: Str::uuid());
    }

    private function existingEntitlementId(string $hqId, string $moduleCode): string
    {
        return (string) (DB::table('tenant_module_entitlements')->where([
            'hq_id' => $hqId,
            'module_code' => $moduleCode,
        ])->value('entitlement_id') ?: Str::uuid());
    }
}
