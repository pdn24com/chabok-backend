<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestContextInputDto;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;
use Modules\Manifest\Application\Dto\ManifestListItemDto;
use Modules\Manifest\Application\Dto\ManifestParcelInputDto;
use Modules\Manifest\Application\Serialization\ManifestParcelOutcomeDocument;
use Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsCommand;
use Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsHandler;
use Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestCommand;
use Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestHandler;
use Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestCommand;
use Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestHandler;
use Modules\Manifest\Application\UseCases\GetManifest\GetManifestCommand;
use Modules\Manifest\Application\UseCases\GetManifest\GetManifestHandler;
use Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsCommand;
use Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsHandler;
use Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesCommand;
use Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesHandler;
use Modules\Manifest\Application\UseCases\ListManifests\ListManifestsCommand;
use Modules\Manifest\Application\UseCases\ListManifests\ListManifestsHandler;
use Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestCommand;
use Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestHandler;
use Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestCommand;
use Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestHandler;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Manifest\Presentation\Http\Resources\ManifestDetailResource;
use Modules\Manifest\Presentation\Http\Resources\ManifestExceptionResource;
use Modules\Manifest\Presentation\Http\Resources\ManifestListResource;

/** Test fixture shorthand for focused use cases; no production facade. */
final readonly class ManifestFixtures
{
    public function __construct(
        private GetManifestContextOptionsHandler $getManifestContextOptions,
        private ListManifestsHandler $listManifests,
        private CreateManifestHandler $createManifest,
        private GetManifestHandler $getManifest,
        private UpdateManifestHandler $updateManifest,
        private ListManifestCandidatesHandler $listManifestCandidates,
        private AddManifestParcelsHandler $addManifestParcels,
        private ValidateManifestHandler $validateManifest,
        private ConfirmManifestHandler $confirmManifest,
        private GetManifestExceptionHandler $getManifestException,
        private ApproveManifestExceptionHandler $approveManifestException,
        private RejectManifestExceptionHandler $rejectManifestException,
        private ResubmitManifestExceptionHandler $resubmitManifestException,
        private ManifestReaderInterface $manifestReader,
    ) {}

    public function contextOptions(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->getManifestContextOptions->handle(new GetManifestContextOptionsCommand($actor, $nodeId));
    }

    public function list(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $filters,
    ): LengthAwarePaginator {
        return $this->listManifests->handle(new ListManifestsCommand($actor, $nodeId, ManifestFiltersDto::fromArray($filters)));
    }

    public function create(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $input,
        string $correlationId,
    ): array {
        return (new ManifestDetailResource($this->createManifest->handle(new CreateManifestCommand($actor, $nodeId, ManifestContextInputDto::fromArray($input), $correlationId))))->resolve();
    }

    public function get(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): array {
        return (new ManifestDetailResource($this->getManifest->handle(new GetManifestCommand($actor, $nodeId, $id))))->resolve();
    }

    public function update(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        array $input,
        string $correlationId,
    ): array {
        return (new ManifestDetailResource($this->updateManifest->handle(new UpdateManifestCommand($actor, $nodeId, $id, ManifestContextInputDto::fromArray($input), $correlationId))))->resolve();
    }

    public function eligible(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        array $filters,
    ): LengthAwarePaginator {
        return $this->listManifestCandidates->handle(new ListManifestCandidatesCommand($actor, $nodeId, $id, ManifestFiltersDto::fromArray($filters)));
    }

    public function add(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        array $input,
        string $correlationId,
    ): array {
        $result = $this->addManifestParcels->handle(new AddManifestParcelsCommand($actor, $nodeId, $id, ManifestParcelInputDto::fromArray($input), $correlationId));

        return ['detail' => (new ManifestDetailResource($result->detail))->resolve(), 'outcomes' => array_map(ManifestParcelOutcomeDocument::serialize(...), $result->outcomes)];
    }

    public function validate(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $correlationId,
    ): array {
        return (new ManifestDetailResource($this->validateManifest->handle(new ValidateManifestCommand($actor, $nodeId, $id, $expected, $correlationId))))->resolve();
    }

    public function confirm(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $correlationId,
        ?string $reasonCode = null,
        ?string $description = null,
    ): array {
        return (new ManifestDetailResource($this->confirmManifest->handle(new ConfirmManifestCommand($actor, $nodeId, $id, $expected, $correlationId, $reasonCode, $description))))->resolve();
    }

    public function exception(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): array {
        return (new ManifestExceptionResource($this->getManifestException->handle(new GetManifestExceptionCommand($actor, $nodeId, $id))))->resolve();
    }

    public function approveException(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        ?string $reason,
        string $correlationId,
    ): array {
        return (new ManifestDetailResource($this->approveManifestException->handle(new ApproveManifestExceptionCommand($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId))))->resolve();
    }

    public function rejectException(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $reason,
        string $correlationId,
    ): array {
        return (new ManifestDetailResource($this->rejectManifestException->handle(new RejectManifestExceptionCommand($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId))))->resolve();
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
    ): array {
        return (new ManifestDetailResource($this->resubmitManifestException->handle(new ResubmitManifestExceptionCommand($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $code, $description, $correlationId))))->resolve();
    }

    public function listItem(ManifestRecord|ManifestListItemDto $row): array
    {
        return (new ManifestListResource($this->manifestReader->listItem($row)))->resolve();
    }
}
