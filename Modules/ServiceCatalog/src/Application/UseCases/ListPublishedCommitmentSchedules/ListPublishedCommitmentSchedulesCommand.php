<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListPublishedCommitmentSchedulesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $includeVersionIds = []) {}
}
