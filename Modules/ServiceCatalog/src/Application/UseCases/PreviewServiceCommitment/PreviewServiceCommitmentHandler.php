<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment;

use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionHandler;

final readonly class PreviewServiceCommitmentHandler
{
    public function __construct(private ValidateServiceSelectionHandler $validateServiceSelectionHandler) {}

    public function handle(PreviewServiceCommitmentCommand $command): OfferingCommitmentDto
    {
        $actor = $command->actor;
        $offeringId = $command->offeringId;
        $context = $command->context;
        $selection = $this->validateServiceSelectionHandler->handle(new ValidateServiceSelectionCommand($actor, $offeringId, $context->serviceOfferingVersionId ?? null, $context, false));

        return $selection->commitment;
    }
}
