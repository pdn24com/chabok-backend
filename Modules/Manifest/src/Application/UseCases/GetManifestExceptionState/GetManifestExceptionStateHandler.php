<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestExceptionState;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetManifestExceptionStateHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestOrchestrationAccess $manifestOrchestrationAccess,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Operations\Application\Contracts\ManifestExceptionAccess $exceptionState,
        private \Modules\Manifest\Application\Services\ManifestWorkflowProjection $manifestWorkflowProjection,
    )
    {
    }

    public function handle(GetManifestExceptionStateCommand $command): GetManifestExceptionStateResult
    {
        return new GetManifestExceptionStateResult($this->execute($command->actor, $command->node, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $node, string $id): array
    {
        $this->manifestOrchestrationAccess->access($actor, $node, 'manifest.view');
        $m = $this->workflow->manifest($actor->hqId, $node, $id);
        if ($m === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $cases = $this->exceptionState->exceptionAttempts($actor->hqId, $id);
        $mapped = array_map(fn(object $c): array => $this->manifestWorkflowProjection->caseResource($c), $cases);
        return [
            'manifest_id' => $id,
            'manifest_state' => (string) $m->state,
            'manifest_version' => (int) $m->version,
            'current_exception' => $mapped[0] ?? null,
            'previous_attempts' => array_slice($mapped, 1),
        ];
    }
}
