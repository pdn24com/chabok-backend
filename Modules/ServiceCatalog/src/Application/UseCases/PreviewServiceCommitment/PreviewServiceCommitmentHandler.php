<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PreviewServiceCommitmentHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionHandler $validateServiceSelection,
    )
    {
    }

    public function handle(PreviewServiceCommitmentCommand $command): PreviewServiceCommitmentResult
    {
        return new PreviewServiceCommitmentResult($this->execute($command->actor, $command->offeringId, $command->context));
    }

    private function execute(AuthenticatedPrincipal $actor, string $offeringId, array $context): array
    {
        $selection = $this->validateServiceSelection->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionCommand($actor, $offeringId, $context['service_offering_version_id'] ?? null, $context, false))->data;
        return (array) $selection['commitment'];
    }
}
