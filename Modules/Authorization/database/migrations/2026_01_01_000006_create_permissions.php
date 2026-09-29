<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('permission_code', 160);
            $table->string('module_code', 80);
            $table->string('resource_code', 80);
            $table->string('action_code', 80);
            $table->string('description', 500);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['permission_code'], 'permissions_permission_code_unique');
            $table->index(['module_code', 'status'], 'permissions_module_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
