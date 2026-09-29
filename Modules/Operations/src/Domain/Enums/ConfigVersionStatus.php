<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

/** Maker-checker lifecycle for Coverage Policy and Route Definition versions. */
enum ConfigVersionStatus: string
{
    /** Statuses a maker may still archive instead of publishing. @return list<self> */
    public static function archivable(): array
    {
        return [self::Draft, self::Validated, self::Approved];
    }

    /** Statuses whose content a Route Plan may still rely on. @return list<self> */
    public static function resolvable(): array
    {
        return [self::Published, self::Superseded];
    }

    /**
     * @param  list<self>  $cases
     * @return list<string>
     */
    public static function valuesOf(array $cases): array
    {
        return array_column($cases, 'value');
    }
    case Draft = 'DRAFT';
    case Validated = 'VALIDATED';
    case Approved = 'APPROVED';
    case Published = 'PUBLISHED';
    case Superseded = 'SUPERSEDED';
    case Archived = 'ARCHIVED';
}
