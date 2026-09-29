<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Dto;

final readonly class NodeDraftDto
{
    public function __construct(public string $code, public NodeDetailsDto $details) {}
}
