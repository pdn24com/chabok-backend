<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('area_id');
            $table->string('node_code', 80);
            $table->string('node_title', 200);
            $table->string('node_type', 60);
            $table->json('capabilities')->default(new Expression('(JSON_ARRAY())'));
            $table->json('address_snapshot')->nullable();
            $table->unsignedInteger('province_id')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->string('country_code', 2)->default('IR');
            $table->string('postal_code', 10)->nullable();
            $table->string('address_line', 1000)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('version')->default('1');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'node_code'], 'nodes_hq_code_unique');
            $table->unique(['hq_id', 'id'], 'nodes_hq_node_unique');
            $table->index(['hq_id', 'area_id', 'status'], 'nodes_hq_area_status_index');
            $table->index(['province_id'], 'nodes_province_fk');
            $table->index(['city_id'], 'nodes_city_fk');
            $table->index(['hq_id', 'node_type', 'status'], 'nodes_hq_type_status_index');
            $table->index(['hq_id', 'city_id', 'status'], 'nodes_hq_city_status_index');
            $table->foreign(['city_id'], 'nodes_city_fk')->references(['id'])->on('cities')->onDelete('restrict');
            $table->foreign(['hq_id', 'area_id'], 'nodes_hq_area_fk')->references(['hq_id', 'id'])->on('areas')->onDelete('restrict');
            $table->foreign(['hq_id'], 'nodes_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['province_id'], 'nodes_province_fk')->references(['id'])->on('provinces')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
