<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('owner_key', 36);
            $table->string('code', 80);
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])->default('ACTIVE');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unsignedInteger('edit_lock')->default('1');
            $table->char('saved_input_fingerprint', 64)->nullable();
            $table->unique(['owner_key', 'code'], 'shipping_methods_owner_code_unique');
            $table->index(['created_by'], 'shipping_methods_created_by_foreign');
            $table->index(['hq_id', 'status', 'code'], 'shipping_methods_tenant_list_index');
            $table->foreign(['created_by'], 'shipping_methods_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'shipping_methods_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
