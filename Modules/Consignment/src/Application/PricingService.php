<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Consignment\Application\Contracts\PricingQuoteProvider;
use Modules\Consignment\Application\Contracts\QuoteBundleStore;
use Modules\Consignment\Domain\ConsignmentPolicy;
use Modules\Consignment\Domain\InputFingerprint;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Geography\Application\GeographyResolver;

final readonly class PricingService
{
    public function __construct(
        private PricingQuoteProvider $provider,
        private QuoteBundleStore $store,
        private AuthorizationContextResolver $authorization,
        private ConsignmentPolicy $policy,
        private GeographyResolver $geography,
    ) {}

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function calculate(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        array $input,
        ?string $consignmentId,
        ?int $expectedVersion,
    ): array {
        $this->assertAccess($actor, $nodeId, $purpose === 'CREATE' ? 'consignment.create' : 'consignment.edit');
        $input = $this->normalized($input);
        $this->policy->assertCommercialConsistency($input);
        if ($purpose === 'CREATE') $this->policy->assertPilotCreate($input);
        if ($purpose === 'EDIT' && ($consignmentId === null || $expectedVersion === null)) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'Edit pricing requires the Consignment and expected version.',
            );
        }
        $fingerprint = InputFingerprint::of($input);
        $providerInput = [...$input, '_hq_id' => $actor->hqId, '_node_id' => $nodeId, '_actor_user_id' => $actor->userId, '_actor_session_id' => $actor->sessionId, '_pricing_request_id' => (string) Str::uuid()];
        $options = array_map(static fn (array $option): array => [
            'option_id' => (string) Str::uuid(),
            '_resolved_input_fingerprint' => InputFingerprint::of([...$input, '_resolved_commitment' => $option['commitment'] ?? null]),
            ...$option,
        ], $this->provider->calculate($providerInput));
        $now = CarbonImmutable::now('UTC');
        $ttl = (int) config('chabok.consignment.quote_ttl_seconds', 900);
        $quoteId = (string) Str::uuid();
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

        return $this->publicBundle($bundle);
    }

    /** @param array<string, mixed> $input
     *  @param array<string, mixed> $acceptedQuote
     *  @return array<string, mixed>
     */
    public function accept(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        array $input,
        array $acceptedQuote,
        ?string $consignmentId = null,
        ?int $expectedVersion = null,
    ): array {
        $input = $this->normalized($input);
        $this->policy->assertCommercialConsistency($input);
        if ($purpose === 'CREATE') $this->policy->assertPilotCreate($input);
        $quoteId = (string) ($acceptedQuote['quote_id'] ?? '');
        $bundle = $this->store->get($quoteId);
        if ($bundle === null || CarbonImmutable::parse((string) $bundle['expires_at'])->isPast()) {
            throw new ApiException(
                ApiErrorCode::PricingQuoteExpired,
                422,
                'The pricing quote has expired.',
            );
        }
        $matches = ($acceptedQuote['quote_version'] ?? null) === $bundle['quote_version']
            && $bundle['hq_id'] === $actor->hqId
            && $bundle['node_id'] === $nodeId
            && $bundle['purpose'] === $purpose
            && $bundle['input_fingerprint'] === InputFingerprint::of($input)
            && $bundle['consignment_id'] === $consignmentId
            && $bundle['expected_version'] === $expectedVersion;
        if (! $matches) {
            throw new ApiException(
                ApiErrorCode::PricingQuoteMismatch,
                422,
                'The pricing quote does not match this request.',
            );
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
        throw new ApiException(
            ApiErrorCode::PricingQuoteMismatch,
            422,
            'The selected pricing option is unavailable.',
        );
    }

    public function consume(string $quoteId): void
    {
        $this->store->forget($quoteId);
    }

    private function assertAccess(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): void {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        $entitled = collect($context['module_entitlements'])
            ->contains(fn (array $item): bool => $item['module_code'] === 'Consignment'
                && $item['status'] === 'ENABLED');
        if (! $entitled) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (! in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if (! in_array($nodeId, $context['accessible_node_ids'], true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    /** @param array<string, mixed> $bundle
     *  @return array<string, mixed>
     */
    private function publicBundle(array $bundle): array
    {
        return [
            'quote_id' => $bundle['quote_id'],
            'quote_version' => $bundle['quote_version'],
            'purpose' => $bundle['purpose'],
            'expires_at' => $bundle['expires_at'],
            'options' => array_map(static function (array $option): array {
                unset($option['_resolved_input_fingerprint']);
                return $option;
            }, $bundle['options']),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function normalized(array $input): array
    {
        // These are server-resolved evidence, not editable quote inputs. The new
        // resolved commitment is fingerprinted separately with the provider result.
        foreach (['commitment_schedule_version_id', 'pickup_commitment_start_at', 'pickup_commitment_end_at', 'delivery_commitment_start_at', 'delivery_commitment_end_at', 'commitment_snapshot', 'delivery_commitment_resolution'] as $field) {
            $input[$field] = null;
        }
        foreach (['sender', 'receiver'] as $party) {
            $contact = $this->geography->canonicalizeContact((array) ($input[$party] ?? []), true);
            unset($contact['city_reference']);
            foreach ([
                'address_book_entry_id', 'phone', 'country', 'postal_code',
                'latitude', 'longitude',
            ] as $field) {
                $contact[$field] ??= null;
            }
            $input[$party] = $contact;
        }
        foreach ([
            'pickup_commitment_at', 'delivery_commitment_at', 'width_cm',
            'length_cm', 'height_cm', 'insurance_value_amount', 'cod_amount',
            'service_offering_id', 'service_offering_version_id',
            'pickup_service_date', 'pickup_window_code', 'delivery_window_code',
            'commitment_schedule_version_id', 'pickup_commitment_start_at',
            'pickup_commitment_end_at', 'delivery_commitment_start_at',
            'delivery_commitment_end_at', 'commitment_snapshot', 'delivery_commitment_resolution',
        ] as $field) {
            $input[$field] ??= null;
        }
        $input['selected_option_version_ids'] = array_values((array) ($input['selected_option_version_ids'] ?? []));
        foreach (['pickup_commitment_at', 'delivery_commitment_at'] as $field) {
            if ($input[$field] !== null) {
                $input[$field] = CarbonImmutable::parse((string) $input[$field])->utc()->toISOString();
            }
        }
        $input['parcels'] = array_map(static function (array $parcel): array {
            foreach (['weight_kg', 'width_cm', 'length_cm', 'height_cm'] as $field) {
                $parcel[$field] ??= null;
            }
            $parcel['content_description'] = trim((string) ($parcel['content_description'] ?? ''));

            return $parcel;
        }, (array) ($input['parcels'] ?? []));

        return $input;
    }
}
