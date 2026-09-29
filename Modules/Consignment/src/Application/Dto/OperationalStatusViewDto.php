<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;

final readonly class OperationalStatusViewDto
{
    public function __construct(public StatusRecord $status, public bool $canManage) {}
}
