<?php

declare(strict_types=1);

namespace Modules\Foundation\Tests\Feature;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\Contracts\AccessTokenService;
use Modules\Foundation\Application\SensitiveDataRedactor;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Infrastructure\Http\RefreshCookieFactory;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ApiFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('test.principal', ForcedPrincipal::class);

        Route::middleware('api')->prefix('api/v1')->group(function (): void {
            Route::get('/foundation/success', fn (Request $request) => ApiResponder::success(
                $request,
                ['ok' => true],
            ));
            Route::get('/foundation/context', fn (Request $request) => ApiResponder::success(
                $request,
                [
                    'node_id' => $request->attributes->get('node_id'),
                    'tenant_id' => $request->attributes->get('tenant_id'),
                ],
            ));
            Route::post('/foundation/origin', fn (Request $request) => ApiResponder::success(
                $request,
                ['accepted' => true],
            ))->middleware('origin.exact');
            Route::options('/foundation/origin', fn () => response('', 204));
            Route::post('/foundation/validate', function (Request $request) {
                $request->validate(['name' => ['required', 'string']]);

                return ApiResponder::success($request, ['valid' => true]);
            });
            Route::get('/foundation/failure', fn () => throw new \RuntimeException(
                'internal secret must not escape',
            ));
            Route::get('/foundation/forced', fn (Request $request) => ApiResponder::success(
                $request,
                ['allowed' => true],
            ))->middleware(['test.principal', 'password.changed'])->name('users.index');
            Route::post('/foundation/change-password', fn (Request $request) => ApiResponder::success(
                $request,
                ['allowed' => true],
            ))->middleware(['test.principal', 'password.changed'])->name('auth.password.change');
        });
    }

    public function test_success_envelope_and_correlation_are_propagated(): void
    {
        $correlationId = '018f77b6-7c5e-7c39-b4e2-111111111111';

        $this->withHeader('X-Correlation-ID', $correlationId)
            ->getJson('/api/v1/foundation/success')
            ->assertOk()
            ->assertHeader('X-Correlation-ID', $correlationId)
            ->assertExactJson([
                'data' => ['ok' => true],
                'meta' => [],
                'correlation_id' => $correlationId,
            ]);
    }

    public function test_invalid_correlation_and_node_headers_use_validation_envelope(): void
    {
        $this->withHeader('X-Correlation-ID', 'not-a-uuid')
            ->getJson('/api/v1/foundation/success')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'message',
                'field_errors' => ['X-Correlation-ID'],
                'details',
                'correlation_id',
            ]);

        $this->flushHeaders();

        $this->withHeader('X-Node-Id', 'not-a-uuid')
            ->getJson('/api/v1/foundation/context')
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath(
                'field_errors.X-Node-Id.0',
                'The X-Node-Id field must be a UUID.',
            );
    }

    public function test_node_context_never_establishes_tenant_context(): void
    {
        $nodeId = '018f77b6-7c5e-7c39-b4e2-222222222222';

        $this->withHeader('X-Node-Id', $nodeId)
            ->getJson('/api/v1/foundation/context')
            ->assertOk()
            ->assertJsonPath('data.node_id', $nodeId)
            ->assertJsonPath('data.tenant_id', null);
    }

    public function test_exact_origin_and_credentialed_cors_are_allowlisted(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/foundation/origin')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->flushHeaders();

        $this->postJson('/api/v1/foundation/origin')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ORIGIN_NOT_ALLOWED');

        $this->flushHeaders();

        $this->withHeader('Origin', 'https://localhost.attacker.example')
            ->postJson('/api/v1/foundation/origin')
            ->assertStatus(403)
            ->assertHeaderMissing('Access-Control-Allow-Origin');

        $this->flushHeaders();

        $this->call(
            'OPTIONS',
            '/api/v1/foundation/origin',
            server: [
                'HTTP_ORIGIN' => 'http://localhost:4173',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ],
        )
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4173');
    }

    public function test_validation_and_unexpected_exceptions_are_safe(): void
    {
        $this->postJson('/api/v1/foundation/validate', [])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => ['name']]);

        $this->getJson('/api/v1/foundation/failure')
            ->assertStatus(500)
            ->assertJsonPath('error_code', 'INTERNAL_SERVER_ERROR')
            ->assertJsonMissing(['message' => 'internal secret must not escape']);
    }

    public function test_refresh_cookie_is_secure_http_only_lax_and_clearable(): void
    {
        $factory = $this->app->make(RefreshCookieFactory::class);
        $cookie = $factory->create('opaque-token');
        $clear = $factory->clear();

        self::assertSame('chabok_refresh', $cookie->getName());
        self::assertSame('/api/v1/auth', $cookie->getPath());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());
        self::assertTrue($clear->isSecure());
        self::assertTrue($clear->isHttpOnly());
        self::assertSame('lax', $clear->getSameSite());
        self::assertStringContainsString('Max-Age=0', (string) $clear);
    }

    public function test_forced_password_session_is_restricted_to_the_allowlist(): void
    {
        $this->getJson('/api/v1/foundation/forced')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');

        $this->postJson('/api/v1/foundation/change-password')
            ->assertOk()
            ->assertJsonPath('data.allowed', true);
    }

    public function test_access_tokens_are_signed_short_lived_and_safely_rejected(): void
    {
        $service = $this->app->make(AccessTokenService::class);
        $issued = $service->issue(new AuthenticatedPrincipal(
            userId: '018f77b6-7c5e-7c39-b4e2-333333333333',
            sessionId: '018f77b6-7c5e-7c39-b4e2-444444444444',
            hqId: '018f77b6-7c5e-7c39-b4e2-555555555555',
            mustChangePassword: true,
        ));
        $claims = $service->decode($issued['token']);

        self::assertSame(300, $issued['expires_in']);
        self::assertSame('018f77b6-7c5e-7c39-b4e2-333333333333', $claims->userId);
        self::assertTrue($claims->mustChangePassword);

        $this->expectException(ApiException::class);
        $service->decode($issued['token'].'tampered');
    }

    public function test_sensitive_values_are_redacted_from_context_and_messages(): void
    {
        $context = SensitiveDataRedactor::context([
            'password' => 'secret',
            'nested' => ['refresh_token' => 'opaque', 'safe' => 'value'],
        ]);
        $message = SensitiveDataRedactor::message(
            'Authorization=Bearer abc.def.ghi password=hunter2',
        );

        self::assertSame('[REDACTED]', $context['password']);
        self::assertSame('[REDACTED]', $context['nested']['refresh_token']);
        self::assertSame('value', $context['nested']['safe']);
        self::assertStringNotContainsString('abc.def.ghi', $message);
        self::assertStringNotContainsString('hunter2', $message);
    }
}

final class ForcedPrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('principal', new AuthenticatedPrincipal(
            userId: '018f77b6-7c5e-7c39-b4e2-333333333333',
            sessionId: '018f77b6-7c5e-7c39-b4e2-444444444444',
            hqId: '018f77b6-7c5e-7c39-b4e2-555555555555',
            mustChangePassword: true,
        ));

        return $next($request);
    }
}
