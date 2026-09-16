<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\SaveOperationalStatus;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class SaveOperationalStatusCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public ?string $id,
        public array $input,
        public string $correlation,
    )
    {
    }
}
