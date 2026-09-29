<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Dto\PricingChargeTypeDto;
use Modules\Pricing\Application\Dto\PricingFiltersDto;
use Modules\Pricing\Application\Dto\PricingZoneSetDto;
use Modules\Pricing\Application\Dto\PricingZoneVersionSearchDto;
use Modules\Pricing\Application\Dto\TariffDraftDto;
use Modules\Pricing\Application\Mappers\QuoteInputMapper;
use Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteCommand;
use Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteHandler;
use Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteCommand;
use Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteHandler;
use Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftCommand;
use Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftHandler;
use Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeCommand;
use Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeHandler;
use Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetCommand;
use Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetHandler;
use Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffCommand;
use Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffHandler;
use Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryCommand;
use Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryHandler;
use Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsCommand;
use Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsHandler;
use Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesCommand;
use Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesHandler;
use Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsCommand;
use Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsHandler;
use Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesCommand;
use Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesHandler;
use Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesCommand;
use Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesHandler;
use Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsCommand;
use Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsHandler;
use Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteCommand;
use Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteHandler;
use Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftCommand;
use Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftHandler;
use Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionCommand;
use Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionHandler;
use Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionCommand;
use Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionHandler;
use Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionCommand;
use Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionHandler;
use Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetCommand;
use Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetHandler;
use Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffCommand;
use Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffHandler;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Enums\PricingTransition;
use Modules\Pricing\Domain\ValueObjects\PricingValidationResult;
use Modules\Pricing\Presentation\Http\Resources\PricingHistoryResource;
use Modules\Pricing\Presentation\Http\Resources\PricingQuoteResource;
use Modules\Pricing\Presentation\Http\Resources\PricingSimulationResource;
use Modules\Pricing\Presentation\Http\Resources\PricingSnapshotResource;
use Modules\Pricing\Presentation\Http\Resources\PricingVersionResource;
use Modules\Pricing\Presentation\Http\Resources\PricingZoneVersionReferenceResource;

