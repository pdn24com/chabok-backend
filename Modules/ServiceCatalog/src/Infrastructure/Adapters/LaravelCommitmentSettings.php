<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Adapters;

final class LaravelCommitmentSettings implements \Modules\ServiceCatalog\Application\Contracts\CommitmentSettings
{
    public function timezone(): string
    {
        return (string) config('service_commitments.timezone', 'Asia/Tehran');
    }
}
