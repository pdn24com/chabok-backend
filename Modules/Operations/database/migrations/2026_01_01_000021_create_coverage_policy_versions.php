<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_policy_versions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('coverage_policy_id');
            $table->unsignedInteger('version_number');
            $table->enum('status', ['DRAFT', 'VALIDATED', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED']);
            $table->timestamp('effective_from', 6)->nullable();
            $table->timestamp('effective_to', 6)->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('validated_by')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('published_by')->nullable();
            $table->timestamp('validated_at', 6)->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->string('content_digest', 64)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['coverage_policy_id', 'version_number'], 'coverage_versions_number_unique');
            $table->unique(['hq_id', 'id'], 'coverage_versions_hq_id_unique');
            $table->index(['hq_id', 'coverage_policy_id'], 'coverage_versions_policy_fk');
            $table->index(['created_by'], 'coverage_policy_versions_created_by_foreign');
            $table->index(['validated_by'], 'coverage_policy_versions_validated_by_foreign');
            $table->index(['approved_by'], 'coverage_policy_versions_approved_by_foreign');
            $table->index(['published_by'], 'coverage_policy_versions_published_by_foreign');
            $table->index(['hq_id', 'status', 'effective_from', 'effective_to'], 'coverage_versions_runtime_index');
            $table->foreign(['approved_by'], 'coverage_policy_versions_approved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'coverage_policy_versions_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'coverage_policy_id'], 'coverage_versions_policy_fk')->references(['hq_id', 'id'])->on('coverage_policies')->onDelete('restrict');
            $table->foreign(['published_by'], 'coverage_policy_versions_published_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['validated_by'], 'coverage_policy_versions_validated_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_policy_versions');
    }
};
