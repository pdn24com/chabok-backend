<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CurrentCatalogInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingCommitmentResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingContextInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingDependenciesInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingEligibilityInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingLegacyCommitmentInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingOptionsInterface;
use Modules\ServiceCatalog\Application\Dto\ResolvedServiceOfferingDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\EligibilityDecisionDocument;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Domain\Enums\EligibilityOutcome;

final readonly class ValidateServiceSelectionHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private OfferingContextInterface $offeringContext,
        private AccessContextResolverInterface $accessContextResolver,
        private CurrentCatalogInterface $currentCatalog,
        private ClockInterface $clock,
        private OfferingDependenciesInterface $offeringDependencies,
        private OfferingEligibilityInterface $offeringEligibility,
        private OfferingCommitmentResolverInterface $offeringCommitmentResolver,
        private OfferingOptionsInterface $offeringOptions,
        private OfferingLegacyCommitmentInterface $offeringLegacyCommitment,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(ValidateServiceSelectionCommand $command): ResolvedServiceOfferingDto
    {
        $actor = $command->actor;
        $offeringId = $command->offeringId;
        $versionId = $command->versionId;
        $context = $command->context;
        $requireCommitmentSelection = $command->requireCommitmentSelection;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.resolve', runtime: true);
        $context = $this->offeringContext->canonicalizeCoverageContext($context);
        $context->scheduleNodeIds = $this->accessContextResolver->resolve($actor)->accessibleNodeIds ?? [];
        $context->factKeys = array_values(array_unique([...$context->factKeys, 'schedule_node_ids']));
        if ($versionId) {
            if (! in_array($versionId, $this->currentCatalog->relatedVersions(CatalogResource::Offering, $offeringId), true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.selected_service_reference_is_invalid');
            }
        }
        $asOf = CarbonImmutable::parse((string) ($context->asOfTimestamp ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc();
        $row = $this->catalogRepository->newestPublishedOffering((string) $actor->hqId, $offeringId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.no_effective_published_service_offering_version_exists', details: ['reason_code' => 'SERVICE_VERSION_NOT_EFFECTIVE']);
        }
        $dependencies = $this->offeringDependencies->currentFor(new Collection([$row]), (string) $actor->hqId)[$row->service_offering_version_id];
        $this->offeringDependencies->assertAvailable($dependencies);
        if (! $this->offeringDependencies->available($row, (string) $actor->hqId, (string) ($context->channel ?? 'BRANCH'))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.service_is_unavailable_channel', details: ['reason_code' => 'SERVICE_UNAVAILABLE']);
        }
        $decision = $this->offeringEligibility->evaluate($row, $context);
        if ($decision->outcome !== EligibilityOutcome::Eligible) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.selected_service_offering_is_not_eligible', details: EligibilityDecisionDocument::make($decision));
        }
        $commitment = $this->offeringCommitmentResolver->resolve($row, $context, $requireCommitmentSelection);
        if ($commitment !== null && $commitment->eligible !== true) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.selected_pickup_commitment_is_not_eligible', details: ['reason_code' => $commitment->reasonCode]);
        }

        return new ResolvedServiceOfferingDto($row, $dependencies, $decision, $this->offeringOptions->resolvedOptions($row, $context),
            $commitment ?? $this->offeringLegacyCommitment->commitment($row, $context));
    }
}
