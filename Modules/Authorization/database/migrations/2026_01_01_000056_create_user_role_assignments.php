<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('role_id');
            $table->enum('scope_type', ['PLATFORM', 'TENANT', 'AREA', 'NODE', 'VENDOR', 'VENDOR_BRANCH', 'SELF']);
            $table->unsignedInteger('scope_id')->nullable();
            $table->boolean('includes_descendants')->default('0');
            $table->enum('status', ['ACTIVE', 'REVOKED'])->default('ACTIVE');
            $table->char('active_slot', 64)->nullable();
            $table->unsignedInteger('assigned_by')->nullable();
            $table->unsignedInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['active_slot'], 'user_role_assignments_active_slot_unique');
            $table->index(['role_id'], 'user_role_assignments_role_id_foreign');
            $table->index(['assigned_by'], 'user_role_assignments_assigned_by_foreign');
            $table->index(['revoked_by'], 'user_role_assignments_revoked_by_foreign');
            $table->index(['user_id', 'hq_id', 'status'], 'assignments_user_tenant_status_index');
            $table->index(['hq_id', 'scope_type', 'scope_id', 'status'], 'assignments_scope_index');
            $table->foreign(['assigned_by'], 'user_role_assignments_assigned_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'user_role_assignments_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['revoked_by'], 'user_role_assignments_revoked_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['role_id'], 'user_role_assignments_role_id_foreign')->references(['id'])->on('roles')->onDelete('restrict');
            $table->foreign(['user_id'], 'user_role_assignments_user_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
    }
};
