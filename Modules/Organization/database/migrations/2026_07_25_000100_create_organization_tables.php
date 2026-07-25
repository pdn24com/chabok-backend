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
        Schema::create('hq_tenants', function (Blueprint $table): void {
            $table->char('hq_id', 36)->primary();
            $table->string('hq_code', 80)->unique();
            $table->string('hq_title', 200);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps(6);
        });

        Schema::create('areas', function (Blueprint $table): void {
            $table->char('area_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('area_title', 200);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->unique(['hq_id', 'area_id'], 'areas_hq_area_unique');
            $table->index(['hq_id', 'status'], 'areas_hq_status_index');
        });

        Schema::create('area_hierarchies', function (Blueprint $table): void {
            $table->char('area_hierarchy_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('parent_area_id', 36);
            $table->char('child_area_id', 36);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign(['hq_id', 'parent_area_id'], 'area_hierarchy_parent_fk')
                ->references(['hq_id', 'area_id'])->on('areas')->restrictOnDelete();
            $table->foreign(['hq_id', 'child_area_id'], 'area_hierarchy_child_fk')
                ->references(['hq_id', 'area_id'])->on('areas')->restrictOnDelete();
            $table->unique(
                ['hq_id', 'parent_area_id', 'child_area_id'],
                'area_hierarchy_edge_unique',
            );
            $table->index(['hq_id', 'parent_area_id'], 'area_hierarchy_parent_index');
            $table->index(['hq_id', 'child_area_id'], 'area_hierarchy_child_index');
        });
        DB::statement(
            'ALTER TABLE area_hierarchies ADD CONSTRAINT area_hierarchy_not_self CHECK (parent_area_id <> child_area_id)',
        );

        Schema::create('nodes', function (Blueprint $table): void {
            $table->char('node_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('area_id', 36);
            $table->string('node_code', 80);
            $table->string('node_title', 200);
            $table->string('node_type', 60);
            $table->json('address_snapshot')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign(['hq_id', 'area_id'], 'nodes_hq_area_fk')
                ->references(['hq_id', 'area_id'])->on('areas')->restrictOnDelete();
            $table->unique(['hq_id', 'node_code'], 'nodes_hq_code_unique');
            $table->index(['hq_id', 'area_id', 'status'], 'nodes_hq_area_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
        Schema::dropIfExists('area_hierarchies');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('hq_tenants');
    }
};
