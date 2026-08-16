<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class LegacyPricingRequestMapper
{
    /** @param array<string, mixed> $input
     *  @return array{order: array<string, mixed>}
     */
    public function map(array $input): array
    {
        $config = (array) config('chabok.consignment.legacy_pricing');
        $nodeId = (string) ($input['_node_id'] ?? '');
        $hqId = (string) ($input['_hq_id'] ?? '');
        $sender = (array) ($input['sender'] ?? []);
        $receiver = (array) ($input['receiver'] ?? []);
        $destinationKey = implode('|', [
            mb_strtolower(trim((string) ($receiver['country'] ?? 'IR'))),
            mb_strtolower(trim((string) ($receiver['state'] ?? ''))),
            mb_strtolower(trim((string) ($receiver['city'] ?? ''))),
        ]);
        $origin = $sender['legacy_city_code'] ?? ($config['origin_codes'][$nodeId] ?? null);
        $destination = $receiver['legacy_city_code'] ?? ($config['destination_codes'][$destinationKey] ?? null);
        $party = $config['party_codes'][$hqId] ?? null;
        $wireValues = $config['input_values'] ?? null;
        if (! is_scalar($origin) || ! is_scalar($destination) || ! is_array($party)
            || ! is_array($wireValues)
            || ! is_scalar($party['sender_code'] ?? null)
            || ! is_scalar($party['receiver_code'] ?? null)) {
            $this->unavailable();
        }
        foreach (['cod', 'extra_service', 'Extra_to', 'packing'] as $required) {
            if (! array_key_exists($required, $wireValues) || ! is_scalar($wireValues[$required])) {
                $this->unavailable();
            }
        }

        $commitment = $input['pickup_commitment_at'] ?? null;
        if (! is_string($commitment) || $commitment === '') {
            $this->unavailable();
        }
        try {
            $pickup = new \DateTimeImmutable($commitment);
        } catch (\Throwable) {
            $this->unavailable();
        }

        $parcels = (array) ($input['parcels'] ?? []);
        if ($parcels === []) {
            $this->unavailable();
        }
        $volume = [];
        foreach ($parcels as $parcel) {
            if (! is_array($parcel)) {
                $this->unavailable();
            }
            foreach (['width_cm', 'height_cm', 'length_cm'] as $dimension) {
                if (! isset($parcel[$dimension]) || ! is_numeric($parcel[$dimension])
                    || (float) $parcel[$dimension] <= 0) {
                    $this->unavailable();
                }
            }
            $volume[] = [
                'width' => (float) $parcel['width_cm'],
                'height' => (float) $parcel['height_cm'],
                'length' => (float) $parcel['length_cm'],
            ];
        }

        return ['order' => [
            'cod' => (string) $wireValues['cod'],
            'destination' => (string) $destination,
            'extra_service' => (string) $wireValues['extra_service'],
            'Extra_to' => (string) $wireValues['Extra_to'],
            'items_count' => (string) count($parcels),
            'origin' => (string) $origin,
            'packing' => (string) $wireValues['packing'],
            'receiver_code' => (string) $party['receiver_code'],
            'sender_code' => (string) $party['sender_code'],
            'value' => (string) ($input['declared_value_amount'] ?? ''),
            'weight' => $this->decimalString($input['weight_kg'] ?? null),
            'pickupDate' => $pickup->format('Y-m-d'),
            'pickupTime' => $pickup->format('H:i'),
            'volume' => $volume,
        ]];
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
        throw new ApiException(
            ApiErrorCode::PricingUnavailable,
            503,
            'Pricing is temporarily unavailable.',
        );
    }
}
