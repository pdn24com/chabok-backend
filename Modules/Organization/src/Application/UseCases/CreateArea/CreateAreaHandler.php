<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateArea;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateAreaHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Organization\Application\Services\HierarchyEditor $hierarchyEditor,
        private \Modules\Organization\Application\UseCases\GetArea\GetAreaHandler $getArea,
        private \Modules\Organization\Application\Services\NetworkChangeRecorder $networkChangeRecorder,
    )
    {
    }

    public function handle(CreateAreaCommand $command): CreateAreaResult
    {
        return new CreateAreaResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.manage');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.area.manage', $input['parent_area_id'] ?? null, true);
        return $this->transactions->run(function () use ($actor, $hqId, $input, $correlationId): array {
            if ($this->network->areaCodeExists($hqId, $input['area_code'])) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'Area code already exists.');
            }
            $areaId = $this->identifiers->uuid();
            $this->network->insertArea([
                'area_id' => $areaId,
                'hq_id' => $hqId,
                'area_code' => $input['area_code'],
                'area_title' => $input['area_title'],
                'status' => 'ACTIVE',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->hierarchyEditor->replaceParent($hqId, $areaId, $input['parent_area_id'] ?? null);
            $after = $this->getArea->handle(new \Modules\Organization\Application\UseCases\GetArea\GetAreaCommand($actor, $areaId))->data;
            $this->networkChangeRecorder->record($actor, 'network.area.created', 'AREA', $areaId, $correlationId, null, $after);
            return $after;
        });
    }
}
