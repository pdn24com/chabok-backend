<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Fixtures\LocalUserFixtureStore;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use RuntimeException;

final class CreateLocalUser extends Command
{
    /** Natural keys of the single local tenant, area and node this fixture owns. */
    private const TENANT_CODE = 'LOCAL-HQ';

    private const AREA_TITLE = 'Local Operations Area';

    private const NODE_CODE = 'LOCAL-BRANCH';

    /** Modules a local login needs enabled to exercise the panel end to end. */
    private const ENABLED_MODULES = ['Foundation', 'IAM', 'Consignment', 'Parcel', 'Manifest', 'ServiceCatalog', 'Pricing'];

    protected $signature = 'chabok:local-user
        {--identifier=branch.manager.local : Globally unique local username}
        {--display-name=Local Branch Manager : Display name for the local account}
        {--allow-insecure-local-password : Explicitly allow a weak password in local/testing only}';

    protected $description = 'Create or update a local-only Branch Manager login and organization fixture';

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly LocalUserFixtureStore $fixtures,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is restricted to local and testing environments.');

            return self::FAILURE;
        }
        if (! $this->fixtures->schemaReady()) {
            $this->error('Run the database migrations before creating the local user.');

            return self::FAILURE;
        }
        $identifier = mb_strtolower(trim((string) $this->option('identifier')));
        $displayName = trim((string) $this->option('display-name'));
        $password = (string) (config('chabok.local_tooling.password') ?: $this->secret('Local password (minimum 12 characters with upper, lower, number, and symbol)'));
        if ($identifier === '' || $displayName === '') {
            $this->error('Identifier and display name are required.');

            return self::FAILURE;
        }
        $allowInsecure = (bool) $this->option('allow-insecure-local-password');
        if (! $allowInsecure && ! $this->validPassword($password)) {
            $this->error('Password must contain at least 12 characters with upper, lower, number, and symbol.');

            return self::FAILURE;
        }
        $this->call('db:seed', ['--class' => AuthorizationCatalogSeeder::class, '--force' => true]);
        $fixture = $this->connection->transaction(function () use ($identifier, $displayName, $password): array {
            $now = now();
            $hqId = $this->fixtures->putTenant(['hq_code' => self::TENANT_CODE],
                ['hq_title' => 'Chabok Local HQ', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
            $areaId = $this->fixtures->putArea(['hq_id' => $hqId, 'area_title' => self::AREA_TITLE],
                ['hq_id' => $hqId, 'area_title' => self::AREA_TITLE, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
            $nodeId = $this->fixtures->putNode(['hq_id' => $hqId, 'node_code' => self::NODE_CODE],
                ['area_id' => $areaId, 'node_title' => 'Local Branch', 'node_type' => 'BRANCH',
                    'address_snapshot' => ['country' => 'IR', 'city' => 'Tehran', 'label' => 'Local development branch'],
                    'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
            [$firstName, $lastName] = $this->splitName($displayName);
            $userId = $this->fixtures->putUser(['normalized_username' => $identifier],
                ['hq_id' => $hqId, 'username' => $identifier, 'mobile' => null, 'normalized_mobile' => null,
                    'email' => null, 'normalized_email' => null, 'first_name' => $firstName, 'last_name' => $lastName,
                    'display_name' => $displayName, 'status' => 'ACTIVE', 'must_change_password' => false, 'created_by' => null,
                    'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $this->fixtures->putCredential($userId,
                ['password_hash' => password_hash($password, PASSWORD_ARGON2ID), 'algorithm' => 'argon2id', 'algorithm_version' => 1,
                    'password_changed_at' => $now, 'failed_attempt_count' => 0, 'locked_until' => null, 'created_at' => $now, 'updated_at' => $now]);
            $roleAssignments = [
                [
                    'role_code' => 'hq_admin',
                    'scope_type' => 'TENANT',
                    'scope_id' => null,
                ],
                [
                    'role_code' => 'branch_manager',
                    'scope_type' => 'NODE',
                    'scope_id' => $nodeId,
                ],
                [
                    'role_code' => 'manifest_approver',
                    'scope_type' => 'NODE',
                    'scope_id' => $nodeId,
                ],
            ];
            foreach ($roleAssignments as $roleAssignment) {
                $roleCode = $roleAssignment['role_code'];
                $roleId = $this->fixtures->globalRoleId($roleCode);
                if ($roleId === null) {
                    throw new RuntimeException("The {$roleCode} role was not seeded.");
                }
                $scopeType = $roleAssignment['scope_type'];
                $scopeId = $roleAssignment['scope_id'];
                $slot = hash('sha256', "{$userId}|{$roleId}|{$scopeType}|".($scopeId ?? ''));
                $this->fixtures->putAssignment($slot,
                    ['hq_id' => $hqId, 'user_id' => $userId, 'role_id' => $roleId, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
                        'includes_descendants' => false, 'status' => 'ACTIVE', 'assigned_by' => null, 'revoked_by' => null,
                        'revoked_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach (self::ENABLED_MODULES as $moduleCode) {
                $this->fixtures->putEntitlement($hqId, $moduleCode,
                    ['status' => 'ENABLED', 'activated_at' => $now, 'deactivated_at' => null, 'updated_by' => null,
                        'created_at' => $now, 'updated_at' => $now]);
            }
            $this->fixtures->revokeSessions($userId, 'LOCAL_CREDENTIAL_RESET', $now);

            return compact('identifier', 'displayName', 'hqId', 'areaId', 'nodeId', 'userId');
        });
        $this->newLine();
        $this->info('Local Branch Manager is ready.');
        $this->table(['Field', 'Value'], [
            ['Identifier', $fixture['identifier']],
            ['Display name', $fixture['displayName']],
            ['Tenant', 'LOCAL-HQ'],
            ['Node', 'LOCAL-BRANCH'],
        ]);
        $this->warn('The password was accepted from hidden input/environment and was not printed or stored in source.');
        if ($allowInsecure) {
            $this->warn('The explicit insecure-local password exception was used. This account must never be promoted.');
        }

        return self::SUCCESS;
    }

    private function validPassword(string $password): bool
    {
        return strlen($password) >= 12 && preg_match('/[a-z]/', $password) === 1 && preg_match('/[A-Z]/', $password) === 1 && preg_match('/[0-9]/', $password) === 1 && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }

    /** @return array{string, string} */
    private function splitName(string $displayName): array
    {
        $parts = preg_split('/\s+/', $displayName, 2) ?: [];

        return [$parts[0] ?? 'Local', $parts[1] ?? 'Manager'];
    }
}
