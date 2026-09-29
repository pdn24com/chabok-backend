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
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('password_hash', 255);
            $table->string('algorithm', 40)->default('argon2id');
            $table->unsignedSmallInteger('algorithm_version')->default('1');
            $table->timestamp('password_changed_at', 6);
            $table->unsignedSmallInteger('failed_attempt_count')->default('0');
            $table->timestamp('locked_until', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['user_id'], 'user_id_public_unique');
            $table->foreign(['user_id'], 'authentication_credentials_user_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_credentials');
    }
};
