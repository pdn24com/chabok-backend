<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\RoutePurpose;

final readonly class RouteDefinitionFiltersDto
{
    public function __construct(public ?string $search = null, public ?RoutePurpose $purpose = null, public int $page = 1, public int $perPage = 20) {}

    public static function fromValidated(array $input): self
    {
        return new self($input['search'] ?? null, isset($input['purpose']) ? RoutePurpose::from($input['purpose']) : null, (int) ($input['page'] ?? 1), (int) ($input['per_page'] ?? 20));
    }
}
