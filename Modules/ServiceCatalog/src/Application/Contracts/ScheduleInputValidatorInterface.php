<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

interface ScheduleInputValidatorInterface
{
    public function validate(array $policy): void;
}
