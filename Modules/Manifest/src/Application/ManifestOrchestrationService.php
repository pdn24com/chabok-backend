<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestOrchestrationService
{
    public function __construct(
        private \Modules\Manifest\Application\UseCases\ConfirmManifestOperation\ConfirmManifestOperationHandler $confirmManifestOperation,
        private \Modules\Manifest\Application\UseCases\ApproveManifestExceptionOperation\ApproveManifestExceptionOperationHandler $approveManifestExceptionOperation,
        private \Modules\Manifest\Application\UseCases\RejectManifestExceptionOperation\RejectManifestExceptionOperationHandler $rejectManifestExceptionOperation,
        private \Modules\Manifest\Application\UseCases\ResubmitManifestExceptionOperation\ResubmitManifestExceptionOperationHandler $resubmitManifestExceptionOperation,
        private \Modules\Manifest\Application\UseCases\GetManifestExceptionState\GetManifestExceptionStateHandler $getManifestExceptionState,
        private \Modules\Manifest\Application\Services\ManifestWorkflowProjection $manifestWorkflowProjection,
    )
    {
    }

    public function confirm(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $expected,
        string $correlationId,
        ?string $reasonCode = null,
        ?string $description = null,
    ): void
    {
        $this->confirmManifestOperation->handle(new \Modules\Manifest\Application\UseCases\ConfirmManifestOperation\ConfirmManifestOperationCommand($actor, $node, $id, $expected, $correlationId, $reasonCode, $description));
    }

    public function approve(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        ?string $reason,
        string $correlationId,
    ): void
    {
        $this->approveManifestExceptionOperation->handle(new \Modules\Manifest\Application\UseCases\ApproveManifestExceptionOperation\ApproveManifestExceptionOperationCommand($actor, $node, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId));
    }

    public function reject(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $reason,
        string $correlationId,
    ): void
    {
        $this->rejectManifestExceptionOperation->handle(new \Modules\Manifest\Application\UseCases\RejectManifestExceptionOperation\RejectManifestExceptionOperationCommand($actor, $node, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId));
    }

    public function resubmit(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $code,
        string $description,
        string $correlationId,
    ): void
    {
        $this->resubmitManifestExceptionOperation->handle(new \Modules\Manifest\Application\UseCases\ResubmitManifestExceptionOperation\ResubmitManifestExceptionOperationCommand($actor, $node, $id, $manifestVersion, $exceptionVersion, $code, $description, $correlationId));
    }

    public function exceptionState(AuthenticatedPrincipal $actor, string $node, string $id): array
    {
        return $this->getManifestExceptionState->handle(new \Modules\Manifest\Application\UseCases\GetManifestExceptionState\GetManifestExceptionStateCommand($actor, $node, $id))->data;
    }

    public function custodyEvents(string $hq, string $manifest): array
    {
        return $this->manifestWorkflowProjection->custodyEvents($hq, $manifest);
    }

    public function movementEvidence(object $m): ?array
    {
        return $this->manifestWorkflowProjection->movementEvidence($m);
    }
}
