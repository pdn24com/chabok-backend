<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\AcceptConsignmentQuote;

use Carbon\CarbonImmutable;
use Modules\Consignment\Application\Contracts\ConsignmentQuoteInputInterface;
use Modules\Consignment\Application\Contracts\QuoteBundleStoreInterface;
use Modules\Consignment\Application\Dto\AcceptedConsignmentQuoteDto;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Consignment\Domain\Policies\ConsignmentPolicy;
use Modules\Consignment\Domain\Support\InputFingerprint;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class AcceptConsignmentQuoteHandler
{
    public function __construct(
        private ConsignmentQuoteInputInterface $consignmentQuoteInput,
        private ConsignmentPolicy $consignmentPolicy,
        private QuoteBundleStoreInterface $quoteBundleStore,
        private ClockInterface $clock,
    ) {}

    public function handle(AcceptConsignmentQuoteCommand $command): AcceptedConsignmentQuoteDto
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $purpose = $command->purpose;
        $input = $command->input;
        $acceptedQuote = $command->acceptedQuote;
        $consignmentId = $command->consignmentId;
        $expectedVersion = $command->expectedVersion;
        $input = $this->consignmentQuoteInput->normalized($input);
        $this->consignmentPolicy->assertCommercialConsistency($input);
        if ($purpose === 'CREATE') {
            $this->consignmentPolicy->assertPilotCreate($input);
        }
        $quoteId = (string) ($acceptedQuote->quoteId ?? '');
        $bundle = $this->quoteBundleStore->get($quoteId);
        if ($bundle === null || CarbonImmutable::parse((string) $bundle->expiresAt) < $this->clock->now()) {
            throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'consignment.pricing_quote_has_expired');
        }
        $matches = ($acceptedQuote->quoteVersion ?? null) === $bundle->quoteVersion && $bundle->hqId === $actor->hqId && $bundle->nodeId === $nodeId && $bundle->purpose === $purpose && $bundle->inputFingerprint === InputFingerprint::of(ConsignmentDraftDocument::draft($input)) && $bundle->consignmentId === $consignmentId && $bundle->expectedVersion === $expectedVersion;
        if (! $matches) {
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'consignment.pricing_quote_does_not_match_request');
        }
        $optionId = (string) ($acceptedQuote->optionId ?? '');
        foreach ($bundle->options as $option) {
            if ($option->optionId === $optionId && $option->available === true) {
                return new AcceptedConsignmentQuoteDto($option, $quoteId, $bundle->quoteVersion, $option->resolvedInputFingerprint, $bundle->providerCalculatedAt);
            }
        }
        throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'consignment.selected_pricing_option_is_unavailable');
    }
}
