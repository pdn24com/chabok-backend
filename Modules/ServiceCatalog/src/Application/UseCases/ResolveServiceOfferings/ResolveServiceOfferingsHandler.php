<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiException;

final readonly class ResolveServiceOfferingsHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\OfferingContext $offeringContext,
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
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

    public function handle(ResolveServiceOfferingsCommand $command): ResolveServiceOfferingsResult
    {
        return new ResolveServiceOfferingsResult($this->execute($command->actor, $command->context));
    }

    private function execute(AuthenticatedPrincipal $actor, array $context): array
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.resolve', runtime: true);
        $context = $this->offeringContext->canonicalizeCoverageContext($context);
        $context['schedule_node_ids'] = $this->authorization->resolve($actor)['accessible_node_ids'] ?? [];
        $asOf = CarbonImmutable::parse((string) ($context['as_of_timestamp'] ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc();
        $channel = (string) ($context['channel'] ?? 'BRANCH');
        $rows = $this->catalog->publishedOfferings($actor->hqId);
        $results = [];
        foreach ($rows as $row) {
            if (!$this->offeringDependencies->available((string) $row->service_offering_version_id, (string) $actor->hqId, $channel)) {
                continue;
            }
            try {
                $row = (object) $this->offeringDependencies->runtimeDependencies((array) $row, (string) $actor->hqId);
            } catch (ApiException $error) {
                if (($error->details['reason_code'] ?? '') === 'CATALOG_DEPENDENCY_UNAVAILABLE') {
                    continue;
                }
                throw $error;
            }
            $decision = $this->offeringEligibility->evaluate((array) $row, $context);
            if ($decision['outcome'] !== 'INELIGIBLE') {
                try {
                    $commitment = $this->commitments->resolveForOffering((string) $row->service_offering_version_id, $context, false);
                } catch (ApiException $exception) {
                    $reasonCode = $exception->details['reason_code'] ?? null;
                    if (in_array($reasonCode, [
                        'PICKUP_WINDOW_INVALID',
                        'DELIVERY_WINDOW_INVALID',
                        'CATALOG_DEPENDENCY_UNAVAILABLE',
                        'COMMITMENT_SCOPE_UNAVAILABLE',
                        'SLA_CALENDAR_UNAVAILABLE',
                    ], true)) {
                        continue;
                    }
                    throw $exception;
                }
                if ($commitment !== null && $commitment['eligible'] !== true) {
                    continue;
                }
                $results[] = [
                    ...$this->catalogReader->decode((array) $row),
                    ...$decision,
                    'options' => $this->offeringOptions->resolvedOptions((string) $row->service_offering_version_id, $context),
                    'commitment' => $commitment ?? $this->offeringLegacyCommitment->commitment((array) $row, $context),
                ];
            }
        }
        return $results;
    }
}
