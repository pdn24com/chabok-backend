<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListProvinces;

use Modules\Geography\Application\Dto\GeographySearchDto;

final readonly class ListProvincesCommand
{
    public function __construct(public GeographySearchDto $filters) {}
}
