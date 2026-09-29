<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_statuses', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('owner_key', 36);
            $table->string('code', 32);
            $table->string('title_fa', 200);
            $table->string('title_en', 200)->nullable();
            $table->string('partial_title_fa', 200)->nullable();
            $table->string('partial_title_en', 200)->nullable();
            $table->string('tone', 20);
            $table->string('status_group', 30)->nullable();
            $table->boolean('is_terminal')->default(false);
            $table->boolean('manifest_enabled')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->unique(['owner_key', 'code']);
            $table->index(['hq_id', 'is_active']);
            $table->foreign('hq_id')->references('id')->on('hq_tenants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_statuses');
    }
};
