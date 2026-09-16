<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestWorkflowGuard
{
    public function __construct(
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Operations\Application\Contracts\ManifestExceptionAccess $exceptionState,
    )
    {
    }

    public function lockedManifest(AuthenticatedPrincipal $actor, string $node, string $id): object
    {
        $m = $this->workflow->lockManifest($actor->hqId, $node, $id);
        if ($m === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $m;
    }

    public function manifestVersion(object $m, int $expected): void
    {
        if ((int) $m->version !== $expected) {
            throw new ApiException(ApiErrorCode::ManifestVersionConflict, 409, 'The Manifest version is stale.', details: ['current_version' => (int) $m->version]);
        }
    }

    public function lockedPendingCase(AuthenticatedPrincipal $actor, string $manifest): object
    {
        $c = $this->exceptionState->OrchestrationLockLatestException($actor->hqId, $manifest);
        if ($c === null) {
            throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'A pending Exception is required.');
        }
        if ((string) $c->case_status !== 'PENDING') {
            throw new ApiException(ApiErrorCode::ExceptionAlreadyDecided, 422, 'The Exception is already decided.');
        }
        return $c;
    }

    public function exceptionVersion(object $case, int $expected): void
    {
        if ((int) $case->version !== $expected) {
            throw new ApiException(ApiErrorCode::ExceptionVersionConflict, 409, 'The Exception version is stale.', details: ['current_version' => (int) $case->version]);
        }
    }

    public function differentReviewer(AuthenticatedPrincipal $actor, object $case): void
    {
        if ((string) $case->submitted_by === $actor->userId) {
            throw new ApiException(ApiErrorCode::ExceptionReviewerConflict, 422, 'The submitter cannot review the same Exception.');
        }
    }
}
