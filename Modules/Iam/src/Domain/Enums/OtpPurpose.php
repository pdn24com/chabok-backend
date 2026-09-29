<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Enums;

enum OtpPurpose: string
{
    case ACTIVATION = 'ACTIVATION';
    case PASSWORD_RESET = 'PASSWORD_RESET';
}
