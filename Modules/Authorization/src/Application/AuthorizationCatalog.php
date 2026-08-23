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
            'network.area.view' => 'LiveOperations',
            'network.area.manage' => 'LiveOperations',
            'network.node.view' => 'LiveOperations',
            'network.node.manage' => 'LiveOperations',
            'network.coverage.view' => 'LiveOperations',
            'network.coverage.manage_draft' => 'LiveOperations',
            'network.coverage.validate' => 'LiveOperations',
            'network.coverage.approve' => 'LiveOperations',
            'network.coverage.publish' => 'LiveOperations',
            'network.route.view' => 'LiveOperations',
            'network.route.manage_draft' => 'LiveOperations',
            'network.route.validate' => 'LiveOperations',
            'network.route.approve' => 'LiveOperations',
            'network.route.publish' => 'LiveOperations',
            'fleet.driver.view' => 'Driver',
            'fleet.driver.manage' => 'Driver',
            'fleet.vehicle.view' => 'Driver',
            'fleet.vehicle.manage' => 'Driver',
            'service_catalog.view' => 'ServiceCatalog',
            'service_catalog.history.view' => 'ServiceCatalog',
            'service_catalog.resolve' => 'ServiceCatalog',
            'service_catalog.manage_draft' => 'ServiceCatalog',
            'service_catalog.availability.manage' => 'ServiceCatalog',
            'service_catalog.approve' => 'ServiceCatalog',
            'service_catalog.publish' => 'ServiceCatalog',
            'service_catalog.audit.view' => 'ServiceCatalog',
            'pricing.quote.calculate' => 'Pricing',
            'pricing.quote.view' => 'Pricing',
            'pricing.tariff.view' => 'Pricing',
            'pricing.tariff.manage_draft' => 'Pricing',
            'pricing.tariff.approve' => 'Pricing',
            'pricing.tariff.publish' => 'Pricing',
            'pricing.audit.view' => 'Pricing',
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
            'hq_admin' => [
                'iam.roles.manage', 'iam.entitlements.view',
                'service_catalog.view', 'service_catalog.history.view', 'service_catalog.resolve',
                'service_catalog.manage_draft', 'service_catalog.availability.manage',
                'service_catalog.approve', 'service_catalog.publish', 'service_catalog.audit.view',
                'pricing.quote.calculate', 'pricing.quote.view', 'pricing.tariff.view',
                'pricing.tariff.manage_draft', 'pricing.tariff.approve', 'pricing.tariff.publish',
                'pricing.audit.view',
                'network.area.view', 'network.area.manage', 'network.node.view', 'network.node.manage',
                'network.coverage.view', 'network.coverage.manage_draft', 'network.coverage.validate',
                'network.coverage.approve', 'network.coverage.publish',
                'network.route.view', 'network.route.manage_draft', 'network.route.validate',
                'network.route.approve', 'network.route.publish',
                'fleet.driver.view', 'fleet.driver.manage', 'fleet.vehicle.view', 'fleet.vehicle.manage',
            ],
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
                'service_catalog.view', 'service_catalog.resolve',
                'pricing.quote.calculate', 'pricing.quote.view',
            ],
            'branch_operator' => [
                'branch_panel.access', 'node_context.view', 'node_context.switch',
                'consignment.view', 'consignment.create', 'parcel.view',
                'manifest.view', 'manifest.create', 'manifest.edit', 'manifest.print',
                'pickup_request.view', 'pickup_request.create',
                'exception.npu.view', 'exception.nok.view', 'driver.view',
                'live_operations.view',
                'service_catalog.view', 'service_catalog.resolve',
                'pricing.quote.calculate', 'pricing.quote.view',
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

    /**
     * Entitlements required before the new administration permissions are evaluated.
     *
     * No new entitlement code is introduced: network configuration is part of
     * LiveOperations and fleet administration is part of Driver. This method is
     * the contract used by Wave 1 authorizers; it deliberately does not grant a
     * permission to any role.
     *
     * @return array<string, string>
     */
    public static function administrativeEntitlements(): array
    {
        return [
            'network.area.view' => 'LiveOperations',
            'network.area.manage' => 'LiveOperations',
            'network.node.view' => 'LiveOperations',
            'network.node.manage' => 'LiveOperations',
            'network.coverage.view' => 'LiveOperations',
            'network.coverage.manage_draft' => 'LiveOperations',
            'network.coverage.validate' => 'LiveOperations',
            'network.coverage.approve' => 'LiveOperations',
            'network.coverage.publish' => 'LiveOperations',
            'network.route.view' => 'LiveOperations',
            'network.route.manage_draft' => 'LiveOperations',
            'network.route.validate' => 'LiveOperations',
            'network.route.approve' => 'LiveOperations',
            'network.route.publish' => 'LiveOperations',
            'fleet.driver.view' => 'Driver',
            'fleet.driver.manage' => 'Driver',
            'fleet.vehicle.view' => 'Driver',
            'fleet.vehicle.manage' => 'Driver',
        ];
    }
}
