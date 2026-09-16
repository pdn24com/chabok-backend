<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ServiceCatalogService implements \Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolver
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesHandler $listCatalogIdentities,
        private \Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsHandler $listPublishedCatalogVersions,
        private \Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsHandler $listCatalogAuditEvents,
        private \Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityHandler $createCatalogIdentity,
        private \Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftHandler $cloneCatalogDraft,
        private \Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftHandler $updateCatalogDraft,
        private \Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftHandler $validateCatalogDraft,
        private \Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionHandler $transitionCatalogVersion,
        private \Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryHandler $getCatalogHistory,
        private \Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsHandler $resolveServiceOfferings,
        private \Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionHandler $validateServiceSelection,
        private \Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentHandler $previewServiceCommitment,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
    )
    {
    }

    public function listIdentities(AuthenticatedPrincipal $actor, string $resource, array $filters): Page
    {
        return $this->listCatalogIdentities->handle(new \Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesCommand($actor, $resource, $filters))->data;
    }

    public function listPublishedVersions(AuthenticatedPrincipal $actor, string $resource, array $filters): Page
    {
        return $this->listPublishedCatalogVersions->handle(new \Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsCommand($actor, $resource, $filters))->data;
    }

    public function auditEvents(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listCatalogAuditEvents->handle(new \Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsCommand($actor, $filters))->data;
    }

    public function createIdentity(AuthenticatedPrincipal $actor, string $resource, array $input, string $correlationId): array
    {
        return $this->createCatalogIdentity->handle(new \Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityCommand($actor, $resource, $input, $correlationId))->data;
    }

    public function cloneDraft(AuthenticatedPrincipal $actor, string $resource, string $identityIdValue, string $correlationId): array
    {
        return $this->cloneCatalogDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftCommand($actor, $resource, $identityIdValue, $correlationId))->data;
    }

    public function updateDraft(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
        int $expectedVersion,
        array $input,
        string $correlationId,
    ): array
    {
        return $this->updateCatalogDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftCommand($actor, $resource, $versionIdValue, $expectedVersion, $input, $correlationId))->data;
    }

    public function validateDraft(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue, bool $automatic = false): array
    {
        return $this->validateCatalogDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftCommand($actor, $resource, $versionIdValue, $automatic))->data;
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
        string $action,
        string $correlationId,
    ): array
    {
        return $this->transitionCatalogVersion->handle(new \Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionCommand($actor, $resource, $versionIdValue, $action, $correlationId))->data;
    }

    public function history(AuthenticatedPrincipal $actor, string $resource, string $identityIdValue): array
    {
        return $this->getCatalogHistory->handle(new \Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryCommand($actor, $resource, $identityIdValue))->data;
    }

    public function resolve(AuthenticatedPrincipal $actor, array $context): array
    {
        return $this->resolveServiceOfferings->handle(new \Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsCommand($actor, $context))->data;
    }

    public function validateSelection(
        AuthenticatedPrincipal $actor,
        string $offeringId,
        ?string $versionId,
        array $context,
        bool $requireCommitmentSelection = true,
    ): array
    {
        return $this->validateServiceSelection->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionCommand($actor, $offeringId, $versionId, $context, $requireCommitmentSelection))->data;
    }

    public function commitmentPreview(AuthenticatedPrincipal $actor, string $offeringId, array $context): array
    {
        return $this->previewServiceCommitment->handle(new \Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentCommand($actor, $offeringId, $context))->data;
    }

    public function versionDetail(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue): array
    {
        return $this->catalogReader->versionDetail($actor, $resource, $versionIdValue);
    }
}
