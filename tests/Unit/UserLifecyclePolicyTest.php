<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Foundation\Domain\ApiException;
use Modules\User\Domain\UserLifecyclePolicy;
use PHPUnit\Framework\TestCase;

final class UserLifecyclePolicyTest extends TestCase
{
    public function test_suspension_can_be_reactivated(): void
    {
        (new UserLifecyclePolicy())->assertTransition('SUSPENDED', 'ACTIVE');
        $this->addToAssertionCount(1);
    }

    public function test_admin_transition_cannot_bypass_invitation_activation_proof(): void
    {
        $this->expectException(ApiException::class);
        (new UserLifecyclePolicy())->assertTransition('INVITED', 'ACTIVE');
    }
}
