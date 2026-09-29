<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ExceptionCaseStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestWorkflowGuardInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Domain\Policies\ManifestPolicy;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;

final readonly class ManifestWorkflowGuard implements ManifestWorkflowGuardInterface
{
    public function __construct(
        private ManifestPolicy $manifestPolicy,
        private ManifestExceptionAccessInterface $manifestExceptionAccess,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function lockedManifest(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
    ): ManifestRecord {
        $m = $this->manifestRepository->lockAtNode($actor->hqId, $node, $id);
        if ($m === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $m;
    }

    public function manifestVersion(ManifestRecord $m, int $expected): void
    {
        $this->manifestPolicy->assertVersion((int) $m->version, $expected);
    }

    public function lockedPendingCase(AuthenticatedPrincipal $actor, string $manifest): OperationalExceptionCaseRecord
    {
        $c = $this->manifestExceptionAccess->lockLatestException($actor->hqId, $manifest);
        if ($c === null) {
            throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'manifest.pending_exception_is_required');
        }
        if (ExceptionCaseStatus::tryFrom((string) $c->case_status)?->isDecided() !== false) {
            throw new ApiException(ApiErrorCode::ExceptionAlreadyDecided, 422, 'manifest.exception_is_already_decided');
        }

        return $c;
    }

    public function exceptionVersion(OperationalExceptionCaseRecord $case, int $expected): void
    {
        if ((int) $case->version !== $expected) {
            throw new ApiException(ApiErrorCode::ExceptionVersionConflict, 409, 'manifest.exception_version_is_stale', details: ['current_version' => (int) $case->version]);
        }
    }

    public function differentReviewer(AuthenticatedPrincipal $actor, OperationalExceptionCaseRecord $case): void
    {
        if ((string) $case->submitted_by === $actor->userId) {
            throw new ApiException(ApiErrorCode::ExceptionReviewerConflict, 422, 'manifest.submitter_cannot_review_same_exception');
        }
    }
}
