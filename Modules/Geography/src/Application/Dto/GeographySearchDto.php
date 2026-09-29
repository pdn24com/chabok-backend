<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Dto;

final readonly class GeographySearchDto
{
    public function __construct(
        public ?string $search = null,
        public bool $active = true,
        public int $page = 1,
        public int $perPage = 25,
        public ?string $provinceId = null,
        public ?string $provinceCode = null,
    ) {}
}
