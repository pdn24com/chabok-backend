<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Application\Contracts\CanonicalGeographyResolverInterface;
use Modules\Foundation\Application\Mappers\CoverageAddressInput;
use Modules\Foundation\Application\Serialization\CoverageAddressDocument;
use Modules\Foundation\Domain\ValueObjects\CoverageAddress;
use Modules\ServiceCatalog\Application\Contracts\OfferingContextInterface;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final readonly class OfferingContext implements OfferingContextInterface
{
    public function __construct(private CanonicalGeographyResolverInterface $canonicalGeographyResolver) {}

    public function canonicalizeCoverageContext(OfferingSelectionContext $context): OfferingSelectionContext
    {
        $canonical = clone $context;
        $canonical->sender = $this->canonicalAddress($context->sender);
        $canonical->receiver = $this->canonicalAddress($context->receiver);
        $canonical->receiverProvided = true;
        $canonical->factKeys = array_values(array_unique([...$canonical->factKeys, 'sender', 'receiver']));

        return $canonical;
    }

    private function canonicalAddress(CoverageAddress $address): CoverageAddress
    {
        $contact = $this->canonicalGeographyResolver->canonicalizeContact(CoverageAddressDocument::serialize($address), false);
        $canonical = CoverageAddressInput::fromArray($contact);
        $canonical->country ??= 'IR';
        $canonical->factKeys = array_values(array_unique([...$canonical->factKeys, 'country']));

        return $canonical;
    }
}
