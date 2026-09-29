<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

final class OperationalProfileRules
{
    public static function rules(array $input = []): array
    {
        return [
            'operational_profile' => ['sometimes', 'array:kind,mode,existing_id,expected_version,role_id,driver,node'],
            'operational_profile.kind' => ['required_with:operational_profile', 'in:DRIVER,NODE'],
            'operational_profile.mode' => ['required_with:operational_profile', 'in:CREATE,LINK'],
            'operational_profile.existing_id' => [($input['mode'] ?? null) === 'LINK' ? 'required' : 'prohibited', 'integer', 'min:1', 'max:4294967295'],
            'operational_profile.expected_version' => [
                ($input['kind'] ?? null) === 'DRIVER' && ($input['mode'] ?? null) === 'LINK' ? 'required' : 'prohibited',
                'integer',
                'min:1',
            ],
            'operational_profile.role_id' => [($input['kind'] ?? null) === 'NODE' ? 'required' : 'prohibited', 'integer', 'min:1', 'max:4294967295'],
            'operational_profile.driver' => [
                ($input['kind'] ?? null) === 'DRIVER' && ($input['mode'] ?? null) === 'CREATE' ? 'required' : 'prohibited',
                'array:driver_code,display_name,home_node_id,mobile,capabilities',
            ],
            'operational_profile.driver.driver_code' => ['required_with:operational_profile.driver', 'string', 'max:80'],
            'operational_profile.driver.display_name' => ['required_with:operational_profile.driver', 'string', 'max:200'],
            'operational_profile.driver.home_node_id' => ['required_with:operational_profile.driver', 'integer', 'min:1', 'max:4294967295'],
            'operational_profile.driver.mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'operational_profile.driver.capabilities' => ['required_with:operational_profile.driver', 'array', 'min:1'],
            'operational_profile.driver.capabilities.*' => ['in:PICKUP,LINEHAUL,DELIVERY', 'distinct'],
            'operational_profile.node' => [
                ($input['kind'] ?? null) === 'NODE' && ($input['mode'] ?? null) === 'CREATE' ? 'required' : 'prohibited',
                'array:area_id,node_code,node_title,node_type,capabilities,address',
            ],
            'operational_profile.node.area_id' => ['required_with:operational_profile.node', 'integer', 'min:1', 'max:4294967295'],
            'operational_profile.node.node_code' => ['required_with:operational_profile.node', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'operational_profile.node.node_title' => ['required_with:operational_profile.node', 'string', 'max:200'],
            'operational_profile.node.node_type' => ['required_with:operational_profile.node', 'in:BRANCH,HUB,GATEWAY,AGENT'],
            'operational_profile.node.capabilities' => ['required_with:operational_profile.node', 'array', 'min:1'],
            'operational_profile.node.capabilities.*' => ['in:PICKUP,CONSOLIDATION,GATEWAY,LINEHAUL,DELIVERY,CUSTOMER_HANDOFF', 'distinct'],
            'operational_profile.node.address' => ['required_with:operational_profile.node', 'array:country_code,line,postal_code,province_id,city_id,location'],
            'operational_profile.node.address.country_code' => ['required_with:operational_profile.node', 'in:IR'],
            'operational_profile.node.address.line' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'operational_profile.node.address.postal_code' => ['sometimes', 'nullable', 'regex:/^[0-9]{10}$/'],
            'operational_profile.node.address.province_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'operational_profile.node.address.city_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'operational_profile.node.address.location' => ['sometimes', 'nullable', 'array:latitude,longitude'],
            'operational_profile.node.address.location.latitude' => ['required_with:operational_profile.node.address.location', 'numeric', 'between:-90,90'],
            'operational_profile.node.address.location.longitude' => ['required_with:operational_profile.node.address.location', 'numeric', 'between:-180,180'],
        ];
    }
}
