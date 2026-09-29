<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

/**
 * Maker-checker lifecycle shared by every versioned configuration record
 * (Service Catalog resources, Pricing tariffs and zone sets).
 */
enum VersionLifecycleStatus: string
{
    /** Statuses that block a new successor draft on the same identity. @return list<self> */
    public static function unpublished(): array
    {
        return [self::Draft, self::Validating, self::ReadyForApproval, self::Approved];
    }

    /** Statuses whose effective interval already claims a period. @return list<self> */
    public static function effective(): array
    {
        return [self::Approved, self::Published];
    }

    /** Statuses a maker may still edit. @return list<self> */
    public static function editable(): array
    {
        return [self::Draft, self::ReadyForApproval];
    }

    public function isEditable(): bool
    {
        return in_array($this, self::editable(), true);
    }

    /**
     * @param  list<self>  $cases
     * @return list<string>
     */
    public static function valuesOf(array $cases): array
    {
        return array_column($cases, 'value');
    }

    /** @return list<string> */
    public static function values(): array
    {
        return self::valuesOf(self::cases());
    }
    case Draft = 'DRAFT';
    case Validating = 'VALIDATING';
    case ReadyForApproval = 'READY_FOR_APPROVAL';
    case Approved = 'APPROVED';
    case Published = 'PUBLISHED';
    case Superseded = 'SUPERSEDED';
    case Archived = 'ARCHIVED';
    case Cancelled = 'CANCELLED';
}
