<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Contracts;

use Modules\CrmTask\Application\Dto\ActivityDraftDto;
use Modules\CrmTask\Application\Dto\ActivityFilingDto;

interface ActivityValidatorInterface
{
    /**
     * An interaction recorded as part of a task action: its detail must fit its type. Fields are named
     * under `activity.`, the object the action carries it in.
     */
    public function validateEmbedded(string $hqId, ActivityDraftDto $activity): void;

    /**
     * An interaction recorded on its own. Besides fitting its type it must be complete for that type, must
     * not be a referral, and must be filed somewhere that agrees with itself. Returns the filing with
     * every part the submission left implicit filled in from the task or the opportunity.
     */
    public function validateStandalone(string $hqId, ActivityDraftDto $activity, ActivityFilingDto $filing): ActivityFilingDto;
}
