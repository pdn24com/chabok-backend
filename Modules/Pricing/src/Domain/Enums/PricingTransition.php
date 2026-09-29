<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Pricing\Domain\Exceptions\PricingVersionViolation;

enum PricingTransition: string
{
    public static function fromInput(string $action): self
    {
        return self::tryFrom($action) ?? throw new PricingVersionViolation('pricing.unsupported_lifecycle_action');
    }

    public function sourceStatus(): VersionLifecycleStatus
    {
        return match ($this) {
            self::Approve => VersionLifecycleStatus::Draft, self::Publish => VersionLifecycleStatus::Approved,
            self::Supersede => VersionLifecycleStatus::Published, self::Archive => VersionLifecycleStatus::Superseded,
        };
    }

    /** Key into lang/<locale>/api.php. */
    public function failureMessageKey(): string
    {
        return match ($this) {
            self::Approve => 'pricing.only_draft_can_be_approved',
            self::Publish => 'pricing.only_approved_version_can_be_published',
            self::Supersede => 'pricing.only_published_version_can_be_superseded',
            self::Archive => 'pricing.only_superseded_version_can_be_archived',
        };
    }
    case Approve = 'approve';
    case Publish = 'publish';
    case Supersede = 'supersede';
    case Archive = 'archive';
}
