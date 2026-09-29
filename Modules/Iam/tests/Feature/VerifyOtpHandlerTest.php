<?php

declare(strict_types=1);

namespace Modules\Iam\Tests\Feature;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\UseCases\VerifyOtp\VerifyOtpCommand;
use Modules\Iam\Application\UseCases\VerifyOtp\VerifyOtpHandler;
use Modules\Iam\Domain\Enums\OtpStatus;
use Modules\Iam\Infrastructure\Persistence\Models\OtpChallengeRecord;
use Modules\Iam\Infrastructure\Repositories\EloquentOtpChallengeRepository;
use Tests\TestCase;

final class VerifyOtpHandlerTest extends TestCase
{
    public function test_last_invalid_attempt_is_committed_before_public_rejection(): void
    {
        $challenge = $this->challenge(1);
        try {
            $this->handler()->handle(new VerifyOtpCommand('48038078', '654321'));
            self::fail('Invalid OTP was accepted.');
        } catch (ApiException $error) {
            self::assertSame(0, DB::connection()->transactionLevel());
            self::assertSame(ApiErrorCode::ValidationError, $error->errorCode);
            self::assertSame(422, $error->httpStatus);
        }
        $challenge->refresh();
        self::assertSame(0, $challenge->remaining_attempts);
        self::assertSame(OtpStatus::LOCKED, $challenge->status);
    }

    public function test_success_stores_only_the_token_hash_and_keeps_the_original_expiry(): void
    {
        $challenge = $this->challenge();
        $result = $this->handler()->handle(new VerifyOtpCommand('48038078', '123456'));
        $challenge->refresh();
        self::assertSame(600, $result->expiresIn);
        self::assertSame(hash('sha256', $result->verificationToken), $challenge->verification_token_hash);
        self::assertSame(OtpStatus::VERIFIED, $challenge->status);
        self::assertSame('2026-09-16T12:10:00+00:00', $challenge->expires_at->format(DATE_ATOM));
        self::assertNotContains($result->verificationToken, $challenge->getAttributes());
    }

    public function test_expiration_is_committed_without_decrementing_attempts(): void
    {
        $challenge = $this->challenge(3, '2026-09-16 11:59:59');
        try {
            $this->handler()->handle(new VerifyOtpCommand('48038078', '123456'));
            self::fail('Expired OTP was accepted.');
        } catch (ApiException $error) {
            self::assertSame(ApiErrorCode::ValidationError, $error->errorCode);
        }
        $challenge->refresh();
        self::assertSame(OtpStatus::EXPIRED, $challenge->status);
        self::assertSame(3, $challenge->remaining_attempts);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.identity_unit' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('identity_unit');
        $migration = require glob(base_path('Modules/Iam/database/migrations/*_create_otp_challenges.php'))[0];
        $migration->up();
    }

    private function handler(): VerifyOtpHandler
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-09-16T12:00:00Z'));

        return new VerifyOtpHandler(DB::connection(), $clock, new EloquentOtpChallengeRepository);
    }

    private function challenge(int $attempts = 5, string $expiresAt = '2026-09-16 12:10:00'): OtpChallengeRecord
    {
        return OtpChallengeRecord::query()->forceCreate([
            'challenge_id' => '48038078',
            'user_id' => '5212567',
            'destination_fingerprint' => hash('sha256', '5212567'),
            'purpose' => 'PASSWORD_RESET',
            'status' => OtpStatus::PENDING,
            'expires_at' => $expiresAt,
            'code_hash' => password_hash('123456', PASSWORD_BCRYPT, ['cost' => 4]),
            'remaining_attempts' => $attempts,
        ]);
    }
}
