<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_hierarchies', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('parent_area_id');
            $table->unsignedInteger('child_area_id');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'parent_area_id', 'child_area_id'], 'area_hierarchy_edge_unique');
            $table->unique(['hq_id', 'child_area_id'], 'area_hierarchy_child_single_parent_unique');
            $table->index(['hq_id', 'parent_area_id'], 'area_hierarchy_parent_index');
            $table->index(['hq_id', 'child_area_id'], 'area_hierarchy_child_index');
            $table->foreign(['hq_id', 'child_area_id'], 'area_hierarchy_child_fk')->references(['hq_id', 'id'])->on('areas')->onDelete('restrict');
            $table->foreign(['hq_id', 'parent_area_id'], 'area_hierarchy_parent_fk')->references(['hq_id', 'id'])->on('areas')->onDelete('restrict');
            $table->foreign(['hq_id'], 'area_hierarchies_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_hierarchies');
    }
};
