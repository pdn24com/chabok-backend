<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum ActivityType: string
{
    case CALL = 'CALL';
    case MESSAGE = 'MESSAGE';
    case MEETING = 'MEETING';
    case DOCUMENT_SENT = 'DOCUMENT_SENT';
    case REFERRAL = 'REFERRAL';
    case NOTE = 'NOTE';
}
