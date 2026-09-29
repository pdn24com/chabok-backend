<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;
use Modules\Iam\Application\Dto\DriverProfileSummaryDto;
use Modules\Iam\Application\Dto\OperationalProfileInputDto;
use Modules\Iam\Application\Ports\OperationalProfileWriterInterface;
use Modules\Iam\Domain\Enums\OperationalProfileKind;
use Modules\Iam\Domain\Enums\OperationalProfileMode;
use Modules\Operations\Application\Dto\DriverChangesDto;
use Modules\Operations\Application\Dto\DriverCreationDto;
use Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverCommand;
use Modules\Operations\Application\UseCases\CreateFleetDriver\CreateFleetDriverHandler;
use Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverCommand;
use Modules\Operations\Application\UseCases\UpdateFleetDriver\UpdateFleetDriverHandler;
use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Domain\Enums\FleetStatus;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Organization\Application\Dto\NodeAddressDto;
use Modules\Organization\Application\Dto\NodeDetailsDto;
use Modules\Organization\Application\Dto\NodeDraftDto;
use Modules\Organization\Application\UseCases\CreateNode\CreateNodeCommand;
use Modules\Organization\Application\UseCases\CreateNode\CreateNodeHandler;
use Modules\Organization\Application\UseCases\GetNode\GetNodeCommand;
use Modules\Organization\Application\UseCases\GetNode\GetNodeHandler;
use Modules\Organization\Domain\Enums\NodeCapability;
use Modules\Organization\Domain\Enums\NodeType;

/** Composition boundary: owning services retain their authorization and domain rules. */
final readonly class OperationalProfileAdapter implements OperationalProfileWriterInterface
{
    public function __construct(
        private CreateFleetDriverHandler $createFleetDriverHandler,
        private UpdateFleetDriverHandler $updateFleetDriverHandler,
        private CreateNodeHandler $createNodeHandler,
        private GetNodeHandler $getNodeHandler,
    ) {}

    public function attach(
        AuthenticatedPrincipal $actor,
        string $userId,
        OperationalProfileInputDto $input,
        string $correlationId,
    ): ?RoleAssignment {
        if ($input->kind === OperationalProfileKind::Driver) {
            if ($input->mode === OperationalProfileMode::Create) {
                $this->createFleetDriverHandler->handle(new CreateFleetDriverCommand($actor, new DriverCreationDto($input->driver->code, $input->driver->displayName, $input->driver->homeNodeId, array_map(DriverCapability::from(...), $input->driver->capabilities), $userId, $input->driver->mobile), $correlationId));
            } else {
                $driver = DriverRecord::query()
                    ->where(['hq_id' => $actor->hqId, 'driver_id' => $input->existingId])
                    ->lockForUpdate()
                    ->first();
                if ($driver === null) {
                    throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
                }
                if ($driver->user_id !== null) {
                    throw new ApiException(ApiErrorCode::Conflict, 409, 'app.driver_is_already_linked_user');
                }
                if ($driver->status !== FleetStatus::Active) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'app.driver_must_be_active');
                }
                $this->updateFleetDriverHandler->handle(new UpdateFleetDriverCommand($actor, $input->existingId, new DriverChangesDto($input->expectedVersion, null, null, null, $userId, true, null, false, null, null), $correlationId));
            }

            return null;
        }
        if ($input->mode === OperationalProfileMode::Create) {
            $draft = $input->node;
            $address = $draft->address;
            $details = new NodeDetailsDto($draft->areaId, $draft->title, NodeType::from($draft->type),
                array_map(NodeCapability::from(...), $draft->capabilities), new NodeAddressDto($address->countryCode,
                    $address->provinceId, $address->cityId, $address->postalCode, $address->line, $address->latitude, $address->longitude));
            $node = $this->createNodeHandler->handle(new CreateNodeCommand($actor, new NodeDraftDto($draft->code, $details), $correlationId));
        } else {
            $node = $this->getNodeHandler->handle(new GetNodeCommand($actor, $input->existingId));
        }
        if ($node->status !== 'ACTIVE') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'app.node_must_be_active');
        }

        return new RoleAssignment($input->roleId, new PermissionScope(ScopeType::NODE, $node->node_id));
    }

    public function forUser(AuthenticatedPrincipal $actor, string $userId): ?DriverProfileSummaryDto
    {
        $row = DriverRecord::query()->where(['hq_id' => $actor->hqId, 'user_id' => $userId])->first(['driver_id', 'driver_code', 'display_name', 'home_node_id', 'status']);

        return $row === null ? null : new DriverProfileSummaryDto($row->driver_id, $row->driver_code, $row->display_name, $row->home_node_id, $row->status->value);
    }
}
