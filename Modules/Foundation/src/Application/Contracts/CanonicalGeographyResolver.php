<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface CanonicalGeographyResolver
{
    /** @param array<string, mixed> $contact @return array<string, mixed> */
    public function canonicalizeContact(array $contact, bool $required): array;
}
