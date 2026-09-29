<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Policies;

use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Pricing\Domain\Enums\PricingTransition;
use Modules\Pricing\Domain\Exceptions\PricingVersionViolation;

final class PricingVersionPolicy
{
    public static function assertDraft(string $status, int $currentVersion, int $expectedVersion): void
    {
        if ($status !== VersionLifecycleStatus::Draft->value) {
            throw new PricingVersionViolation('pricing.only_drafts_are_editable');
        }
        if ($currentVersion !== $expectedVersion) {
            throw new PricingVersionViolation('pricing.draft_changed_since_loaded', $currentVersion);
        }
    }

    public static function assertTransition(string $status, PricingTransition $transition): void
    {
        if ($status !== $transition->sourceStatus()->value) {
            throw new PricingVersionViolation($transition->failureMessageKey());
        }
    }
}
