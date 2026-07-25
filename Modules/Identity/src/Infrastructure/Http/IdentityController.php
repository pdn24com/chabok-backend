<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Infrastructure\Http\RefreshCookieFactory;
use Modules\Identity\Application\IdentityService;

final readonly class IdentityController
{
    public function __construct(
        private IdentityService $identity,
        private RefreshCookieFactory $cookies,
    ) {}

    public function login(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['identifier', 'password', 'client_type', 'device_id', 'device_name']);
        $input = $request->validate([
            'identifier' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string'],
            'client_type' => ['sometimes', 'in:BRANCH_PANEL'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:200'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);
        $result = $this->identity->login(
            $input['identifier'],
            $input['password'],
            $input['device_id'] ?? null,
            $input['device_name'] ?? null,
            $this->correlationId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponder::success($request, $result['payload'])
            ->withCookie($this->cookies->create($result['refresh_token'], $result['refresh_expires_at']));
    }

    public function refresh(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $result = $this->identity->refresh(
            (string) $request->cookie((string) config('chabok.refresh_cookie.name'), ''),
            $this->correlationId($request),
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponder::success($request, $result['payload'])
            ->withCookie($this->cookies->create($result['refresh_token'], $result['refresh_expires_at']));
    }

    public function logout(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $this->identity->logout(
            $request->cookie((string) config('chabok.refresh_cookie.name')),
            $this->correlationId($request),
        );

        return ApiResponder::success($request, ['success' => true])
            ->withCookie($this->cookies->clear());
    }

    public function logoutAll(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['current_password', 'step_up_verification_token']);
        $input = $request->validate([
            'current_password' => ['required_without:step_up_verification_token', 'string'],
            'step_up_verification_token' => ['required_without:current_password', 'string'],
        ]);
        $this->assertExactlyOne($input, 'current_password', 'step_up_verification_token');
        $count = $this->identity->logoutAll(
            $this->principal($request),
            $input['current_password'] ?? null,
            $input['step_up_verification_token'] ?? null,
            $this->correlationId($request),
        );

        return ApiResponder::success($request, ['revoked_session_count' => $count])
            ->withCookie($this->cookies->clear());
    }

    public function sendOtp(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['identifier', 'purpose']);
        $input = $request->validate([
            'identifier' => ['required', 'string', 'max:254'],
            'purpose' => ['required', 'in:ACTIVATION,PASSWORD_RESET'],
        ]);

        return ApiResponder::success($request, $this->identity->sendOtp(
            $input['identifier'],
            $input['purpose'],
            $this->correlationId($request),
            $request->ip(),
        ));
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['challenge_id', 'code']);
        $input = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        return ApiResponder::success($request, $this->identity->verifyOtp($input['challenge_id'], $input['code']));
    }

    public function activatePassword(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['verification_token', 'invitation_token', 'new_password']);
        $input = $request->validate([
            'verification_token' => ['required_without:invitation_token', 'string'],
            'invitation_token' => ['required_without:verification_token', 'string'],
            'new_password' => ['required', 'string'],
        ]);
        $this->assertExactlyOne($input, 'verification_token', 'invitation_token');
        $userId = $this->identity->activatePassword(
            $input['verification_token'] ?? null,
            $input['invitation_token'] ?? null,
            $input['new_password'],
            $this->correlationId($request),
        );

        return ApiResponder::success($request, ['user_id' => $userId, 'status' => 'ACTIVE']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['verification_token', 'new_password']);
        $input = $request->validate([
            'verification_token' => ['required', 'string'],
            'new_password' => ['required', 'string'],
        ]);
        $result = $this->identity->resetPassword(
            $input['verification_token'],
            $input['new_password'],
            $this->correlationId($request),
        );

        return ApiResponder::success($request, [
            'revoked_session_count' => $result['revoked_session_count'],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['current_password', 'new_password']);
        $input = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string'],
        ]);
        $this->identity->changePassword(
            $this->principal($request),
            $input['current_password'],
            $input['new_password'],
            $this->correlationId($request),
        );

        return ApiResponder::success($request, ['success' => true]);
    }

    public function sessions(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->identity->listSessions($this->principal($request)->userId));
    }

    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        $this->identity->revokeOwnedSession(
            $this->principal($request),
            $sessionId,
            $this->correlationId($request),
        );

        return ApiResponder::success($request, ['success' => true]);
    }

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlationId(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }

    /** @param array<string, mixed> $input */
    private function assertExactlyOne(array $input, string $first, string $second): void
    {
        if (isset($input[$first]) === isset($input[$second])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $first => ['Exactly one proof is required.'],
            ]);
        }
    }
}
