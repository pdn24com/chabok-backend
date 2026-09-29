<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            // Tree without cycles; the parent must belong to the same tenant.
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('title', 200);
            $table->boolean('active')->default('1');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'document_categories_hq_internal_id_unique');
            $table->index(['hq_id', 'parent_id', 'active'], 'document_categories_parent_active_index');
            $table->index(['created_by'], 'document_categories_created_by_fk');
            $table->foreign(['created_by'], 'document_categories_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'parent_id'], 'document_categories_parent_fk')->references(['hq_id', 'id'])->on('document_categories')->onDelete('restrict');
            $table->foreign(['hq_id'], 'document_categories_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
