<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;

interface ContactPointRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ContactPointRecord;
}
