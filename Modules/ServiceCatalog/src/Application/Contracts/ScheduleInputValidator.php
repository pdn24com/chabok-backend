<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

interface ScheduleInputValidator
{
    public function validate(array $policy): void;
}
