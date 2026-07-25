<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->char('record_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->char('actor_id', 36);
            $table->string('command_name', 160);
            $table->string('idempotency_key', 200);
            $table->char('request_fingerprint', 64);
            $table->enum('state', ['IN_PROGRESS', 'COMPLETED', 'FAILED']);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('safe_response')->nullable();
            $table->timestamp('expires_at', 6);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('actor_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(
                ['actor_id', 'command_name', 'idempotency_key'],
                'idempotency_actor_command_key_unique',
            );
            $table->index('expires_at', 'idempotency_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
