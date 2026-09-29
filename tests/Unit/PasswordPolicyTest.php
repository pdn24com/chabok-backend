<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Iam\Domain\Exceptions\WeakPassword;
use Modules\Iam\Domain\Policies\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function test_accepts_a_policy_compliant_password(): void
    {
        (new PasswordPolicy)->assertValid('Valid!Password123');
        $this->addToAssertionCount(1);
    }

    public function test_rejects_a_weak_password(): void
    {
        $this->expectException(WeakPassword::class);
        (new PasswordPolicy)->assertValid('weak');
    }
}
