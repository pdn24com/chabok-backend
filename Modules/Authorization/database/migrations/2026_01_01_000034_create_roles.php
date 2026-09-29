<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('owner_key', 36);
            $table->string('role_code', 120);
            $table->string('role_title', 200);
            $table->string('description', 500)->nullable();
            $table->enum('role_kind', ['SYSTEM', 'TEMPLATE', 'CUSTOM']);
            $table->boolean('is_cloneable')->default('0');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['owner_key', 'role_code'], 'roles_owner_code_unique');
            $table->index(['created_by'], 'roles_created_by_foreign');
            $table->index(['hq_id', 'role_kind', 'status'], 'roles_tenant_kind_status_index');
            $table->foreign(['created_by'], 'roles_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'roles_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
