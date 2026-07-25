<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authentication_credentials', function (Blueprint $table): void {
            $table->char('credential_id', 36)->primary();
            $table->char('user_id', 36)->unique();
            $table->string('password_hash');
            $table->string('algorithm', 40)->default('argon2id');
            $table->unsignedSmallInteger('algorithm_version')->default(1);
            $table->timestamp('password_changed_at', 6);
            $table->unsignedSmallInteger('failed_attempt_count')->default(0);
            $table->timestamp('locked_until', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
        });

        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->char('challenge_id', 36)->primary();
            $table->char('user_id', 36)->nullable();
            $table->char('destination_fingerprint', 64);
            $table->enum('purpose', ['ACTIVATION', 'PASSWORD_RESET']);
            $table->string('code_hash');
            $table->char('verification_token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at', 6);
            $table->unsignedTinyInteger('remaining_attempts')->default(5);
            $table->enum('status', ['PENDING', 'VERIFIED', 'CONSUMED', 'EXPIRED', 'LOCKED'])
                ->default('PENDING');
            $table->char('request_ip_hash', 64)->nullable();
            $table->timestamp('verified_at', 6)->nullable();
            $table->timestamp('consumed_at', 6)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(
                ['purpose', 'destination_fingerprint', 'status', 'expires_at'],
                'otp_lookup_index',
            );
        });

        Schema::create('user_invitations', function (Blueprint $table): void {
            $table->char('invitation_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('user_id', 36);
            $table->enum('channel', ['SMS', 'EMAIL']);
            $table->string('normalized_recipient', 254);
            $table->char('token_hash', 64)->unique();
            $table->enum('status', ['PENDING', 'SUPERSEDED', 'ACCEPTED', 'EXPIRED'])
                ->default('PENDING');
            $table->timestamp('sent_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('accepted_at', 6)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(['hq_id', 'user_id', 'channel', 'status'], 'invitation_current_index');
        });

        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->char('session_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->char('user_id', 36);
            $table->char('token_family_id', 36);
            $table->char('refresh_token_hash', 64)->unique();
            $table->string('device_id', 200)->nullable();
            $table->string('device_name', 200)->nullable();
            $table->char('ip_address_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('issued_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('last_seen_at', 6)->nullable();
            $table->unsignedInteger('rotation_counter')->default(0);
            $table->timestamp('rotated_at', 6)->nullable();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->string('revoked_reason', 80)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->index(
                ['user_id', 'revoked_at', 'expires_at'],
                'sessions_user_active_index',
            );
            $table->index('token_family_id', 'sessions_family_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('user_invitations');
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('authentication_credentials');
    }
};
