<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->string('token_family_id', 64);
            $table->char('refresh_token_hash', 64);
            $table->string('device_id', 200)->nullable();
            $table->string('device_name', 200)->nullable();
            $table->char('ip_address_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('issued_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('last_seen_at', 6)->nullable();
            $table->unsignedInteger('rotation_counter')->default('0');
            $table->timestamp('rotated_at', 6)->nullable();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->string('revoked_reason', 80)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['refresh_token_hash'], 'user_sessions_refresh_token_hash_unique');
            $table->index(['hq_id'], 'user_sessions_hq_id_foreign');
            $table->index(['user_id', 'revoked_at', 'expires_at'], 'sessions_user_active_index');
            $table->index(['token_family_id'], 'sessions_family_index');
            $table->foreign(['hq_id'], 'user_sessions_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['user_id'], 'user_sessions_user_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
