<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Adapters;

use Modules\ServiceCatalog\Application\Contracts\CommitmentSettingsInterface;

final class LaravelCommitmentSettings implements CommitmentSettingsInterface
{
    public function timezone(): string
    {
        return (string) config('service_commitments.timezone', 'Asia/Tehran');
    }
}
