<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Modules\Customer\Domain\Enums\ContactPointIdentifierKind;
use Modules\Customer\Domain\Enums\ContactPointScope;
use Modules\Customer\Domain\Enums\ContactPointStatus;
use Modules\Customer\Domain\Enums\ContactPointType;

/**
 * One row of the contact-point set a person's tab submits. A row with an ID is an existing channel of the
 * same person, a row without one is new. The normalised value is never part of the input: the server
 * derives it from the value and the channel type.
 */
final readonly class CustomerContactPointDraftDto
{
    public function __construct(
        public ?string $id,
        public ContactPointType $type,
        public ContactPointIdentifierKind $identifierKind,
        public string $value,
        public ContactPointScope $scope,
        public bool $isDefault,
        public ContactPointStatus $status,
        public ?int $priority,
        public ?string $subtype,
        public ?string $workContext,
        public ?string $relationshipId,
        public ?string $addressId,
        public bool $verifiedManually,
    ) {}
}
