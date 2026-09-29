<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerContactPoints;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;

/** The contact-point set of the person as the replacement left it, default entries first. */
final readonly class SaveCustomerContactPointsResult
{
    /**
     * @param  Collection<int, ContactPointRecord>  $contactPoints
     */
    public function __construct(
        public Collection $contactPoints,
    ) {}
}
