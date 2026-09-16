<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

interface ManifestNumberRepository
{
    public function initialize(array $attributes): void;

    public function lock(string $key): ?object;

    public function update(string $key, array $changes): void;
}
