<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hq_tenants', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_code', 80);
            $table->string('hq_title', 200);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_code'], 'hq_tenants_hq_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hq_tenants');
    }
};
