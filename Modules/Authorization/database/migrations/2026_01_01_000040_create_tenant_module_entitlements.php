<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_entitlements', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('module_code', 80);
            $table->enum('status', ['ENABLED', 'SUSPENDED', 'DISABLED']);
            $table->timestamp('activated_at', 6)->nullable();
            $table->timestamp('deactivated_at', 6)->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'module_code'], 'entitlements_tenant_module_unique');
            $table->index(['updated_by'], 'tenant_module_entitlements_updated_by_foreign');
            $table->index(['hq_id', 'status'], 'entitlements_tenant_status_index');
            $table->foreign(['hq_id'], 'tenant_module_entitlements_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['updated_by'], 'tenant_module_entitlements_updated_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_entitlements');
    }
};
