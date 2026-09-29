<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Mappers;

use Modules\Organization\Application\Dto\AreaChangesDto;
use Modules\Organization\Application\Dto\AreaDraftDto;
use Modules\Organization\Application\Dto\NetworkFiltersDto;
use Modules\Organization\Application\Dto\NodeAddressDto;
use Modules\Organization\Application\Dto\NodeChangesDto;
use Modules\Organization\Application\Dto\NodeDetailsDto;
use Modules\Organization\Application\Dto\NodeDraftDto;
use Modules\Organization\Domain\Enums\NetworkStatus;
use Modules\Organization\Domain\Enums\NodeCapability;
use Modules\Organization\Domain\Enums\NodeType;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class NetworkInput
{
    public static function area(array $input): AreaDraftDto
    {
        return new AreaDraftDto($input['area_code'], $input['area_title'], $input['parent_area_id'] ?? null);
    }

    public static function areaChanges(array $input): AreaChangesDto
    {
        return new AreaChangesDto((int) $input['expected_version'], $input['area_title'] ?? null,
            isset($input['status']) ? NetworkStatus::from($input['status']) : null,
            $input['parent_area_id'] ?? null, array_key_exists('parent_area_id', $input));
    }

    public static function filters(array $input): NetworkFiltersDto
    {
        return new NetworkFiltersDto($input['search'] ?? '', isset($input['status']) ? NetworkStatus::from($input['status']) : null,
            $input['area_id'] ?? null, isset($input['node_type']) ? NodeType::from($input['node_type']) : null,
            (int) ($input['page'] ?? 1), (int) ($input['per_page'] ?? 20));
    }

    public static function node(array $input): NodeDraftDto
    {
        return new NodeDraftDto($input['node_code'], new NodeDetailsDto($input['area_id'], $input['node_title'], NodeType::from($input['node_type']),
            self::capabilities($input['capabilities']), self::address($input['address'])));
    }

    public static function nodeChanges(array $input): NodeChangesDto
    {
        return new NodeChangesDto((int) $input['expected_version'], $input['area_id'] ?? null, $input['node_title'] ?? null,
            isset($input['node_type']) ? NodeType::from($input['node_type']) : null,
            isset($input['capabilities']) ? self::capabilities($input['capabilities']) : null,
            isset($input['address']) ? self::address($input['address']) : null,
            isset($input['status']) ? NetworkStatus::from($input['status']) : null);
    }

    public static function mergeNode(NodeRecord $row, NodeChangesDto $changes): NodeDetailsDto
    {
        return new NodeDetailsDto($changes->areaId ?? $row->area_id, $changes->title ?? $row->node_title,
            $changes->type ?? NodeType::from($row->node_type), $changes->capabilities ?? self::capabilities($row->capabilities),
            $changes->address ?? new NodeAddressDto('IR', $row->province_id, $row->city_id, $row->postal_code, $row->address_line,
                $row->latitude === null ? null : (float) $row->latitude, $row->longitude === null ? null : (float) $row->longitude));
    }

    public static function address(array $address): NodeAddressDto
    {
        return new NodeAddressDto($address['country_code'] ?? null, $address['province_id'] ?? null, $address['city_id'] ?? null,
            $address['postal_code'] ?? null, $address['line'] ?? null,
            isset($address['location']['latitude']) ? (float) $address['location']['latitude'] : null,
            isset($address['location']['longitude']) ? (float) $address['location']['longitude'] : null);
    }

    /** @param list<string> $capabilities @return list<NodeCapability> */
    private static function capabilities(array $capabilities): array
    {
        return array_map(NodeCapability::from(...), array_values(array_unique($capabilities)));
    }
}
