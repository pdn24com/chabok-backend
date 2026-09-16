<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface GeographyRepository
{
    public function provinces(array $filters, ?string $normalizedSearch): Page;

    public function cities(array $filters, ?string $normalizedSearch): Page;

    public function city(string $cityId): ?\stdClass;
}
