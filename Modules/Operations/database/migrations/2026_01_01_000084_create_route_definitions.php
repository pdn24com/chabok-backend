<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_definitions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('route_code', 80);
            $table->string('route_title', 200);
            $table->unsignedInteger('published_version_id')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('version')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'route_code'], 'route_definitions_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'route_definitions_hq_id_unique');
            $table->index(['hq_id', 'status'], 'route_definitions_hq_status_index');
            $table->index(['hq_id', 'published_version_id'], 'route_definitions_published_fk');
            $table->foreign(['hq_id', 'published_version_id'], 'route_definitions_published_fk')->references(['hq_id', 'id'])->on('route_definition_versions')->onDelete('restrict');
            $table->foreign(['hq_id'], 'route_definitions_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_definitions');
    }
};
