<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;

final class EloquentContactPointRepository implements ContactPointRepositoryInterface
{
    public function create(array $attributes): ContactPointRecord
    {
        return ContactPointRecord::query()->forceCreate($attributes);
    }
}
