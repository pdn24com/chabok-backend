<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

enum ManifestState: string
{
    /** @return list<self> */
    public static function editable(): array
    {
        return [self::Draft, self::Open];
    }

    public function isEditable(): bool
    {
        return in_array($this, self::editable(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    case Draft = 'DRAFT';
    case Open = 'OPEN';
    case Closed = 'CLOSED';
}
