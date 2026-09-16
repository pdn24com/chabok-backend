<?php

declare(strict_types=1);

namespace Modules\Pricing\Application;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PricingService
{
    public function __construct(
        private \Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook\PrepareMatrixWorkbookHandler $prepareMatrixWorkbook,
        private \Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsHandler $listTariffs,
        private \Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsHandler $listPricingZoneSets,
        private \Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesHandler $listPricingZoneVersionReferences,
        private \Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesHandler $listServiceTariffReferences,
        private \Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesHandler $listPricingChargeTypes,
        private \Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsHandler $listPricingAuditEvents,
        private \Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryHandler $getPricingHistory,
        private \Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftHandler $clonePricingDraft,
        private \Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeHandler $createPricingChargeType,
        private \Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetHandler $createPricingZoneSet,
        private \Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionHandler $updatePricingZoneVersion,
        private \Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffHandler $createTariff,
        private \Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionHandler $updateTariffVersion,
        private \Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffHandler $validateTariff,
        private \Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetHandler $validatePricingZoneSet,
        private \Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionHandler $transitionPricingVersion,
        private \Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteHandler $calculatePricingQuote,
        private \Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftHandler $simulateTariffDraft,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteHandler $rejectPricingQuote,
        private \Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteHandler $acceptPricingQuote,
    )
    {
    }

    public function matrixWorkbook(AuthenticatedPrincipal $actor, array $input, bool $sample): array
    {
        return $this->prepareMatrixWorkbook->handle(new \Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook\PrepareMatrixWorkbookCommand($actor, $input, $sample))->data;
    }

    public function listTariffs(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listTariffs->handle(new \Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsCommand($actor, $filters))->data;
    }

    public function listZoneSets(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listPricingZoneSets->handle(new \Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsCommand($actor, $filters))->data;
    }

    public function listZoneSetVersionReferences(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listPricingZoneVersionReferences->handle(new \Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesCommand($actor, $filters))->data;
    }

    public function serviceTariffReferences(AuthenticatedPrincipal $actor): array
    {
        return $this->listServiceTariffReferences->handle(new \Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesCommand($actor))->data;
    }

    public function listChargeTypes(AuthenticatedPrincipal $actor): array
    {
        return $this->listPricingChargeTypes->handle(new \Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesCommand($actor))->data;
    }

    public function auditEvents(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listPricingAuditEvents->handle(new \Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsCommand($actor, $filters))->data;
    }

    public function history(AuthenticatedPrincipal $actor, string $kind, string $identityId): array
    {
        return $this->getPricingHistory->handle(new \Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryCommand($actor, $kind, $identityId))->data;
    }

    public function cloneDraft(AuthenticatedPrincipal $actor, string $kind, string $identityId, string $correlationId): array
    {
        return $this->clonePricingDraft->handle(new \Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftCommand($actor, $kind, $identityId, $correlationId))->data;
    }

    public function createChargeType(AuthenticatedPrincipal $actor, array $input): array
    {
        return $this->createPricingChargeType->handle(new \Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeCommand($actor, $input))->data;
    }

    public function createZoneSet(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createPricingZoneSet->handle(new \Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetCommand($actor, $input, $correlationId))->data;
    }

    public function updateZoneVersion(AuthenticatedPrincipal $actor, string $versionId, array $input): array
    {
        return $this->updatePricingZoneVersion->handle(new \Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionCommand($actor, $versionId, $input))->data;
    }

    public function createTariff(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createTariff->handle(new \Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffCommand($actor, $input, $correlationId))->data;
    }

    public function updateTariffVersion(AuthenticatedPrincipal $actor, string $versionId, array $input): array
    {
        return $this->updateTariffVersion->handle(new \Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionCommand($actor, $versionId, $input))->data;
    }

    public function validateTariff(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return $this->validateTariff->handle(new \Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffCommand($actor, $versionId))->data;
    }

    public function validateZoneSet(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return $this->validatePricingZoneSet->handle(new \Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetCommand($actor, $versionId))->data;
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $kind,
        string $versionId,
        string $action,
        string $correlationId,
    ): array
    {
        return $this->transitionPricingVersion->handle(new \Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionCommand($actor, $kind, $versionId, $action, $correlationId))->data;
    }

    public function calculateQuote(AuthenticatedPrincipal $actor, array $input, string $idempotencyKey): array
    {
        return $this->calculatePricingQuote->handle(new \Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteCommand($actor, $input, $idempotencyKey))->data;
    }

    public function simulateDraft(AuthenticatedPrincipal $actor, string $versionId, array $input, int $expectedVersion): array
    {
        return $this->simulateTariffDraft->handle(new \Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftCommand($actor, $versionId, $input, $expectedVersion))->data;
    }

    public function quoteDetail(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        return $this->pricingReader->quoteDetail($actor, $quoteId);
    }

    public function rejectQuote(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        return $this->rejectPricingQuote->handle(new \Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteCommand($actor, $quoteId))->data;
    }

    public function acceptQuote(
        AuthenticatedPrincipal $actor,
        string $quoteId,
        string $objectType,
        string $objectId,
        string $inputFingerprint,
        string $idempotencyKey,
    ): array
    {
        return $this->acceptPricingQuote->handle(new \Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteCommand($actor, $quoteId, $objectType, $objectId, $inputFingerprint, $idempotencyKey))->data;
    }

    public function tariffVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return $this->pricingReader->tariffVersion($actor, $versionId);
    }

    public function zoneVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return $this->pricingReader->zoneVersion($actor, $versionId);
    }
}
