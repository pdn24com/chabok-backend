<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

interface CommitmentSettings
{
    public function timezone(): string;
}
