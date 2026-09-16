<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Unit;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Identity\Application\Repositories\OtpChallengeRepository;
use Modules\Identity\Application\UseCases\VerifyOtp\VerifyOtpCommand;
use Modules\Identity\Application\UseCases\VerifyOtp\VerifyOtpHandler;
use PHPUnit\Framework\TestCase;

final class VerifyOtpHandlerTest extends TestCase
{
    public function test_last_invalid_attempt_is_committed_before_public_rejection(): void
    {
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-16T12:00:00Z'));
        $committed = false;
        $transactions = $this->createMock(TransactionManager::class);
        $transactions->expects(self::once())->method('run')->willReturnCallback(function (callable $operation) use (&$committed) {
            $result = $operation();
            $committed = true;
            return $result;
        });
        $challenges = $this->createMock(OtpChallengeRepository::class);
        $challenges->expects(self::once())->method('findForUpdate')->with('challenge')->willReturn((object) [
            'status' => 'PENDING',
            'expires_at' => '2026-09-16T12:10:00Z',
            'code_hash' => password_hash('123456', PASSWORD_BCRYPT, ['cost' => 4]),
            'remaining_attempts' => 1,
        ]);
        $challenges->expects(self::once())->method('update')->with('challenge', ['remaining_attempts' => 0, 'status' => 'LOCKED']);
        try {
            (new VerifyOtpHandler($transactions, $challenges, $clock))->handle(new VerifyOtpCommand('challenge', '654321'));
            self::fail('Invalid OTP was accepted.');
        } catch (ApiException $error) {
            self::assertTrue($committed, 'Rejection must not roll back the failed-attempt count.');
            self::assertSame(ApiErrorCode::ValidationError, $error->errorCode);
            self::assertSame(422, $error->httpStatus);
        }
    }

    public function test_success_stores_only_the_token_hash_and_keeps_the_original_expiry(): void
    {
        $now = new \DateTimeImmutable('2026-09-16T12:00:00Z');
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($now);
        $transactions = $this->createStub(TransactionManager::class);
        $transactions->method('run')->willReturnCallback(fn(callable $operation) => $operation());
        $challenges = $this->createMock(OtpChallengeRepository::class);
        $challenges->expects(self::once())->method('findForUpdate')->with('challenge')->willReturn((object) [
            'status' => 'PENDING',
            'user_id' => 'user',
            'expires_at' => '2026-09-16T12:10:00Z',
            'code_hash' => password_hash('123456', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        $stored = null;
        $challenges->expects(self::once())->method('update')->willReturnCallback(function (string $id, array $attributes) use (&$stored): void {
            $stored = $attributes;
        });
        $result = (new VerifyOtpHandler($transactions, $challenges, $clock))->handle(new VerifyOtpCommand('challenge', '123456'));
        self::assertSame(600, $result->data['expires_in']);
        self::assertSame(hash('sha256', $result->data['verification_token']), $stored['verification_token_hash']);
        self::assertSame('VERIFIED', $stored['status']);
        self::assertEquals($now->modify('+600 seconds'), $stored['expires_at']);
        self::assertArrayNotHasKey('verification_token', $stored);
    }
}
