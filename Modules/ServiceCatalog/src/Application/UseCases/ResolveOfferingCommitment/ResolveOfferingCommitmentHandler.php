<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment;

use Modules\ServiceCatalog\Application\Contracts\OfferingCommitmentResolverInterface;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;

final readonly class ResolveOfferingCommitmentHandler
{
    public function __construct(
        private OfferingCommitmentResolverInterface $offeringCommitmentResolver,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(ResolveOfferingCommitmentCommand $command): ?OfferingCommitmentDto
    {
        $offering = $this->catalogRepository->findOfferingVersionWithCommitment($command->offeringVersionId);

        return $offering === null ? null : $this->offeringCommitmentResolver->resolve($offering, $command->context, $command->requireSelection);
    }
}
