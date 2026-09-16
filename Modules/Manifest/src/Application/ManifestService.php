<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestService
{
    public function __construct(
        private \Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsHandler $getManifestContextOptions,
        private \Modules\Manifest\Application\UseCases\ListManifests\ListManifestsHandler $listManifests,
        private \Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestHandler $createManifest,
        private \Modules\Manifest\Application\UseCases\GetManifest\GetManifestHandler $getManifest,
        private \Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestHandler $updateManifest,
        private \Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesHandler $listManifestCandidates,
        private \Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsHandler $addManifestParcels,
        private \Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestHandler $validateManifest,
        private \Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestHandler $confirmManifest,
        private \Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionHandler $getManifestException,
        private \Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionHandler $approveManifestException,
        private \Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionHandler $rejectManifestException,
        private \Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionHandler $resubmitManifestException,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
    )
    {
    }

    public function contextOptions(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->getManifestContextOptions->handle(new \Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsCommand($actor, $nodeId))->data;
    }

    public function list(AuthenticatedPrincipal $actor, string $nodeId, array $filters): Page
    {
        return $this->listManifests->handle(new \Modules\Manifest\Application\UseCases\ListManifests\ListManifestsCommand($actor, $nodeId, $filters))->data;
    }

    public function create(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        return $this->createManifest->handle(new \Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestCommand($actor, $nodeId, $input, $correlationId))->data;
    }

    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->getManifest->handle(new \Modules\Manifest\Application\UseCases\GetManifest\GetManifestCommand($actor, $nodeId, $id))->data;
    }

    public function update(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $input, string $correlationId): array
    {
        return $this->updateManifest->handle(new \Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestCommand($actor, $nodeId, $id, $input, $correlationId))->data;
    }

    public function eligible(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $filters): Page
    {
        return $this->listManifestCandidates->handle(new \Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesCommand($actor, $nodeId, $id, $filters))->data;
    }

    public function add(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $input, string $correlationId): array
    {
        return $this->addManifestParcels->handle(new \Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsCommand($actor, $nodeId, $id, $input, $correlationId))->data;
    }

    public function validate(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId): array
    {
        return $this->validateManifest->handle(new \Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestCommand($actor, $nodeId, $id, $expected, $correlationId))->data;
    }

    public function confirm(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $correlationId,
        ?string $reasonCode = null,
        ?string $description = null,
    ): array
    {
        return $this->confirmManifest->handle(new \Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestCommand($actor, $nodeId, $id, $expected, $correlationId, $reasonCode, $description))->data;
    }

    public function exception(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->getManifestException->handle(new \Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionCommand($actor, $nodeId, $id))->data;
    }

    public function approveException(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        ?string $reason,
        string $correlationId,
    ): array
    {
        return $this->approveManifestException->handle(new \Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionCommand($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId))->data;
    }

    public function rejectException(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $reason,
        string $correlationId,
    ): array
    {
        return $this->rejectManifestException->handle(new \Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionCommand($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId))->data;
    }

    public function resubmitException(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $code,
        string $description,
        string $correlationId,
    ): array
    {
        return $this->resubmitManifestException->handle(new \Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionCommand($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $code, $description, $correlationId))->data;
    }

    public function listItem(object|array $row): array
    {
        return $this->manifestReader->listItem($row);
    }
}
