<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->char('permission_id', 36)->primary();
            $table->string('permission_code', 160)->unique();
            $table->string('module_code', 80);
            $table->string('resource_code', 80);
            $table->string('action_code', 80);
            $table->string('description', 500);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps(6);
            $table->index(['module_code', 'status'], 'permissions_module_status_index');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->char('role_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->char('owner_key', 36);
            $table->string('role_code', 120);
            $table->string('role_title', 200);
            $table->string('description', 500)->nullable();
            $table->enum('role_kind', ['SYSTEM', 'TEMPLATE', 'CUSTOM']);
            $table->boolean('is_cloneable')->default(false);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->char('created_by', 36)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['owner_key', 'role_code'], 'roles_owner_code_unique');
            $table->index(['hq_id', 'role_kind', 'status'], 'roles_tenant_kind_status_index');
        });
        DB::statement(
            "ALTER TABLE roles ADD CONSTRAINT roles_owner_shape CHECK ((role_kind = 'CUSTOM' AND hq_id IS NOT NULL AND owner_key = hq_id) OR (role_kind <> 'CUSTOM' AND hq_id IS NULL AND owner_key = 'GLOBAL'))",
        );

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->char('role_permission_id', 36)->primary();
            $table->char('role_id', 36);
            $table->char('permission_id', 36);
            $table->char('created_by', 36)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign('role_id')->references('role_id')->on('roles')->restrictOnDelete();
            $table->foreign('permission_id')->references('permission_id')->on('permissions')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['role_id', 'permission_id'], 'role_permissions_unique');
        });

        Schema::create('tenant_module_entitlements', function (Blueprint $table): void {
            $table->char('entitlement_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('module_code', 80);
            $table->enum('status', ['ENABLED', 'SUSPENDED', 'DISABLED']);
            $table->timestamp('activated_at', 6)->nullable();
            $table->timestamp('deactivated_at', 6)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('updated_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id', 'module_code'], 'entitlements_tenant_module_unique');
            $table->index(['hq_id', 'status'], 'entitlements_tenant_status_index');
        });

        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->char('assignment_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->char('user_id', 36);
            $table->char('role_id', 36);
            $table->enum('scope_type', [
                'PLATFORM', 'TENANT', 'AREA', 'NODE', 'VENDOR', 'VENDOR_BRANCH', 'SELF',
            ]);
            $table->char('scope_id', 36)->nullable();
            $table->boolean('includes_descendants')->default(false);
            $table->enum('status', ['ACTIVE', 'REVOKED'])->default('ACTIVE');
            $table->char('active_slot', 64)->nullable()->unique();
            $table->char('assigned_by', 36)->nullable();
            $table->char('revoked_by', 36)->nullable();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('role_id')->references('role_id')->on('roles')->restrictOnDelete();
            $table->foreign('assigned_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('revoked_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(['user_id', 'hq_id', 'status'], 'assignments_user_tenant_status_index');
            $table->index(['hq_id', 'scope_type', 'scope_id', 'status'], 'assignments_scope_index');
        });
        DB::statement(
            "ALTER TABLE user_role_assignments ADD CONSTRAINT assignments_platform_shape CHECK ((scope_type = 'PLATFORM' AND hq_id IS NULL AND scope_id IS NULL AND includes_descendants = 0) OR (scope_type <> 'PLATFORM' AND hq_id IS NOT NULL))",
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::dropIfExists('tenant_module_entitlements');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
