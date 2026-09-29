<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Contracts\GeographyResolverInterface;
use Modules\Pricing\Application\Contracts\PricingInputInterface;
use Modules\Pricing\Application\Dto\QuoteInputDto;
use Modules\Pricing\Application\Dto\QuoteParcelDto;
use Modules\Pricing\Application\Mappers\QuoteInputMapper;
use Modules\Pricing\Application\Serialization\QuoteInputDocument;

final readonly class PricingInput implements PricingInputInterface
{
    public function __construct(private GeographyResolverInterface $geographyResolver, private ClockInterface $clock) {}

    public function normalize(QuoteInputDto $request): QuoteInputDto
    {
        $input = clone $request;
        if (! (bool) $input->insuranceEnabled) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.declared_value_insurance_is_mandatory', fieldErrors: ['insurance_enabled' => ['pricing.insurance_is_required']]);
        }
        $packages = $input->parcels;
        if ($packages === []) {
            $packages = [new QuoteParcelDto(weightKg: $input->weightKg, lengthCm: $input->lengthCm, widthCm: $input->widthCm, heightCm: $input->heightCm)];
        }
        foreach ($packages as $index => $package) {
            if (! is_numeric($package->weightKg) || ! is_finite((float) $package->weightKg) || $package->weightKg <= 0) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.positive_parcel_weight_is_required', fieldErrors: ["parcels.{$index}.weight_kg" => ['pricing.positive_weight_is_required']]);
            }
            $dimensions = [$package->lengthCm, $package->widthCm, $package->heightCm];
            $provided = 0;
            $valid = 0;
            foreach ($dimensions as $dimension) {
                if ($dimension === null) {
                    continue;
                }
                $provided++;
                if (is_numeric($dimension) && is_finite((float) $dimension) && $dimension > 0) {
                    $valid++;
                }
            }
            if ($provided > 0 && $valid !== 3) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.provide_all_three_positive_parcel_dimensions_omit', fieldErrors: ["parcels.{$index}" => ['pricing.dimensions_are_incomplete_or_invalid']]);
            }
        }
        if ($input->codEnabled && (int) $input->codAmount <= 0) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.cod_requires_positive_goods_collection_amount');
        }
        foreach (['_hq_id', '_node_id', '_actor_user_id', '_actor_session_id', '_pricing_request_id'] as $key) {
            unset($input->extensions[$key]);
        }
        $sender = $this->geographyResolver->canonicalizeContact(QuoteInputDocument::contact($input->sender), false);
        $receiver = $this->geographyResolver->canonicalizeContact(QuoteInputDocument::contact($input->receiver), false);
        unset($sender['city_reference'], $receiver['city_reference']);
        $input->sender = QuoteInputMapper::contact($sender);
        $input->receiver = QuoteInputMapper::contact($receiver);
        $input->asOfTimestamp = CarbonImmutable::parse($input->asOfTimestamp ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString())->utc()->toISOString();
        $input->acceptanceAt ??= $input->asOfTimestamp;
        $input->selectedOptionVersionIds = array_values($input->selectedOptionVersionIds);
        $input->presentFields = array_values(array_unique([...$input->presentFields, 'sender', 'receiver', 'purpose', 'channel', 'as_of_timestamp', 'acceptance_at', 'selected_option_version_ids']));

        return $input;
    }

    public function fingerprint(QuoteInputDto $input): string
    {
        $value = $this->canonical(QuoteInputDocument::quote($input));

        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE));
    }

    public function databaseTimestamp(DateTimeInterface|string|null $value): ?string
    {
        return $value === null || $value === '' ? null : ($value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value))->utc()->format('Y-m-d H:i:s.u');
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }
}
