<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\AcceptConsignmentQuote;

use Carbon\CarbonImmutable;
use Modules\Consignment\Domain\InputFingerprint;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class AcceptConsignmentQuoteHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentQuoteInput $consignmentQuoteInput,
        private \Modules\Consignment\Domain\ConsignmentPolicy $policy,
        private \Modules\Consignment\Application\Contracts\QuoteBundleStore $store,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function handle(AcceptConsignmentQuoteCommand $command): AcceptConsignmentQuoteResult
    {
        return new AcceptConsignmentQuoteResult($this->execute($command->actor, $command->nodeId, $command->purpose, $command->input, $command->acceptedQuote, $command->consignmentId, $command->expectedVersion));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        array $input,
        array $acceptedQuote,
        ?string $consignmentId = null,
        ?int $expectedVersion = null,
    ): array
    {
        $input = $this->consignmentQuoteInput->normalized($input);
        $this->policy->assertCommercialConsistency($input);
        if ($purpose === 'CREATE') {
            $this->policy->assertPilotCreate($input);
        }
        $quoteId = (string) ($acceptedQuote['quote_id'] ?? '');
        $bundle = $this->store->get($quoteId);
        if ($bundle === null || CarbonImmutable::parse((string) $bundle['expires_at']) < $this->clock->now()) {
            throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'The pricing quote has expired.');
        }
        $matches = ($acceptedQuote['quote_version'] ?? null) === $bundle['quote_version'] && $bundle['hq_id'] === $actor->hqId && $bundle['node_id'] === $nodeId && $bundle['purpose'] === $purpose && $bundle['input_fingerprint'] === InputFingerprint::of($input) && $bundle['consignment_id'] === $consignmentId && $bundle['expected_version'] === $expectedVersion;
        if (!$matches) {
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'The pricing quote does not match this request.');
        }
        $optionId = (string) ($acceptedQuote['option_id'] ?? '');
        foreach ($bundle['options'] as $option) {
            if ($option['option_id'] === $optionId && $option['available'] === true) {
                return [
                    ...$option,
                    'quote_id' => $quoteId,
                    'quote_version' => (int) $bundle['quote_version'],
                    'input_fingerprint' => (string) $option['_resolved_input_fingerprint'],
                    'provider_calculated_at' => (string) $bundle['provider_calculated_at'],
                ];
            }
        }
        throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'The selected pricing option is unavailable.');
    }
}
