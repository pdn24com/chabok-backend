<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CalculateConsignmentQuote;

use Carbon\CarbonImmutable;
use Modules\Consignment\Domain\InputFingerprint;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CalculateConsignmentQuoteHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentQuoteAccess $consignmentQuoteAccess,
        private \Modules\Consignment\Application\Services\ConsignmentQuoteInput $consignmentQuoteInput,
        private \Modules\Consignment\Domain\ConsignmentPolicy $policy,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Consignment\Application\Contracts\PricingQuoteProvider $provider,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Contracts\QuoteSettings $settings,
        private \Modules\Consignment\Application\Contracts\QuoteBundleStore $store,
        private \Modules\Consignment\Application\Services\ConsignmentQuoteProjection $consignmentQuoteProjection,
    )
    {
    }

    public function handle(CalculateConsignmentQuoteCommand $command): CalculateConsignmentQuoteResult
    {
        return new CalculateConsignmentQuoteResult($this->execute($command->actor, $command->nodeId, $command->purpose, $command->input, $command->consignmentId, $command->expectedVersion));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        array $input,
        ?string $consignmentId,
        ?int $expectedVersion,
    ): array
    {
        $this->consignmentQuoteAccess->assertAccess($actor, $nodeId, $purpose === 'CREATE' ? 'consignment.create' : 'consignment.edit');
        $input = $this->consignmentQuoteInput->normalized($input);
        $this->policy->assertCommercialConsistency($input);
        if ($purpose === 'CREATE') {
            $this->policy->assertPilotCreate($input);
        }
        if ($purpose === 'EDIT' && ($consignmentId === null || $expectedVersion === null)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Edit pricing requires the Consignment and expected version.');
        }
        $fingerprint = InputFingerprint::of($input);
        $providerInput = [
            ...$input,
            '_hq_id' => $actor->hqId,
            '_node_id' => $nodeId,
            '_actor_user_id' => $actor->userId,
            '_actor_session_id' => $actor->sessionId,
            '_pricing_request_id' => $this->identifiers->uuid(),
        ];
        $options = array_map(fn(array $option): array => [
            'option_id' => $this->identifiers->uuid(),
            '_resolved_input_fingerprint' => InputFingerprint::of([...$input, '_resolved_commitment' => $option['commitment'] ?? null]),
            ...$option,
        ], $this->provider->calculate($providerInput));
        $now = CarbonImmutable::instance($this->clock->now())->utc();
        $ttl = $this->settings->quoteTtlSeconds();
        $quoteId = $this->identifiers->uuid();
        $bundle = [
            'quote_id' => $quoteId,
            'quote_version' => 1,
            'hq_id' => $actor->hqId,
            'node_id' => $nodeId,
            'purpose' => $purpose,
            'input_fingerprint' => $fingerprint,
            'consignment_id' => $consignmentId,
            'expected_version' => $expectedVersion,
            'provider_calculated_at' => $now->toISOString(),
            'expires_at' => $now->addSeconds($ttl)->toISOString(),
            'options' => $options,
        ];
        $this->store->put($quoteId, $bundle, $ttl);
        return $this->consignmentQuoteProjection->publicBundle($bundle);
    }
}
