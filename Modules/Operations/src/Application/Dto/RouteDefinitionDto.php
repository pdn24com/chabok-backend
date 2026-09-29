<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

final readonly class RouteDefinitionDto
{
    public function __construct(public string $code, public string $title) {}

    public static function fromValidated(array $input): self
    {
        return new self($input['route_code'], $input['route_title']);
    }
}
