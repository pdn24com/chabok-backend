<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

final readonly class OfferingContext
{
    public function __construct(private \Modules\Foundation\Application\Contracts\CanonicalGeographyResolver $geography)
    {
    }

    public function canonicalizeCoverageContext(array $context): array
    {
        foreach (['sender', 'receiver'] as $party) {
            $contact = $this->geography->canonicalizeContact((array) ($context[$party] ?? []), false);
            $contact['country'] ??= 'IR';
            $context[$party] = $contact;
        }
        return $context;
    }
}
