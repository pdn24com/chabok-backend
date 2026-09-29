<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Pricing\Domain\Enums\PricingTransition;
use Modules\Pricing\Domain\Exceptions\PricingVersionViolation;
use Modules\Pricing\Domain\Policies\PricingVersionPolicy;
use PHPUnit\Framework\TestCase;

final class PricingVersionPolicyTest extends TestCase
{
    public function test_each_transition_requires_its_declared_source_and_preserves_failure_reason(): void
    {
        foreach (PricingTransition::cases() as $transition) {
            PricingVersionPolicy::assertTransition($transition->sourceStatus()->value, $transition);
            self::assertSame($transition, PricingTransition::fromInput($transition->value));
            try {
                PricingVersionPolicy::assertTransition(VersionLifecycleStatus::Archived->value, $transition);
                self::fail('An archived version cannot transition.');
            } catch (PricingVersionViolation $error) {
                self::assertNull($error->currentVersion);
                self::assertSame($transition->failureMessageKey(), $error->messageKey);
            }
        }
    }

    public function test_edit_checks_draft_state_before_optimistic_version_and_reports_current_version(): void
    {
        PricingVersionPolicy::assertDraft(VersionLifecycleStatus::Draft->value, 3, 3);
        foreach (['PUBLISHED', 'DRAFT'] as $state) {
            try {
                PricingVersionPolicy::assertDraft($state, 3, 1);
                self::fail('Invalid edits must fail.');
            } catch (PricingVersionViolation $error) {
                self::assertSame($state === 'DRAFT' ? 3 : null, $error->currentVersion);
                self::assertSame($state === 'DRAFT' ? 'pricing.draft_changed_since_loaded' : 'pricing.only_drafts_are_editable', $error->messageKey);
            }
        }
    }

    public function test_unknown_action_is_a_domain_validation_failure(): void
    {
        try {
            PricingTransition::fromInput('delete');
            self::fail('An unknown action must be rejected.');
        } catch (PricingVersionViolation $error) {
            self::assertSame('pricing.unsupported_lifecycle_action', $error->messageKey);
        }
    }
}
