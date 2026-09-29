<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['role_id', 'permission_id'], 'role_permissions_unique');
            $table->index(['permission_id'], 'role_permissions_permission_id_foreign');
            $table->index(['created_by'], 'role_permissions_created_by_foreign');
            $table->foreign(['created_by'], 'role_permissions_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['permission_id'], 'role_permissions_permission_id_foreign')->references(['id'])->on('permissions')->onDelete('restrict');
            $table->foreign(['role_id'], 'role_permissions_role_id_foreign')->references(['id'])->on('roles')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
