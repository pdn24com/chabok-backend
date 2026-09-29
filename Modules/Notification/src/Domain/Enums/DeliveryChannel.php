<?php

declare(strict_types=1);

namespace Modules\Notification\Domain\Enums;

enum DeliveryChannel: string
{
    case SMS = 'SMS';
    case EMAIL = 'EMAIL';
}
