<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CalculateConsignmentQuote;

use Carbon\CarbonImmutable;
use Modules\Consignment\Application\Contracts\ConsignmentQuoteAccessInterface;
use Modules\Consignment\Application\Contracts\ConsignmentQuoteInputInterface;
use Modules\Consignment\Application\Contracts\PricingQuoteProviderInterface;
use Modules\Consignment\Application\Contracts\QuoteBundleStoreInterface;
use Modules\Consignment\Application\Contracts\QuoteSettingsInterface;
use Modules\Consignment\Application\Dto\ConsignmentPricingRequestDto;
use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Consignment\Domain\Policies\ConsignmentPolicy;
use Modules\Consignment\Domain\Support\InputFingerprint;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\IdentifierGeneratorInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CalculateConsignmentQuoteHandler
{
    public function __construct(
        private ConsignmentQuoteAccessInterface $consignmentQuoteAccess,
        private ConsignmentQuoteInputInterface $consignmentQuoteInput,
        private ConsignmentPolicy $consignmentPolicy,
        private IdentifierGeneratorInterface $identifierGenerator,
        private PricingQuoteProviderInterface $pricingQuoteProvider,
        private ClockInterface $clock,
        private QuoteSettingsInterface $quoteSettings,
        private QuoteBundleStoreInterface $quoteBundleStore,
    ) {}

    public function handle(CalculateConsignmentQuoteCommand $command): ConsignmentQuoteBundleDto
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $purpose = $command->purpose;
        $input = $command->input;
        $consignmentId = $command->consignmentId;
        $expectedVersion = $command->expectedVersion;
        $this->consignmentQuoteAccess->assertAccess($actor, $nodeId, $purpose === 'CREATE' ? 'consignment.create' : 'consignment.edit');
        $input = $this->consignmentQuoteInput->normalized($input);
        $this->consignmentPolicy->assertCommercialConsistency($input);
        if ($purpose === 'CREATE') {
            $this->consignmentPolicy->assertPilotCreate($input);
        }
        if ($purpose === 'EDIT' && ($consignmentId === null || $expectedVersion === null)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'consignment.edit_pricing_requires_consignment_expected_version');
        }
        $fingerprint = InputFingerprint::of(ConsignmentDraftDocument::draft($input));
        $providerRequest = new ConsignmentPricingRequestDto($actor, $nodeId, $input, $this->identifierGenerator->token());
        $options = $this->pricingQuoteProvider->calculate($providerRequest);
        foreach ($options as $option) {
            $option->optionId = $this->identifierGenerator->token();
            $option->resolvedInputFingerprint = InputFingerprint::of([...ConsignmentDraftDocument::draft($input), '_resolved_commitment' => $option->commitment]);
            $option->presentFields = array_values(array_unique([...$option->presentFields, 'option_id', '_resolved_input_fingerprint']));
        }
        $now = CarbonImmutable::instance($this->clock->now())->utc();
        $ttl = $this->quoteSettings->quoteTtlSeconds();
        $quoteId = $this->identifierGenerator->token();
        $bundle = new ConsignmentQuoteBundleDto(
            $quoteId, 1, $actor->hqId, $nodeId, $purpose, $fingerprint, $consignmentId, $expectedVersion,
            $now->toISOString(), $now->addSeconds($ttl)->toISOString(), $options,
        );
        $this->quoteBundleStore->put($quoteId, $bundle, $ttl);

        return $bundle;
    }
}
