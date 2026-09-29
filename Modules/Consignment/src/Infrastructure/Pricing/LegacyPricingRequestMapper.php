<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use DateTimeImmutable;
use Modules\Consignment\Application\Dto\ConsignmentPricingRequestDto;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Throwable;

final class LegacyPricingRequestMapper
{
    /**
     * @return array{order: array<string, mixed>}
     */
    public function map(ConsignmentPricingRequestDto $request): array
    {
        $config = (array) config('chabok.consignment.legacy_pricing');
        $input = $request->input;
        $nodeId = $request->nodeId;
        $hqId = $request->actor->hqId;
        $sender = $input->sender;
        $receiver = $input->receiver;
        $destinationKey = implode('|', [
            mb_strtolower(trim((string) ($receiver->country ?? 'IR'))),
            mb_strtolower(trim((string) ($receiver->state ?? ''))),
            mb_strtolower(trim((string) ($receiver->city ?? ''))),
        ]);
        $origin = $sender->legacyCityCode ?? $config['origin_codes'][$nodeId] ?? null;
        $destination = $receiver->legacyCityCode ?? $config['destination_codes'][$destinationKey] ?? null;
        $party = $config['party_codes'][$hqId] ?? null;
        $wireValues = $config['input_values'] ?? null;
        if (! is_scalar($origin) || ! is_scalar($destination) || ! is_array($party) || ! is_array($wireValues) || ! is_scalar($party['sender_code'] ?? null) || ! is_scalar($party['receiver_code'] ?? null)) {
            $this->unavailable();
        }
        foreach (['cod', 'extra_service', 'Extra_to', 'packing'] as $required) {
            if (! array_key_exists($required, $wireValues) || ! is_scalar($wireValues[$required])) {
                $this->unavailable();
            }
        }
        $commitment = $input->pickupCommitmentAt ?? null;
        if (! is_string($commitment) || $commitment === '') {
            $this->unavailable();
        }
        try {
            $pickup = new DateTimeImmutable($commitment);
        } catch (Throwable) {
            $this->unavailable();
        }
        $parcels = $input->parcels;
        if ($parcels === []) {
            $this->unavailable();
        }
        $volume = [];
        foreach ($parcels as $parcel) {
            foreach ([$parcel->widthCm, $parcel->heightCm, $parcel->lengthCm] as $dimension) {
                if ($dimension === null || ! is_numeric($dimension) || (float) $dimension <= 0) {
                    $this->unavailable();
                }
            }
            $volume[] = [
                'width' => (float) $parcel->widthCm,
                'height' => (float) $parcel->heightCm,
                'length' => (float) $parcel->lengthCm,
            ];
        }

        return [
            'order' => [
                'cod' => (string) $wireValues['cod'],
                'destination' => (string) $destination,
                'extra_service' => (string) $wireValues['extra_service'],
                'Extra_to' => (string) $wireValues['Extra_to'],
                'items_count' => (string) count($parcels),
                'origin' => (string) $origin,
                'packing' => (string) $wireValues['packing'],
                'receiver_code' => (string) $party['receiver_code'],
                'sender_code' => (string) $party['sender_code'],
                'value' => (string) ($input->declaredValueAmount ?? ''),
                'weight' => $this->decimalString($input->weightKg ?? null),
                'pickupDate' => $pickup->format('Y-m-d'),
                'pickupTime' => $pickup->format('H:i'),
                'volume' => $volume,
            ],
        ];
    }

    private function decimalString(mixed $value): string
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            $this->unavailable();
        }

        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    private function unavailable(): never
    {
        throw new ApiException(ApiErrorCode::PricingUnavailable, 503, 'consignment.pricing_is_temporarily_unavailable');
    }
}
