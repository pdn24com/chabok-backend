<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Dto;

final readonly class AreaDraftDto
{
    public function __construct(public string $code, public string $title, public ?string $parentId) {}
}
