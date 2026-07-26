<?php

declare(strict_types=1);

namespace Modules\Authorization\Application;

final class AuthorizationCatalog
{
    /** @return array<string, string> */
    public static function permissions(): array
    {
        return [
            'branch_panel.access' => 'Foundation',
            'node_context.view' => 'Foundation',
            'node_context.switch' => 'Foundation',
            'audit.view' => 'Foundation',
            'iam.users.view' => 'IAM',
            'iam.users.manage' => 'IAM',
            'iam.roles.assign' => 'IAM',
            'iam.roles.manage' => 'IAM',
            'iam.entitlements.view' => 'IAM',
            'consignment.view' => 'Consignment',
            'consignment.create' => 'Consignment',
            'consignment.edit' => 'Consignment',
            'consignment.cancel' => 'Consignment',
            'parcel.view' => 'Parcel',
            'manifest.view' => 'Manifest',
            'manifest.create' => 'Manifest',
            'manifest.edit' => 'Manifest',
            'manifest.approve' => 'Manifest',
            'manifest.reopen' => 'Manifest',
            'manifest.cancel' => 'Manifest',
            'manifest.print' => 'Manifest',
            'pickup_request.view' => 'Pickup',
            'pickup_request.create' => 'Pickup',
            'pickup_request.assign' => 'Pickup',
            'pickup_request.reassign' => 'Pickup',
            'pickup_request.cancel' => 'Pickup',
            'exception.npu.view' => 'Exception',
            'exception.npu.review' => 'Exception',
            'exception.nok.view' => 'Exception',
            'exception.nok.review' => 'Exception',
            'driver.view' => 'Driver',
            'driver.location.view' => 'Driver',
            'driver.assign' => 'Driver',
            'driver.reassign' => 'Driver',
            'live_operations.view' => 'LiveOperations',
            'live_operations.intervene' => 'LiveOperations',
        ];
    }

    /** @return array<string, array{title: string, kind: string, cloneable: bool}> */
    public static function roles(): array
    {
        return [
            'platform_super_admin' => ['title' => 'Platform Super Admin', 'kind' => 'SYSTEM', 'cloneable' => false],
            'hq_admin' => ['title' => 'HQ Admin', 'kind' => 'SYSTEM', 'cloneable' => false],
            'branch_manager' => ['title' => 'Branch Manager', 'kind' => 'SYSTEM', 'cloneable' => false],
            'branch_operator' => ['title' => 'Branch Operator', 'kind' => 'SYSTEM', 'cloneable' => false],
            'hub_operator' => ['title' => 'Hub Operator', 'kind' => 'SYSTEM', 'cloneable' => false],
            'branch_read_only' => ['title' => 'Branch Read-only', 'kind' => 'SYSTEM', 'cloneable' => false],
            'driver' => ['title' => 'Driver', 'kind' => 'SYSTEM', 'cloneable' => false],
            'vendor_manager' => ['title' => 'Vendor Manager', 'kind' => 'SYSTEM', 'cloneable' => false],
            'manifest_approver' => ['title' => 'Manifest Approver', 'kind' => 'TEMPLATE', 'cloneable' => true],
            'exception_reviewer' => ['title' => 'Exception Reviewer', 'kind' => 'TEMPLATE', 'cloneable' => true],
            'dispatcher' => ['title' => 'Dispatcher', 'kind' => 'TEMPLATE', 'cloneable' => true],
        ];
    }

    /** @return array<string, list<string>> */
    public static function grants(): array
    {
        return [
            'platform_super_admin' => ['iam.entitlements.view'],
            'hq_admin' => ['iam.roles.manage', 'iam.entitlements.view'],
            'branch_manager' => [
                'branch_panel.access', 'node_context.view', 'node_context.switch', 'audit.view',
                'iam.users.view', 'iam.users.manage', 'iam.roles.assign',
                'consignment.view', 'consignment.create', 'consignment.edit', 'consignment.cancel',
                'parcel.view',
                'manifest.view', 'manifest.create', 'manifest.edit', 'manifest.reopen',
                'manifest.cancel', 'manifest.print',
                'pickup_request.view', 'pickup_request.create', 'pickup_request.cancel',
                'exception.npu.view', 'exception.nok.view',
                'driver.view', 'live_operations.view',
            ],
            'branch_operator' => [
                'branch_panel.access', 'node_context.view', 'node_context.switch',
                'consignment.view', 'consignment.create', 'parcel.view',
                'manifest.view', 'manifest.create', 'manifest.edit', 'manifest.print',
                'pickup_request.view', 'pickup_request.create',
                'exception.npu.view', 'exception.nok.view', 'driver.view',
                'live_operations.view',
            ],
            'hub_operator' => [
                'branch_panel.access', 'node_context.view', 'node_context.switch',
                'consignment.view', 'parcel.view', 'manifest.view',
                'manifest.create', 'manifest.edit', 'manifest.print', 'driver.view',
            ],
            'branch_read_only' => [
                'branch_panel.access', 'node_context.view', 'node_context.switch', 'audit.view',
                'consignment.view', 'parcel.view', 'manifest.view', 'pickup_request.view',
                'exception.npu.view', 'exception.nok.view', 'driver.view', 'live_operations.view',
            ],
            'driver' => [],
            'vendor_manager' => [],
            'manifest_approver' => ['manifest.approve'],
            'exception_reviewer' => ['exception.nok.review', 'exception.npu.review'],
            'dispatcher' => [
                'pickup_request.assign', 'pickup_request.reassign',
                'driver.assign', 'driver.reassign', 'live_operations.intervene',
            ],
        ];
    }
}
