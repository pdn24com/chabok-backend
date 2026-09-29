<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Iam\Domain\Enums\UserStatus;
use Modules\Iam\Domain\Exceptions\UserLifecycleViolation;
use Modules\Iam\Domain\Policies\UserLifecyclePolicy;
use PHPUnit\Framework\TestCase;

final class UserLifecyclePolicyTest extends TestCase
{
    public function test_suspension_can_be_reactivated(): void
    {
        (new UserLifecyclePolicy)->assertTransition(UserStatus::Suspended, UserStatus::Active);
        $this->addToAssertionCount(1);
    }

    public function test_admin_transition_cannot_bypass_invitation_activation_proof(): void
    {
        $this->expectException(UserLifecycleViolation::class);
        (new UserLifecyclePolicy)->assertTransition(UserStatus::Invited, UserStatus::Active);
    }
}
