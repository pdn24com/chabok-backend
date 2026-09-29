<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Mappers\CatalogDraftInput;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityCommand;
use Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityHandler;
use Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryCommand;
use Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryHandler;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsCommand;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsHandler;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesCommand;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesHandler;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsCommand;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsHandler;
use Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentCommand;
use Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentHandler;
use Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsCommand;
use Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsHandler;
use Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionCommand;
use Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionHandler;
use Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionHandler;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogValidationResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogVersionResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\OfferingCommitmentResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\ResolvedServiceOfferingResource;

final readonly class ServiceCatalogFixtures
{
    public function __construct(
        private ListCatalogIdentitiesHandler $listCatalogIdentities,
        private ListPublishedCatalogVersionsHandler $listPublishedCatalogVersions,
        private ListCatalogAuditEventsHandler $listCatalogAuditEvents,
        private CreateCatalogIdentityHandler $createCatalogIdentity,
        private CloneCatalogDraftHandler $cloneCatalogDraft,
        private UpdateCatalogDraftHandler $updateCatalogDraft,
        private ValidateCatalogDraftHandler $validateCatalogDraft,
        private TransitionCatalogVersionHandler $transitionCatalogVersion,
        private GetCatalogHistoryHandler $getCatalogHistory,
        private ResolveServiceOfferingsHandler $resolveServiceOfferings,
        private ValidateServiceSelectionHandler $validateServiceSelection,
        private PreviewServiceCommitmentHandler $previewServiceCommitment,
        private CatalogReaderInterface $catalogReader,
    ) {}

    public function listIdentities(
        AuthenticatedPrincipal $actor,
        string $resource,
        array $filters,
    ): LengthAwarePaginator {
        return $this->listCatalogIdentities->handle(new ListCatalogIdentitiesCommand($actor, $resource, $filters));
    }

    public function listPublishedVersions(
        AuthenticatedPrincipal $actor,
        string $resource,
        array $filters,
    ): LengthAwarePaginator {
        return $this->listPublishedCatalogVersions->handle(new ListPublishedCatalogVersionsCommand($actor, $resource, $filters));
    }

    public function auditEvents(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listCatalogAuditEvents->handle(new ListCatalogAuditEventsCommand($actor, $filters));
    }

    public function createIdentity(
        AuthenticatedPrincipal $actor,
        string $resource,
        array $input,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->createCatalogIdentity->handle(new CreateCatalogIdentityCommand($actor, $resource, CatalogDraftInput::draft($input), $correlationId))))->resolve();
    }

    public function cloneDraft(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $identityIdValue,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->cloneCatalogDraft->handle(new CloneCatalogDraftCommand($actor, $resource, $identityIdValue, $correlationId))))->resolve();
    }

    public function updateDraft(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
        int $expectedVersion,
        array $input,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->updateCatalogDraft->handle(new UpdateCatalogDraftCommand($actor, $resource, $versionIdValue, $expectedVersion, CatalogDraftInput::draft($input), $correlationId))))->resolve();
    }

    public function validateDraft(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
        bool $automatic = false,
    ): array {
        return (new CatalogValidationResource($this->validateCatalogDraft->handle(new ValidateCatalogDraftCommand($actor, $resource, $versionIdValue, $automatic))))->resolve();
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
        string $action,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->transitionCatalogVersion->handle(new TransitionCatalogVersionCommand($actor, $resource, $versionIdValue, $action, $correlationId))))->resolve();
    }

    public function history(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $identityIdValue,
    ): array {
        return $this->getCatalogHistory->handle(new GetCatalogHistoryCommand($actor, $resource, $identityIdValue));
    }

    public function resolve(AuthenticatedPrincipal $actor, array $context): array
    {
        return ResolvedServiceOfferingResource::collection($this->resolveServiceOfferings->handle(new ResolveServiceOfferingsCommand($actor, OfferingSelectionInput::fromArray($context))))->resolve();
    }

    public function validateSelection(
        AuthenticatedPrincipal $actor,
        string $offeringId,
        ?string $versionId,
        array $context,
        bool $requireCommitmentSelection = true,
    ): array {
        return (new ResolvedServiceOfferingResource($this->validateServiceSelection->handle(new ValidateServiceSelectionCommand($actor, $offeringId, $versionId, OfferingSelectionInput::fromArray($context), $requireCommitmentSelection))))->resolve();
    }

    public function commitmentPreview(
        AuthenticatedPrincipal $actor,
        string $offeringId,
        array $context,
    ): array {
        return (new OfferingCommitmentResource($this->previewServiceCommitment->handle(new PreviewServiceCommitmentCommand($actor, $offeringId, OfferingSelectionInput::fromArray($context)))))->resolve();
    }

    public function versionDetail(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
    ): array {
        return (new CatalogVersionResource($this->catalogReader->versionDetail($actor, $resource, $versionIdValue)))->resolve();
    }
}
