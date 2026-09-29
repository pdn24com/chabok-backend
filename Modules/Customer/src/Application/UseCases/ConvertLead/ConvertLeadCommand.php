<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ConvertLead;

use Modules\Customer\Application\Dto\LeadConversionDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ConvertLeadCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public LeadConversionDto $conversion,
    ) {}
}
