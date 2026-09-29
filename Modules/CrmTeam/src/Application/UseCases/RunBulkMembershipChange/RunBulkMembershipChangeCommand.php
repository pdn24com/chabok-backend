<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\RunBulkMembershipChange;

use Modules\CrmTeam\Application\Dto\BulkMembershipDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class RunBulkMembershipChangeCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public BulkMembershipDto $input,
        /** A preview reads and reports; it writes nothing. */
        public bool $previewOnly = false,
    ) {}
}
