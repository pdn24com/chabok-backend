<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum ActivityChannel: string
{
    case PHONE = 'PHONE';
    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
    case WHATSAPP = 'WHATSAPP';
    case TELEGRAM = 'TELEGRAM';
    case BALE = 'BALE';
    case INSTAGRAM = 'INSTAGRAM';
    case IN_PERSON = 'IN_PERSON';
    case POST = 'POST';
}
