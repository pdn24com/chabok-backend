<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('user_id');
            $table->enum('channel', ['SMS', 'EMAIL']);
            $table->string('normalized_recipient', 254);
            $table->char('token_hash', 64);
            $table->enum('status', ['PENDING', 'SUPERSEDED', 'ACCEPTED', 'EXPIRED'])->default('PENDING');
            $table->timestamp('sent_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('accepted_at', 6)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['token_hash'], 'user_invitations_token_hash_unique');
            $table->index(['user_id'], 'user_invitations_user_id_foreign');
            $table->index(['created_by'], 'user_invitations_created_by_foreign');
            $table->index(['hq_id', 'user_id', 'channel', 'status'], 'invitation_current_index');
            $table->foreign(['created_by'], 'user_invitations_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'user_invitations_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['user_id'], 'user_invitations_user_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
