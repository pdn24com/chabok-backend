<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Contracts;

use Modules\Organization\Application\Dto\NodeDetailsDto;

interface NetworkInputValidatorInterface
{
    public function validateNodeInput(string $hqId, NodeDetailsDto $input): void;
}
