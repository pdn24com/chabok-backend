<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Enums;

enum UserCreationMode: string
{
    case DirectActive = 'DIRECT_ACTIVE';
    case SmsInvitation = 'SMS_INVITATION';
    case EmailInvitation = 'EMAIL_INVITATION';
}
