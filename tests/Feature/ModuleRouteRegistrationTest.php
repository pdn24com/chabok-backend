<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Tests\TestCase;

final class ModuleRouteRegistrationTest extends TestCase
{
    public function test_s0_04_registers_the_complete_approved_operation_set(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'))
            ->map(fn (Route $route): string => $route->methods()[0].' /'.$route->uri())
            ->sort()
            ->values()
            ->all();

        $expected = [
            'DELETE /api/v1/iam/users/{userId}/role-assignments/{assignmentId}',
            'DELETE /api/v1/me/sessions/{sessionId}',
            'GET /api/v1/context/nodes',
            'GET /api/v1/consignments',
            'GET /api/v1/consignments/{consignmentId}',
            'GET /api/v1/manifests',
            'GET /api/v1/manifests/{manifestId}',
            'GET /api/v1/manifests/{manifestId}/eligible-parcels',
            'GET /api/v1/iam/module-entitlements',
            'GET /api/v1/iam/permissions',
            'GET /api/v1/iam/roles',
            'GET /api/v1/iam/roles/{roleId}',
            'GET /api/v1/iam/users',
            'GET /api/v1/iam/users/{userId}',
            'GET /api/v1/me',
            'GET /api/v1/me/context',
            'GET /api/v1/me/sessions',
            'PATCH /api/v1/iam/roles/{roleId}',
            'PATCH /api/v1/consignments/{consignmentId}',
            'PATCH /api/v1/iam/users/{userId}',
            'PATCH /api/v1/manifests/{manifestId}',
            'PATCH /api/v1/me/profile',
            'POST /api/v1/auth/login',
            'POST /api/v1/auth/logout',
            'POST /api/v1/auth/logout-all',
            'POST /api/v1/auth/otp/send',
            'POST /api/v1/auth/otp/verify',
            'POST /api/v1/auth/password/activate',
            'POST /api/v1/auth/password/change',
            'POST /api/v1/auth/password/reset',
            'POST /api/v1/auth/refresh',
            'POST /api/v1/consignments',
            'POST /api/v1/consignments/pricing-quotes',
            'POST /api/v1/iam/roles/{roleId}/clone',
            'POST /api/v1/iam/users',
            'POST /api/v1/iam/users/{userId}/activate',
            'POST /api/v1/iam/users/{userId}/deactivate',
            'POST /api/v1/iam/users/{userId}/invite',
            'POST /api/v1/iam/users/{userId}/revoke-sessions',
            'POST /api/v1/iam/users/{userId}/role-assignments',
            'POST /api/v1/iam/users/{userId}/suspend',
            'POST /api/v1/iam/users/{userId}/temporary-password',
            'POST /api/v1/manifests',
            'POST /api/v1/manifests/{manifestId}/confirm',
            'POST /api/v1/manifests/{manifestId}/parcels',
            'POST /api/v1/manifests/{manifestId}/validate',
            'PUT /api/v1/iam/roles/{roleId}/permissions',
        ];
        sort($expected);
        $this->assertSame($expected, $routes);
    }
}
