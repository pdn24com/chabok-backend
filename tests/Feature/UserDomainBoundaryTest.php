<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Modules\Iam\Domain\Enums\UserCreationMode;
use Modules\Iam\Domain\Enums\UserStatus;
use Modules\Iam\Domain\Exceptions\InvalidUserCreation;
use Modules\Iam\Domain\Exceptions\UserLifecycleViolation;
use Modules\Iam\Domain\Policies\UserCreationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UserDomainBoundaryTest extends TestCase
{
    public static function modes(): array
    {
        return [
            [UserCreationMode::DirectActive, true, false, false, true],
            [UserCreationMode::DirectActive, false, true, true, false],
            [UserCreationMode::SmsInvitation, false, true, false, true],
            [UserCreationMode::SmsInvitation, false, false, true, false],
            [UserCreationMode::SmsInvitation, true, true, false, false],
            [UserCreationMode::EmailInvitation, false, false, true, true],
            [UserCreationMode::EmailInvitation, false, true, false, false],
            [UserCreationMode::EmailInvitation, true, false, true, false],
            [null, true, true, true, false],
        ];
    }

    #[DataProvider('modes')]
    public function test_creation_policy_keeps_mode_requirements(?UserCreationMode $mode, bool $password, bool $mobile, bool $email, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(InvalidUserCreation::class);
        }
        self::assertSame($mode, (new UserCreationPolicy)->requireValidMode($mode, $password, $mobile, $email));
    }

    public function test_user_domain_failures_preserve_http_status_code_and_details(): void
    {
        Route::get('/api/user-domain-test/creation', static fn () => throw new InvalidUserCreation);
        Route::get('/api/user-domain-test/lifecycle', static fn () => throw new UserLifecycleViolation(UserStatus::Invited, UserStatus::Active));
        $this->getJson('/api/user-domain-test/creation')->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
        $this->getJson('/api/user-domain-test/lifecycle')->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.from', 'INVITED')->assertJsonPath('details.to', 'ACTIVE');
    }
}
