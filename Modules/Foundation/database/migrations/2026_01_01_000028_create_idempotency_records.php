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
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->unsignedInteger('actor_id');
            $table->string('command_name', 160);
            $table->string('idempotency_key', 200);
            $table->char('request_fingerprint', 64);
            $table->enum('state', ['IN_PROGRESS', 'COMPLETED', 'FAILED']);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('safe_response')->nullable();
            $table->timestamp('expires_at', 6);
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['actor_id', 'command_name', 'idempotency_key'], 'idempotency_actor_command_key_unique');
            $table->index(['hq_id'], 'idempotency_records_hq_id_foreign');
            $table->index(['expires_at'], 'idempotency_expiry_index');
            $table->foreign(['actor_id'], 'idempotency_records_actor_id_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'idempotency_records_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
