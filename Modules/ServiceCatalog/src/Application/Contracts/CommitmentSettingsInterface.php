<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

interface CommitmentSettingsInterface
{
    public function timezone(): string;
}
