<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ValidateServiceSelectionHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\OfferingContext $offeringContext,
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\ServiceCatalog\Application\Services\OfferingDependencies $offeringDependencies,
        private \Modules\ServiceCatalog\Application\Services\OfferingEligibility $offeringEligibility,
        private \Modules\ServiceCatalog\Application\CommitmentScheduleService $commitments,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
        private \Modules\ServiceCatalog\Application\Services\OfferingOptions $offeringOptions,
        private \Modules\ServiceCatalog\Application\Services\OfferingLegacyCommitment $offeringLegacyCommitment,
    )
    {
    }

    public function handle(ValidateServiceSelectionCommand $command): ValidateServiceSelectionResult
    {
        return new ValidateServiceSelectionResult($this->execute($command->actor, $command->offeringId, $command->versionId, $command->context, $command->requireCommitmentSelection));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $offeringId,
        ?string $versionId,
        array $context,
        bool $requireCommitmentSelection = true,
    ): array
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.resolve', runtime: true);
        $context = $this->offeringContext->canonicalizeCoverageContext($context);
        $context['schedule_node_ids'] = $this->authorization->resolve($actor)['accessible_node_ids'] ?? [];
        if ($versionId) {
            if (!in_array($versionId, $this->currentCatalog->relatedVersions('offerings', $offeringId), true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected service reference is invalid.');
            }
        }
        $asOf = CarbonImmutable::parse((string) ($context['as_of_timestamp'] ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc();
        $row = $this->catalog->latestPublishedOffering($actor->hqId, $offeringId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'No effective published Service Offering version exists.', details: ['reason_code' => 'SERVICE_VERSION_NOT_EFFECTIVE']);
        }
        $row = (object) $this->offeringDependencies->runtimeDependencies((array) $row, (string) $actor->hqId);
        if (!$this->offeringDependencies->available((string) $row->service_offering_version_id, (string) $actor->hqId, (string) ($context['channel'] ?? 'BRANCH'))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The service is unavailable for this channel.', details: ['reason_code' => 'SERVICE_UNAVAILABLE']);
        }
        $decision = $this->offeringEligibility->evaluate((array) $row, $context);
        if ($decision['outcome'] !== 'ELIGIBLE') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected Service Offering is not eligible.', details: $decision);
        }
        $commitment = $this->commitments->resolveForOffering((string) $row->service_offering_version_id, $context, $requireCommitmentSelection);
        if ($commitment !== null && $commitment['eligible'] !== true) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected Pickup commitment is not eligible.', details: ['reason_code' => $commitment['reason_code']]);
        }
        return [
            ...$this->catalogReader->decode((array) $row),
            ...$decision,
            'options' => $this->offeringOptions->resolvedOptions((string) $row->service_offering_version_id, $context),
            'commitment' => $commitment ?? $this->offeringLegacyCommitment->commitment((array) $row, $context),
        ];
    }
}