/** Test fixture conveniences; response assertions use the same resources as HTTP. */
final readonly class PricingFixtures
{
    public function __construct(
        private ListTariffsHandler $listTariffs,
        private ListPricingZoneSetsHandler $listPricingZoneSets,
        private ListPricingZoneVersionReferencesHandler $listPricingZoneVersionReferences,
        private ListServiceTariffReferencesHandler $listServiceTariffReferences,
        private ListPricingChargeTypesHandler $listPricingChargeTypes,
        private ListPricingAuditEventsHandler $listPricingAuditEvents,
        private GetPricingHistoryHandler $getPricingHistory,
        private ClonePricingDraftHandler $clonePricingDraft,
        private CreatePricingChargeTypeHandler $createPricingChargeType,
        private CreatePricingZoneSetHandler $createPricingZoneSet,
        private UpdatePricingZoneVersionHandler $updatePricingZoneVersion,
        private CreateTariffHandler $createTariff,
        private UpdateTariffVersionHandler $updateTariffVersion,
        private ValidateTariffHandler $validateTariff,
        private ValidatePricingZoneSetHandler $validatePricingZoneSet,
        private TransitionPricingVersionHandler $transitionPricingVersion,
        private CalculatePricingQuoteHandler $calculatePricingQuote,
        private SimulateTariffDraftHandler $simulateTariffDraft,
        private PricingReaderInterface $pricingReader,
        private RejectPricingQuoteHandler $rejectPricingQuote,
        private AcceptPricingQuoteHandler $acceptPricingQuote,
    ) {}

    public function listTariffs(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listTariffs->handle(new ListTariffsCommand($actor, PricingFiltersDto::fromInput($filters)));
    }

    public function listZoneSets(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listPricingZoneSets->handle(new ListPricingZoneSetsCommand($actor, PricingFiltersDto::fromInput($filters)));
    }

    public function listZoneSetVersionReferences(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $search = new PricingZoneVersionSearchDto(search: $filters['search'] ?? '', includeVersionId: $filters['include_version_id'] ?? null, page: (int) ($filters['page'] ?? 1), pageSize: (int) ($filters['page_size'] ?? 50));

        return $this->listPricingZoneVersionReferences->handle(new ListPricingZoneVersionReferencesCommand($actor, $search))
            ->through(fn ($version) => (new PricingZoneVersionReferenceResource($version))->resolve());
    }

    public function serviceTariffReferences(AuthenticatedPrincipal $actor): array
    {
        return $this->listServiceTariffReferences->handle(new ListServiceTariffReferencesCommand($actor));
    }

    public function listChargeTypes(AuthenticatedPrincipal $actor): array
    {
        return $this->listPricingChargeTypes->handle(new ListPricingChargeTypesCommand($actor));
    }

    public function auditEvents(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listPricingAuditEvents->handle(new ListPricingAuditEventsCommand($actor, PricingFiltersDto::fromInput($filters)));
    }

    public function history(
        AuthenticatedPrincipal $actor,
        string $kind,
        string $identityId,
    ): array {
        return PricingHistoryResource::collection($this->getPricingHistory->handle(new GetPricingHistoryCommand($actor, PricingResource::fromPath($kind), $identityId)))->resolve();
    }

    public function cloneDraft(
        AuthenticatedPrincipal $actor,
        string $kind,
        string $identityId,
        string $correlationId,
    ): array {
        return (new PricingVersionResource($this->clonePricingDraft->handle(new ClonePricingDraftCommand($actor, PricingResource::fromPath($kind), $identityId, $correlationId))))->resolve();
    }

    public function createChargeType(AuthenticatedPrincipal $actor, array $input): array
    {
        return $this->createPricingChargeType->handle(new CreatePricingChargeTypeCommand($actor, PricingChargeTypeDto::fromInput($input)));
    }

    public function createZoneSet(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new PricingVersionResource($this->createPricingZoneSet->handle(new CreatePricingZoneSetCommand($actor, PricingZoneSetDto::fromInput($input), $correlationId))))->resolve();
    }

    public function updateZoneVersion(
        AuthenticatedPrincipal $actor,
        string $versionId,
        array $input,
    ): array {
        return (new PricingVersionResource($this->updatePricingZoneVersion->handle(new UpdatePricingZoneVersionCommand($actor, $versionId, PricingZoneSetDto::fromInput($input)))))->resolve();
    }

    public function createTariff(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new PricingVersionResource($this->createTariff->handle(new CreateTariffCommand($actor, TariffDraftDto::fromInput($input), $correlationId))))->resolve();
    }

    public function updateTariffVersion(
        AuthenticatedPrincipal $actor,
        string $versionId,
        array $input,
    ): array {
        return (new PricingVersionResource($this->updateTariffVersion->handle(new UpdateTariffVersionCommand($actor, $versionId, TariffDraftDto::fromInput($input)))))->resolve();
    }

    public function validateTariff(AuthenticatedPrincipal $actor, string $versionId): PricingValidationResult
    {
        return $this->validateTariff->handle(new ValidateTariffCommand($actor, $versionId));
    }

    public function validateZoneSet(AuthenticatedPrincipal $actor, string $versionId): PricingValidationResult
    {
        return $this->validatePricingZoneSet->handle(new ValidatePricingZoneSetCommand($actor, $versionId));
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $kind,
        string $versionId,
        string $action,
        string $correlationId,
    ): array {
        return (new PricingVersionResource($this->transitionPricingVersion->handle(new TransitionPricingVersionCommand($actor, PricingResource::fromPath($kind), $versionId, PricingTransition::fromInput($action), $correlationId))))->resolve();
    }

    public function calculateQuote(
        AuthenticatedPrincipal $actor,
        array $input,
        string $idempotencyKey,
    ): array {
        return (new PricingQuoteResource($this->calculatePricingQuote->handle(new CalculatePricingQuoteCommand($actor, QuoteInputMapper::quote($input), $idempotencyKey))))->resolve();
    }

    public function simulateDraft(
        AuthenticatedPrincipal $actor,
        string $versionId,
        array $input,
        int $expectedVersion,
    ): array {
        return (new PricingSimulationResource($this->simulateTariffDraft->handle(new SimulateTariffDraftCommand($actor, $versionId, QuoteInputMapper::quote($input), $expectedVersion))))->resolve();
    }

    public function quoteDetail(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        return (new PricingQuoteResource($this->pricingReader->quoteDetail($actor, $quoteId)))->resolve();
    }

    public function rejectQuote(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        return (new PricingQuoteResource($this->rejectPricingQuote->handle(new RejectPricingQuoteCommand($actor, $quoteId))))->resolve();
    }

    public function acceptQuote(
        AuthenticatedPrincipal $actor,
        string $quoteId,
        string $objectType,
        string $objectId,
        string $inputFingerprint,
        string $idempotencyKey,
    ): array {
        return (new PricingSnapshotResource($this->acceptPricingQuote->handle(new AcceptPricingQuoteCommand($actor, $quoteId, $objectType, $objectId, $inputFingerprint, $idempotencyKey))))->resolve();
    }

    public function tariffVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return (new PricingVersionResource($this->pricingReader->tariffVersion($actor, $versionId)))->resolve();
    }

    public function zoneVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return (new PricingVersionResource($this->pricingReader->zoneVersion($actor, $versionId)))->resolve();
    }
}
