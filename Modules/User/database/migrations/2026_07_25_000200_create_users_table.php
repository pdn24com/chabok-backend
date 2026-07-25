<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->char('user_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->string('username', 100)->nullable();
            $table->string('normalized_username', 100)->nullable()->collation('utf8mb4_bin');
            $table->string('mobile', 32)->nullable();
            $table->string('normalized_mobile', 32)->nullable()->collation('utf8mb4_bin');
            $table->string('email', 254)->nullable();
            $table->string('normalized_email', 254)->nullable()->collation('utf8mb4_bin');
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('display_name', 240);
            $table->enum('status', ['INVITED', 'ACTIVE', 'SUSPENDED', 'DEACTIVATED']);
            $table->boolean('must_change_password')->default(false);
            $table->char('created_by', 36)->nullable();
            $table->timestamp('activated_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique('normalized_username', 'users_normalized_username_unique');
            $table->unique('normalized_mobile', 'users_normalized_mobile_unique');
            $table->unique('normalized_email', 'users_normalized_email_unique');
            $table->index(['hq_id', 'status'], 'users_hq_status_index');
            $table->index('created_at', 'users_created_at_index');
        });

        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_identifier_required CHECK (normalized_username IS NOT NULL OR normalized_mobile IS NOT NULL OR normalized_email IS NOT NULL)',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
