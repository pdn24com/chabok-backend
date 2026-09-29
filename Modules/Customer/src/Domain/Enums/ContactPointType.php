<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

enum ContactPointType: string
{
    case MOBILE = 'MOBILE';
    case PHONE = 'PHONE';
    case INSTAGRAM = 'INSTAGRAM';
    case WHATSAPP = 'WHATSAPP';
    case TELEGRAM = 'TELEGRAM';
    case BALE = 'BALE';
    case EMAIL = 'EMAIL';
    case ADDRESS_REFERENCE = 'ADDRESS_REFERENCE';

    /** The one identifier kind a channel of this type can carry; a WhatsApp account is a phone number. */
    public function identifierKind(): ContactPointIdentifierKind
    {
        return match ($this) {
            self::MOBILE, self::PHONE, self::WHATSAPP => ContactPointIdentifierKind::PHONE,
            self::INSTAGRAM, self::TELEGRAM, self::BALE => ContactPointIdentifierKind::USERNAME,
            self::EMAIL => ContactPointIdentifierKind::EMAIL,
            self::ADDRESS_REFERENCE => ContactPointIdentifierKind::ADDRESS,
        };
    }
}
