<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Operations\Application\FleetAdministrationService;
use Modules\Organization\Application\NetworkAdministrationService;
use Modules\User\Application\Contracts\OperationalProfileWriter;

/** Composition boundary: owning services retain their authorization and domain rules. */
final readonly class OperationalProfileAdapter implements OperationalProfileWriter
{
    public function __construct(private FleetAdministrationService $fleet, private NetworkAdministrationService $network) {}

    public function attach(AuthenticatedPrincipal $actor, string $userId, array $input, string $correlationId): ?array
    {
        if ($input['kind'] === 'DRIVER') {
            if ($input['mode'] === 'CREATE') {
                $this->fleet->createDriver($actor, $input['driver'] + ['user_id' => $userId], $correlationId);
            } else {
                $driver = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $input['existing_id']])->lockForUpdate()->first();
                if ($driver === null) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
                if ($driver->user_id !== null) throw new ApiException(ApiErrorCode::Conflict, 409, 'Driver is already linked to a user.');
                if ($driver->status !== 'ACTIVE') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Driver must be active.');
                $this->fleet->updateDriver($actor, $input['existing_id'], ['user_id' => $userId, 'expected_version' => $input['expected_version']], $correlationId);
            }
            return null;
        }
        $node = $input['mode'] === 'CREATE'
            ? $this->network->createNode($actor, $input['node'], $correlationId)
            : $this->network->node($actor, $input['existing_id']);
        if ($node['status'] !== 'ACTIVE') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Node must be active.');
        return ['role_id' => $input['role_id'], 'scope_type' => 'NODE', 'scope_id' => $node['node_id'], 'includes_descendants' => false];
    }

    public function forUser(AuthenticatedPrincipal $actor, string $userId): ?array
    {
        $row = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'user_id' => $userId])->first(['driver_id', 'driver_code', 'display_name', 'home_node_id', 'status']);
        return $row === null ? null : (array) $row;
    }
}
