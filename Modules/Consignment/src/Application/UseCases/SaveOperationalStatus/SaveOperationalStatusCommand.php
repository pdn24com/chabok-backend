<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\SaveOperationalStatus;

use Modules\Consignment\Application\Dto\OperationalStatusDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class SaveOperationalStatusCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public ?string $id,
        public OperationalStatusDto $input,
        public string $correlation,
    ) {}
}
