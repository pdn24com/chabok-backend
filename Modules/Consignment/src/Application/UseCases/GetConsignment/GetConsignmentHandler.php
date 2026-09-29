<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignment;

use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Contracts\ConsignmentSettingsInterface;
use Modules\Consignment\Application\Contracts\EditPricingImpactInterface;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;

final readonly class GetConsignmentHandler
{
    public function __construct(
        private ConsignmentAccessGuardInterface $consignmentAccessGuard,
        private ConsignmentRepositoryInterface $consignmentRepository,
        private ScopedAccessInterface $scopedAccess,
        private ConsignmentSettingsInterface $consignmentSettings,
        private EditPricingImpactInterface $editPricingImpact,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function handle(GetConsignmentCommand $command): GetConsignmentResult
    {
        $context = $this->consignmentAccessGuard->assertAccess($command->actor, $command->nodeId, 'consignment.view');
        $hqId = (string) $command->actor->hqId;
        $id = $command->consignmentId;
        $showParcels = $context->hasPermission('parcel.view') && in_array($context->actingNodeId, $this->scopedAccess->nodes($context, 'parcel.view'), true);
        $showAudit = $context->hasPermission('audit.view') && in_array($context->actingNodeId, $this->scopedAccess->nodes($context, 'audit.view'), true);
        $consignment = $this->consignmentRepository->findVisibleDetail($hqId, $context->accessibleNodeIds, $id, $showAudit);
        if ($consignment === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $manifests = $this->manifestRepository->outcomesForConsignment($hqId, $id);

        return new GetConsignmentResult(
            consignment: $consignment,
            showParcels: $showParcels,
            showAudit: $showAudit,
            editable: $context->hasPermission('consignment.edit') && in_array($context->actingNodeId, $this->scopedAccess->nodes($context, 'consignment.edit'), true) && in_array($consignment->current_status, $this->consignmentSettings->editableStatuses(), true),
            nonPricingContactFields: $this->editPricingImpact->contactFields($consignment),
            manifests: $manifests,
        );
    }
}
