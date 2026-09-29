<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Consignment\Domain\Exceptions\ConsignmentRuleViolation;
use Modules\Consignment\Domain\Exceptions\InvalidNumberRange;
use Modules\Foundation\Application\Support\ApiMessage;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Presentation\Http\ApiExceptionRenderer;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\Middleware\AuthenticateAccessToken;
use Modules\Foundation\Presentation\Http\Middleware\CorrelationId;
use Modules\Foundation\Presentation\Http\Middleware\CredentialedCors;
use Modules\Foundation\Presentation\Http\Middleware\IdempotentCommand;
use Modules\Foundation\Presentation\Http\Middleware\NegotiateLocale;
use Modules\Foundation\Presentation\Http\Middleware\NodeContext;
use Modules\Foundation\Presentation\Http\Middleware\RequireExactOrigin;
use Modules\Foundation\Presentation\Http\Middleware\RequirePasswordChangeCompleted;
use Modules\Foundation\Presentation\Http\Middleware\RequireSecureTransport;
use Modules\Foundation\Presentation\Http\Middleware\ValidateNodeAccess;
use Modules\Iam\Domain\Exceptions\InvalidUserCreation;
use Modules\Iam\Domain\Exceptions\UserLifecycleViolation;
use Modules\Iam\Domain\Exceptions\WeakPassword;
use Modules\Manifest\Domain\Exceptions\ManifestRuleViolation;
use Modules\Operations\Domain\Exceptions\InvalidFleetConfiguration;
use Modules\Pricing\Domain\Exceptions\InvalidMatrixWorkbook;
use Modules\Pricing\Domain\Exceptions\InvalidPricingInput;
use Modules\Pricing\Domain\Exceptions\InvalidPricingZone;
use Modules\Pricing\Domain\Exceptions\InvalidTariffMatrix;
use Modules\Pricing\Domain\Exceptions\PricingValidationFailed;
use Modules\Pricing\Domain\Exceptions\PricingVersionViolation;
use Modules\Pricing\Presentation\Http\Resources\PricingValidationResource;
use Modules\ServiceCatalog\Domain\Enums\CommitmentUnavailability;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentRuleViolation;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentWindowUnavailable;
use Modules\ServiceCatalog\Domain\Exceptions\OfferingCommitmentUnavailable;

return Application::configure(basePath: \dirname(__DIR__))->withRouting(commands: __DIR__.'/../routes/console.php', health: '/up')->withMiddleware(function (Middleware $middleware): void {
    $middleware->api(prepend: [NegotiateLocale::class, CorrelationId::class, RequireSecureTransport::class, CredentialedCors::class, NodeContext::class]);
    $middleware->alias(['access.auth' => AuthenticateAccessToken::class, 'idempotent' => IdempotentCommand::class, 'origin.exact' => RequireExactOrigin::class, 'password.changed' => RequirePasswordChangeCompleted::class, 'node.access' => ValidateNodeAccess::class]);
})->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    $exceptions->render(fn (InvalidMatrixWorkbook $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422));
    $exceptions->render(fn (PricingValidationFailed $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422, details: (new PricingValidationResource($exception->validation))->resolve($request)));
    $exceptions->render(fn (PricingVersionViolation $exception, Request $request) => ApiResponder::error($request,
        $exception->currentVersion === null ? ApiErrorCode::ValidationError : ApiErrorCode::VersionConflict, $exception->messageKey,
        $exception->currentVersion === null ? 422 : 409, details: $exception->currentVersion === null ? [] : ['current_version' => $exception->currentVersion]));
    $exceptions->render(fn (InvalidPricingInput $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422));
    $exceptions->render(fn (InvalidTariffMatrix $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422, details: ['errors' => (new PricingValidationResource($exception->validation))->resolve($request)['errors']]));
    $exceptions->render(fn (InvalidPricingZone $exception, Request $request) => ApiResponder::error($request, $exception->errorCode, $exception->messageKey, 422,
        fieldErrors: $exception->fieldErrors, details: $exception->reasonCode === null ? [] : ['reason_code' => $exception->reasonCode]));
    $exceptions->render(fn (InvalidFleetConfiguration $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422, fieldErrors: $exception->fieldErrors));
    $exceptions->render(fn (InvalidNumberRange $exception, Request $request) => ApiResponder::error($request, $exception->errorCode, $exception->messageKey, 422));
    $exceptions->render(fn (ConsignmentRuleViolation $exception, Request $request) => ApiResponder::error($request, $exception->errorCode, $exception->messageKey, 422,
        fieldErrors: $exception->fieldErrors, details: $exception->reasonCode === null ? [] : ['reason_code' => $exception->reasonCode]));
    $exceptions->render(fn (WeakPassword $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, 'foundation.request_is_invalid', 422,
        fieldErrors: ['password' => [ApiMessage::translate($exception->messageKey)]]));
    $exceptions->render(fn (ManifestRuleViolation $exception, Request $request) => ApiResponder::error($request, $exception->errorCode, $exception->messageKey, $exception->errorCode === ApiErrorCode::ManifestVersionConflict ? 409 : 422, fieldErrors: $exception->fieldErrors, details: $exception->details));
    $exceptions->render(fn (CommitmentRuleViolation $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError,
        $exception->messageKey, 422, details: ['reason_code' => $exception->reason->value]));
    $exceptions->render(fn (CommitmentWindowUnavailable $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422,
        details: ['reason_code' => $exception->reasonCode()]));
    $exceptions->render(fn (OfferingCommitmentUnavailable $exception, Request $request) => ApiResponder::error($request,
        $exception->reason === CommitmentUnavailability::CommitmentScopeUnavailable ? ApiErrorCode::ScopeAccessDenied : ApiErrorCode::ValidationError,
        $exception->messageKey, $exception->reason === CommitmentUnavailability::CommitmentScopeUnavailable ? 403 : 422,
        details: $exception->reason === CommitmentUnavailability::CatalogDependencyUnavailable
            ? ['reason_code' => $exception->reason->value, 'resource' => 'commitment-schedules']
            : ['reason_code' => $exception->reason->value]));
    $exceptions->render(fn (InvalidUserCreation $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422));
    $exceptions->render(fn (UserLifecycleViolation $exception, Request $request) => ApiResponder::error($request, ApiErrorCode::ValidationError, $exception->messageKey, 422,
        details: ['from' => $exception->from->value, 'to' => $exception->to->value]));
    $exceptions->render(ApiExceptionRenderer::render(...));
})->create();
