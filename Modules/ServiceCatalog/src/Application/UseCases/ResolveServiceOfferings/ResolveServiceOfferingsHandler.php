<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingCommitmentResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingContextInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingDependenciesInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingEligibilityInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingLegacyCommitmentInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingOptionsInterface;
use Modules\ServiceCatalog\Application\Dto\ResolvedServiceOfferingDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\EligibilityOutcome;

final readonly class ResolveServiceOfferingsHandler
{
    /** One resolution never considers more than this many published Offerings. */
    private const MAX_OFFERINGS = 100;

    public function __construct(
        private CommitmentZoneResolverInterface $commitmentZoneResolver,
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private OfferingContextInterface $offeringContext,
        private AccessContextResolverInterface $accessContextResolver,
        private ClockInterface $clock,
        private OfferingDependenciesInterface $offeringDependencies,
        private OfferingEligibilityInterface $offeringEligibility,
        private OfferingCommitmentResolverInterface $offeringCommitmentResolver,
        private OfferingOptionsInterface $offeringOptions,
        private OfferingLegacyCommitmentInterface $offeringLegacyCommitment,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    /** @return list<ResolvedServiceOfferingDto> */
    public function handle(ResolveServiceOfferingsCommand $command): array
    {
        $actor = $command->actor;
        $context = $command->context;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.resolve', runtime: true);
        $context = $this->offeringContext->canonicalizeCoverageContext($context);
        $context->scheduleNodeIds = $this->accessContextResolver->resolve($actor)->accessibleNodeIds ?? [];
        $context->factKeys = array_values(array_unique([...$context->factKeys, 'schedule_node_ids']));
        $asOf = CarbonImmutable::parse((string) ($context->asOfTimestamp ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc();
        $channel = (string) ($context->channel ?? 'BRANCH');
        $rows = $this->catalogRepository->publishedOfferings((string) $actor->hqId, self::MAX_OFFERINGS);
        $dependencies = $this->offeringDependencies->currentFor($rows, (string) $actor->hqId);
        $zoneGroupsByOwner = [];
        foreach ($rows as $row) {
            $policy = $row->commitmentBinding?->scheduleVersion?->schedule?->publishedVersion?->commitment_policy;
            if (! empty($policy['zone_set_id'])) {
                $zoneGroupsByOwner[$row->hq_id ?? ''][] = $policy['zone_set_id'];
            }
        }
        $destinationsByOwner = [];
        $results = [];
        $selectedOptionsByOwner = [];
        foreach ($rows as $row) {
            if (! $this->offeringDependencies->available($row, (string) $actor->hqId, $channel)) {
                continue;
            }
            $current = $dependencies[$row->service_offering_version_id];
            if (! $current->available()) {
                continue;
            }
            $owner = $row->hq_id ?? '';
            $selectedOptionsByOwner[$owner] ??= $this->offeringEligibility->selectedOptions($context, $row->hq_id);
            $decision = $this->offeringEligibility->evaluate($row, $context, $selectedOptionsByOwner[$owner]);
            if ($decision->outcome !== EligibilityOutcome::Ineligible) {
                $destinationsByOwner[$owner] ??= $this->commitmentZoneResolver->destinations($owner,
                    $zoneGroupsByOwner[$owner] ?? [], $context->commitmentDestination());
                $resolution = $this->offeringCommitmentResolver->inspect($row, $context, false, $destinationsByOwner[$owner]);
                if (! $resolution->available()) {
                    continue;
                }
                $commitment = $resolution->commitment;
                if ($commitment !== null && $commitment->eligible !== true) {
                    continue;
                }
                $results[] = new ResolvedServiceOfferingDto($row, $current, $decision, $this->offeringOptions->resolvedOptions($row, $context),
                    $commitment ?? $this->offeringLegacyCommitment->commitment($row, $context));
            }
        }

        return $results;
    }
}
