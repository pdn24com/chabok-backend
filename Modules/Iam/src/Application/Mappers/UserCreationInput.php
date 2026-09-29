<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Mappers;

use Modules\Foundation\Application\Mappers\RoleAssignmentInput;
use Modules\Iam\Application\Dto\DriverProfileDraftDto;
use Modules\Iam\Application\Dto\NodeProfileDraftDto;
use Modules\Iam\Application\Dto\OperationalProfileInputDto;
use Modules\Iam\Application\Dto\ProfileAddressDto;
use Modules\Iam\Application\Dto\UserCreationDto;
use Modules\Iam\Domain\Enums\OperationalProfileKind;
use Modules\Iam\Domain\Enums\OperationalProfileMode;
use Modules\Iam\Domain\Enums\UserCreationMode;

final class UserCreationInput
{
    public static function user(array $input): UserCreationDto
    {
        return new UserCreationDto(UserCreationMode::tryFrom($input['creation_mode']), $input['username'] ?? null,
            $input['mobile'] ?? null, $input['email'] ?? null, $input['first_name'], $input['last_name'],
            $input['temporary_password'] ?? null, RoleAssignmentInput::drafts($input['assignments']),
            isset($input['operational_profile']) ? self::profile($input['operational_profile']) : null);
    }

    public static function profile(array $input): OperationalProfileInputDto
    {
        $driver = $input['driver'] ?? null;
        $node = $input['node'] ?? null;
        $address = $node['address'] ?? null;

        return new OperationalProfileInputDto(OperationalProfileKind::from($input['kind']), OperationalProfileMode::from($input['mode']),
            $input['existing_id'] ?? null, isset($input['expected_version']) ? (int) $input['expected_version'] : null, $input['role_id'] ?? null,
            $driver === null ? null : new DriverProfileDraftDto(trim($driver['driver_code']), trim($driver['display_name']),
                $driver['home_node_id'], array_values($driver['capabilities']), $driver['mobile'] ?? null),
            $node === null ? null : new NodeProfileDraftDto($node['node_code'], $node['node_title'], $node['area_id'], $node['node_type'],
                array_values($node['capabilities']), new ProfileAddressDto($address['country_code'], $address['province_id'] ?? null,
                    $address['city_id'] ?? null, $address['postal_code'] ?? null, $address['line'] ?? null,
                    isset($address['location']['latitude']) ? (float) $address['location']['latitude'] : null,
                    isset($address['location']['longitude']) ? (float) $address['location']['longitude'] : null)));
    }
}
