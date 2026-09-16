<?php

declare(strict_types=1);

namespace Modules\Operations\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class FleetPolicy
{
    private const DRIVER_CAPABILITIES = ['PICKUP', 'LINEHAUL', 'DELIVERY'];
    private const AVAILABILITY = ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'MAINTENANCE', 'INACTIVE'];

    public function capabilities(array $values): array
    {
        $values = array_values(array_unique(array_map(fn($value): string => (string) $value, $values)));
        sort($values);
        if ($values === [] || array_diff($values, self::DRIVER_CAPABILITIES) !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'At least one valid Driver capability is required.', ['capabilities' => ['حداقل یک قابلیت معتبر انتخاب کنید.']]);
        }
        return $values;
    }

    public function operationalType(array $capabilities): string
    {
        return count($capabilities) === 1 ? $capabilities[0] : 'MULTI';
    }

    public function lifecycle(string $status, string $availability, bool $statusWasProvided): array
    {
        if ($status === 'INACTIVE' && $statusWasProvided) {
            $availability = 'INACTIVE';
        }
        if ($status === 'ACTIVE' && $availability === 'INACTIVE' && $statusWasProvided) {
            $availability = 'AVAILABLE';
        }
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true) || !in_array($availability, self::AVAILABILITY, true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Invalid Fleet lifecycle transition.');
        }
        if (($status === 'INACTIVE') !== ($availability === 'INACTIVE')) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Inactive Fleet resources must have INACTIVE availability.');
        }
        return [$status, $availability];
    }

    public function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return trim((string) $value);
    }
}
