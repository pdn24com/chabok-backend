<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Foundation\Infrastructure\Http\ApiExceptionRenderer;
use Modules\Foundation\Infrastructure\Http\Middleware\AuthenticateAccessToken;
use Modules\Foundation\Infrastructure\Http\Middleware\CorrelationId;
use Modules\Foundation\Infrastructure\Http\Middleware\CredentialedCors;
use Modules\Foundation\Infrastructure\Http\Middleware\IdempotentCommand;
use Modules\Foundation\Infrastructure\Http\Middleware\NodeContext;
use Modules\Foundation\Infrastructure\Http\Middleware\RequireExactOrigin;
use Modules\Foundation\Infrastructure\Http\Middleware\RequirePasswordChangeCompleted;
use Modules\Foundation\Infrastructure\Http\Middleware\RequireSecureTransport;
use Modules\Foundation\Infrastructure\Http\Middleware\ValidateNodeAccess;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            CorrelationId::class,
            RequireSecureTransport::class,
            CredentialedCors::class,
            NodeContext::class,
        ]);

        $middleware->alias([
            'access.auth' => AuthenticateAccessToken::class,
            'idempotent' => IdempotentCommand::class,
            'origin.exact' => RequireExactOrigin::class,
            'password.changed' => RequirePasswordChangeCompleted::class,
            'node.access' => ValidateNodeAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(ApiExceptionRenderer::render(...));
    })->create();
