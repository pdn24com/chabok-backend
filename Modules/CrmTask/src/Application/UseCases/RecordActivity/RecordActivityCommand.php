<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\RecordActivity;

use Modules\CrmTask\Application\Dto\ActivityDraftDto;
use Modules\CrmTask\Application\Dto\ActivityFilingDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class RecordActivityCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public ActivityDraftDto $activity,
        public ActivityFilingDto $filing,
    ) {}
}
