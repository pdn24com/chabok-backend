<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->char('destination_fingerprint', 64);
            $table->enum('purpose', ['ACTIVATION', 'PASSWORD_RESET']);
            $table->string('code_hash', 255);
            $table->char('verification_token_hash', 64)->nullable();
            $table->timestamp('expires_at', 6);
            $table->unsignedTinyInteger('remaining_attempts')->default('5');
            $table->enum('status', ['PENDING', 'VERIFIED', 'CONSUMED', 'EXPIRED', 'LOCKED'])->default('PENDING');
            $table->char('request_ip_hash', 64)->nullable();
            $table->timestamp('verified_at', 6)->nullable();
            $table->timestamp('consumed_at', 6)->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['verification_token_hash'], 'otp_challenges_verification_token_hash_unique');
            $table->index(['user_id'], 'otp_challenges_user_id_foreign');
            $table->index(['purpose', 'destination_fingerprint', 'status', 'expires_at'], 'otp_lookup_index');
            $table->foreign(['user_id'], 'otp_challenges_user_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
