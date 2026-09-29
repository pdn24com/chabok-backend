<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\ServiceCatalog\Application\Contracts\CatalogSelectionReaderInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogSelectionAvailabilityDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

/** Loads current identities once for a batch of tariff rules. */
final class CatalogSelectionReader implements CatalogSelectionReaderInterface
{
    public function __construct(
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function inspect(string $hqId, array $selections): array
    {
        $offeringReferences = [];
        $optionReferences = [];
        foreach ($selections as $selection) {
            if ($selection->offeringReference !== null) {
                $offeringReferences[] = $selection->offeringReference;
            }
            if ($selection->optionReference !== null) {
                $optionReferences[] = $selection->optionReference;
            }
        }
        $offeringIdentities = collect($this->catalogRepository->identityIdsOfVersions(CatalogResource::Offering, $offeringReferences));
        $optionIdentities = collect($this->catalogRepository->identityIdsOfVersions(CatalogResource::Option, $optionReferences));
        $offerings = $this->catalogRepository->activeOfferingsWithPublishedVersion([...$offeringReferences, ...$offeringIdentities->all()]);
        $options = $this->catalogRepository->activeOptionsWithPublishedVersion([...$optionReferences, ...$optionIdentities->all()], $hqId);
        $results = [];
        foreach ($selections as $selection) {
            $offeringId = $offeringIdentities->get($selection->offeringReference, $selection->offeringReference);
            $offering = $offerings->get($offeringId);
            $version = $offering?->publishedVersions->first();
            // Freight rules point to revisions; the old publication check deliberately
            // did not apply tenant visibility. Option binding does enforce visibility.
            $published = $offeringIdentities->has($selection->offeringReference) && $version !== null;
            $available = $version !== null && ($offering->hq_id === null || $offering->hq_id === $hqId);
            if ($selection->optionReference === null) {
                $results[] = new CatalogSelectionAvailabilityDto($published, true, $available);

                continue;
            }
            $optionId = $optionIdentities->get($selection->optionReference, $selection->optionReference);
            $option = $options->get($optionId);
            $bound = false;
            if ($version !== null && ($offering->hq_id === null || $offering->hq_id === $hqId) && $option?->publishedVersions->isNotEmpty()) {
                foreach ($version->optionRules as $rule) {
                    if ($rule->optionVersion?->service_option_id === $optionId) {
                        $bound = true;
                        break;
                    }
                }
            }
            $results[] = new CatalogSelectionAvailabilityDto($published, $bound, $available);
        }

        return $results;
    }
}
