<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class NetworkInputValidator
{
    private const NODE_TYPES = ['BRANCH', 'HUB', 'GATEWAY', 'AGENT'];
    private const CAPABILITIES = ['PICKUP', 'CONSOLIDATION', 'GATEWAY', 'LINEHAUL', 'DELIVERY', 'CUSTOMER_HANDOFF'];

    public function __construct(private \Modules\Organization\Application\Repositories\NetworkRepository $network)
    {
    }

    public function validateNodeInput(string $hqId, array $input): void
    {
        if (!in_array($input['node_type'], self::NODE_TYPES, true) || array_diff($input['capabilities'], self::CAPABILITIES) !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Invalid Node type or capability.');
        }
        if (!$this->network->activeAreaExists($hqId, $input['area_id'])) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active Area in the current HQ is required.');
        }
        $address = $input['address'];
        if (($address['country_code'] ?? null) !== 'IR') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only IR addresses are supported.');
        }
        if (($address['city_id'] ?? null) !== null) {
            $city = $this->network->activeCity($address['city_id']);
            if ($city === null || ($address['province_id'] ?? null) !== $city->province_id) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'City and Province must be an active canonical pair.');
            }
        } elseif (($address['province_id'] ?? null) !== null && !$this->network->activeProvinceExists($address['province_id'])) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Province must be active.');
        }
    }
}
