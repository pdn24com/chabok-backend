<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Adapters;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolverInterface;
use Modules\ServiceCatalog\Application\Dto\ServiceEligibilitySelectionDto;
use Modules\ServiceCatalog\Application\Mappers\ServiceEligibilityInput;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionHandler;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final readonly class ServiceEligibilityAdapter implements ServiceEligibilityResolverInterface
{
    public function __construct(private ValidateServiceSelectionHandler $validateServiceSelectionHandler) {}

    public function validateSelection(AuthenticatedPrincipal $actor, string $offeringId, ?string $versionId, OfferingSelectionContext $context): ServiceEligibilitySelectionDto
    {
        return ServiceEligibilityInput::selection($this->validateServiceSelectionHandler->handle(new ValidateServiceSelectionCommand($actor, $offeringId, $versionId, $context)));
    }
}
