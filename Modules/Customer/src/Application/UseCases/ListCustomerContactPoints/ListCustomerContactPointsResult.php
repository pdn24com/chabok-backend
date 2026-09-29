<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerContactPoints;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;

/** The whole contact-point set of one person, default entries first. */
final readonly class ListCustomerContactPointsResult
{
    /**
     * @param  Collection<int, ContactPointRecord>  $contactPoints
     */
    public function __construct(
        public Collection $contactPoints,
    ) {}
}
