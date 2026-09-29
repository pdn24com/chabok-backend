<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('username', 100)->nullable();
            $table->string('normalized_username', 100)->collation('utf8mb4_bin')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('normalized_mobile', 32)->collation('utf8mb4_bin')->nullable();
            $table->string('email', 254)->nullable();
            $table->string('normalized_email', 254)->collation('utf8mb4_bin')->nullable();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('display_name', 240);
            $table->enum('status', ['INVITED', 'ACTIVE', 'SUSPENDED', 'DEACTIVATED']);
            $table->boolean('must_change_password')->default('0');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('activated_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['normalized_username'], 'users_normalized_username_unique');
            $table->unique(['normalized_mobile'], 'users_normalized_mobile_unique');
            $table->unique(['normalized_email'], 'users_normalized_email_unique');
            $table->index(['created_by'], 'users_created_by_foreign');
            $table->index(['hq_id', 'status'], 'users_hq_status_index');
            $table->index(['created_at'], 'users_created_at_index');
            $table->foreign(['created_by'], 'users_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'users_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
